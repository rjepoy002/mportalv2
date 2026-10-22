<?php
require_once __DIR__.'/workflow.php';
require_once __DIR__.'/applicant-contract.php';

final class NewConnectionDraftInvalid extends DomainException
{
    public function __construct(public readonly array $errors) { parent::__construct('Invalid draft fields.'); }
}
final class NewConnectionDraftLimited extends RuntimeException {}

/** Internal only. Caller supplies authenticated server identity, never request-body identity.
 * No connection factory, session, HTTP route, logging, mail or operational-table access.
 */
class NewConnectionDrafts
{
    public function __construct(protected PDO $pdo,protected string $schema,protected array $catalog)
    {
        if($schema!=='mco_portal'&&!preg_match('/^mco_nc_test_[a-f0-9]{16}$/D',$schema))throw new InvalidArgumentException('Invalid draft ownership boundary.');
        if($pdo->getAttribute(PDO::ATTR_ERRMODE)!==PDO::ERRMODE_EXCEPTION||$pdo->getAttribute(PDO::ATTR_EMULATE_PREPARES))throw new RuntimeException('Exception mode and native prepared statements required.');
        if(!preg_match('/(?:STRICT_TRANS_TABLES|STRICT_ALL_TABLES)/',(string)$pdo->query('SELECT @@SESSION.sql_mode')->fetchColumn()))throw new RuntimeException('Strict SQL mode required.');
        $pdo->exec("SET SESSION time_zone='+00:00'");
    }
    protected function table(string $name): string { return '`'.$this->schema.'`.`new_connection_'.$name.'`'; }
    protected function query(string $sql,array $values=[]): PDOStatement
    {
        $q=$this->pdo->prepare($sql);$q->execute($values);return $q;
    }
    protected function transaction(callable $work): mixed
    {
        if($this->pdo->inTransaction())throw new LogicException('Draft service owns its transaction.');
        $this->pdo->beginTransaction();
        try{$result=$work();$this->pdo->commit();return $result;}
        catch(Throwable $e){if($this->pdo->inTransaction())$this->pdo->rollBack();throw $e;}
    }
    protected function actor(int $id): array
    {
        $user=$this->query('SELECT id,email,status,email_verified_at FROM `'.$this->schema.'`.users WHERE id=? FOR UPDATE',[$id])->fetch(PDO::FETCH_ASSOC);
        if(!$user||$user['status']!=='active'||empty($user['email_verified_at']))throw new NewConnectionDenied('Draft unavailable.');
        return $user;
    }
    protected function key(string $key): void
    {
        if(!preg_match('/^[a-f0-9]{64}$/D',$key))throw new InvalidArgumentException('A 256-bit request key is required.');
    }
    protected function owned(int $actor,string $reference): array
    {
        if(!preg_match('/^[a-f0-9]{32}$/D',$reference))throw new NewConnectionDenied('Draft unavailable.');
        $app=$this->query('SELECT * FROM '.$this->table('applications').' WHERE public_reference=? AND applicant_user_id=? FOR UPDATE',[$reference,$actor])->fetch(PDO::FETCH_ASSOC);
        if(!$app)throw new NewConnectionDenied('Draft unavailable.');
        $stages=$this->query('SELECT * FROM '.$this->table('stages').' WHERE application_id=? ORDER BY stage_code FOR UPDATE',[$app['id']])->fetchAll(PDO::FETCH_ASSOC);
        $revision=$this->query('SELECT * FROM '.$this->table('application_revisions').' WHERE application_id=? AND id=? FOR UPDATE',[$app['id'],$app['current_revision_id']])->fetch(PDO::FETCH_ASSOC);
        if(count($stages)!==7||!$revision||(int)$revision['revision_no']!==(int)$app['revision_no']||(int)$app['workflow_version']!==1||(int)$revision['schema_version']!==1)throw new NewConnectionDenied('Draft unavailable.');

        if(!hash_equals($revision['snapshot_sha256'],hash('sha256',$revision['snapshot_json'])))throw new RuntimeException('Draft integrity check failed.');
        return [$app,$revision];
    }
    protected function locked(int $actor,string $reference): array
    {
        [$app,$revision]=$this->owned($actor,$reference);
        $stage=$this->query('SELECT * FROM '.$this->table('stages').' WHERE application_id=? AND stage_code=1 FOR UPDATE',[$app['id']])->fetch(PDO::FETCH_ASSOC);
        if($app['lifecycle_status']!=='draft'||$revision['submission_state']!=='draft'||!$stage||$stage['status']!=='in_progress'||(int)$stage['revision_id']!==(int)$revision['id'])throw new NewConnectionDenied('Draft is not editable.');
        return [$app,$revision];
    }
    protected function validationContext(array $user): array {return ['email'=>$user['email'],'catalog'=>$this->catalog];}
    private function validate(string $json,array $user): array
    {
        $result=NewConnectionApplicantContract::validate($json,$this->validationContext($user));
        if(!$result['valid'])throw new NewConnectionDraftInvalid($result['errors']);
        // Pending acknowledgments are neither durable draft data nor approved consent.
        $snapshot=$result['snapshot'];$snapshot['review']['information_confirmed']=false;
        $canonical=NewConnectionApplicantContract::canonical($snapshot);
        return [$canonical,hash('sha256',$canonical)];
    }
    protected function receipt(array $app,array $revision): array
    {
        return ['reference'=>$app['public_reference'],'application_version'=>(int)$app['lock_version'],
            'revision_id'=>(int)$revision['id'],'revision_no'=>(int)$revision['revision_no'],
            'revision_sha256'=>$revision['snapshot_sha256']];
    }
    protected function limit(int $actor): void
    {
        // User row is locked, so successful writes across all this user's drafts serialize.
        $count=(int)$this->query('SELECT COUNT(*) FROM '.$this->table('audit_events')." WHERE actor_user_id=? AND action_code IN ('draft.created','draft.saved','stage1.submitted') AND occurred_at>UTC_TIMESTAMP(6)-INTERVAL 1 MINUTE",[$actor])->fetchColumn();
        if($count>=30)throw new NewConnectionDraftLimited('Draft write limit reached. Try later.');
    }
    protected function audit(int $app,int $actor,string $action,int $version,int $revision): void
    {
        $this->query('INSERT INTO '.$this->table('audit_events')." (application_id,actor_user_id,action_code,entity_type,entity_id,safe_metadata_json,occurred_at) VALUES(?,?,?,'application',?,?,UTC_TIMESTAMP(6))",[$app,$actor,$action,$app,json_encode(['application_version'=>$version,'revision_no'=>$revision],JSON_THROW_ON_ERROR)]);
    }
    /** Atomic empty canonical draft plus seven stages. Retry returns original create receipt. */
    public function create(int $authenticatedUser,string $creationKey): array
    {
        $this->key($creationKey);
        return $this->transaction(function()use($authenticatedUser,$creationKey){
            $user=$this->actor($authenticatedUser);
            $fingerprint=hash('sha256',json_encode(['draft.create',$authenticatedUser,$creationKey],JSON_THROW_ON_ERROR));
            $old=$this->query('SELECT public_reference FROM '.$this->table('applications').' WHERE applicant_user_id=? AND creation_key=? FOR UPDATE',[$authenticatedUser,$creationKey])->fetchColumn();
            if($old){
                [$app,$revision]=$this->locked($authenticatedUser,$old);
                $request=$this->query('SELECT request_sha256,receipt_json FROM '.$this->table('draft_requests').' WHERE application_id=? AND actor_user_id=? AND idempotency_key=? FOR UPDATE',[$app['id'],$authenticatedUser,$creationKey])->fetch(PDO::FETCH_ASSOC);
                if(!$request||!hash_equals($request['request_sha256'],$fingerprint))throw new NewConnectionConflict('Creation key belongs to another command.');
                return json_decode($request['receipt_json'],true,32,JSON_THROW_ON_ERROR);
            }
            $this->limit($authenticatedUser);
            [$canonical,$hash]=$this->validate(json_encode(NewConnectionApplicantContract::emptyInput(),JSON_THROW_ON_ERROR),$user);
            $reference=bin2hex(random_bytes(16));
            $this->query('INSERT INTO '.$this->table('applications')." (public_reference,applicant_user_id,creation_key,lifecycle_status,workflow_version,created_at,updated_at) VALUES(?,?,?,'draft',1,UTC_TIMESTAMP(6),UTC_TIMESTAMP(6))",[$reference,$authenticatedUser,$creationKey]);
            $app=(int)$this->pdo->lastInsertId();
            $this->query('INSERT INTO '.$this->table('application_revisions')." (application_id,revision_no,schema_version,snapshot_json,snapshot_sha256,submission_state,created_by,created_at) VALUES(?,1,1,?,?,'draft',?,UTC_TIMESTAMP(6))",[$app,$canonical,$hash,$authenticatedUser]);
            $revision=(int)$this->pdo->lastInsertId();
            foreach(NewConnectionWorkflow::STAGES as $code=>$label)$this->query('INSERT INTO '.$this->table('stages').' (application_id,stage_code,status,revision_id,updated_at) VALUES(?,?,?,?,UTC_TIMESTAMP(6))',[$app,$code,$code===1?'in_progress':'not_started',$revision]);
            $this->query('UPDATE '.$this->table('applications').' SET current_revision_id=? WHERE id=?',[$revision,$app]);
            $this->audit($app,$authenticatedUser,'draft.created',0,1);
            $receipt=$this->receipt(...$this->locked($authenticatedUser,$reference));
            $this->query('INSERT INTO '.$this->table('draft_requests').' (application_id,actor_user_id,idempotency_key,request_sha256,receipt_json,occurred_at) VALUES(?,?,?,?,?,UTC_TIMESTAMP(6))',[$app,$authenticatedUser,$creationKey,$fingerprint,json_encode($receipt,JSON_THROW_ON_ERROR)]);
            return $receipt;
        });
    }
    /** Read/resume current editable draft; a reference never grants access by itself. */
    public function retrieve(int $authenticatedUser,string $reference): array
    {
        return $this->transaction(function()use($authenticatedUser,$reference){
            $this->actor($authenticatedUser);[$app,$revision]=$this->locked($authenticatedUser,$reference);
            $snapshot=json_decode($revision['snapshot_json'],true,32,JSON_THROW_ON_ERROR);
            $input=NewConnectionApplicantContract::emptyInput();
            foreach($input as $section=>&$fields)foreach($fields as $field=>&$value)if($section!=='acknowledgments'&&array_key_exists($field,$snapshot[$section]??[]))$value=$snapshot[$section][$field];
            unset($fields,$value);$input['review']['information_confirmed']=false;
            return $this->receipt($app,$revision)+['snapshot'=>$snapshot,'input'=>$input];
        });
    }
    /** Expected token is the receipt's application version plus revision identity/content version. */
    public function save(int $authenticatedUser,string $reference,array $expected,string $idempotencyKey,string $inputJson): array
    {
        $this->key($idempotencyKey);
        if(strlen($inputJson)>65536)throw new NewConnectionDraftInvalid(['form'=>'Request exceeds 64 KiB.']);
        $keys=['application_version','revision_id','revision_no','revision_sha256'];$provided=array_keys($expected);sort($keys);sort($provided);
        if($keys!==$provided||!is_int($expected['application_version'])||$expected['application_version']<0||!is_int($expected['revision_id'])||$expected['revision_id']<1||!is_int($expected['revision_no'])||$expected['revision_no']<1||!is_string($expected['revision_sha256'])||!preg_match('/^[a-f0-9]{64}$/D',$expected['revision_sha256']))throw new InvalidArgumentException('Invalid expected draft versions.');
        $ordered=[];foreach($keys as $key)$ordered[$key]=$expected[$key];
        $fingerprint=hash('sha256',json_encode([$authenticatedUser,$reference,$ordered,$inputJson],JSON_THROW_ON_ERROR));
        return $this->transaction(function()use($authenticatedUser,$reference,$expected,$idempotencyKey,$inputJson,$fingerprint){
            $user=$this->actor($authenticatedUser);[$app,$revision]=$this->locked($authenticatedUser,$reference);
            $old=$this->query('SELECT request_sha256,receipt_json FROM '.$this->table('draft_requests').' WHERE application_id=? AND actor_user_id=? AND idempotency_key=? FOR UPDATE',[$app['id'],$authenticatedUser,$idempotencyKey])->fetch(PDO::FETCH_ASSOC);
            if($old){if(!hash_equals($old['request_sha256'],$fingerprint))throw new NewConnectionConflict('Request key reused with different content.');return json_decode($old['receipt_json'],true,32,JSON_THROW_ON_ERROR);}
            $current=$this->receipt($app,$revision);
            foreach($expected as $key=>$value)if($current[$key]!==$value)throw new NewConnectionConflict('Draft changed. Reload before saving.');
            $this->limit($authenticatedUser);[$canonical,$hash]=$this->validate($inputJson,$user);
            $this->query('UPDATE '.$this->table('application_revisions')." SET snapshot_json=?,snapshot_sha256=? WHERE application_id=? AND id=? AND submission_state='draft'",[$canonical,$hash,$app['id'],$revision['id']]);
            $q=$this->query('UPDATE '.$this->table('applications').' SET lock_version=lock_version+1,updated_at=UTC_TIMESTAMP(6) WHERE id=? AND lock_version=?',[$app['id'],$expected['application_version']]);
            if($q->rowCount()!==1)throw new NewConnectionConflict('Draft changed.');
            $app['lock_version']++;$revision['snapshot_sha256']=$hash;$receipt=$this->receipt($app,$revision);
            $this->audit((int)$app['id'],$authenticatedUser,'draft.saved',(int)$app['lock_version'],(int)$revision['revision_no']);
            $this->query('INSERT INTO '.$this->table('draft_requests').' (application_id,actor_user_id,idempotency_key,request_sha256,receipt_json,occurred_at) VALUES(?,?,?,?,?,UTC_TIMESTAMP(6))',[$app['id'],$authenticatedUser,$idempotencyKey,$fingerprint,json_encode($receipt,JSON_THROW_ON_ERROR)]);
            return $receipt;
        });
    }
}
