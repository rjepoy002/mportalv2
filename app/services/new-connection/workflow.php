<?php

final class NewConnectionConflict extends RuntimeException {}
final class NewConnectionDenied extends RuntimeException {}

/** Internal foundation only. No HTTP route calls this service in Task 04. */
final class NewConnectionWorkflow
{
    public const STAGES=[
        1=>'Applicant Information and Submission',2=>'Document Submission and Verification',
        3=>'Billing Clearance',4=>'Online PMES',5=>'Technical Inspection',
        6=>'Payment and Handwritten Form Signing',7=>'Installation Scheduling',
    ];
    public const TRANSITIONS=[
        'not_started'=>['in_progress'],'in_progress'=>['submitted'],
        'submitted'=>['under_review'],'under_review'=>['approved','needs_action','on_hold','rejected'],
        'needs_action'=>['in_progress'],'on_hold'=>['under_review'],'approved'=>[],'rejected'=>[],
    ];

    public function __construct(private PDO $pdo,private string $schema='mco_portal')
    {
        if($schema!=='mco_portal'&&!preg_match('/^mco_nc_test_[a-f0-9]{16}$/D',$schema))throw new InvalidArgumentException('Invalid ownership boundary.');
        if(!str_contains((string)$pdo->query('SELECT @@SESSION.sql_mode')->fetchColumn(),'STRICT_TRANS_TABLES')
            && !str_contains((string)$pdo->query('SELECT @@SESSION.sql_mode')->fetchColumn(),'STRICT_ALL_TABLES'))throw new RuntimeException('Strict SQL mode is required for this service.');
        $pdo->exec("SET SESSION time_zone='+00:00'");
    }
    private function table(string $suffix): string
    {
        if(!in_array($suffix,['applications','application_revisions','stages','stage_events','audit_events','staff_assignments','role_permissions','permissions'],true))throw new LogicException('Unknown module table.');
        return '`'.$this->schema.'`.`new_connection_'.$suffix.'`';
    }
    private function query(string $sql,array $params=[]): PDOStatement
    {
        $q=$this->pdo->prepare($sql);$q->execute($params);return $q;
    }
    private function actor(int $user): void
    {
        $row=$this->query('SELECT status,email_verified_at FROM `'.$this->schema.'`.users WHERE id=? FOR UPDATE',[$user])->fetch(PDO::FETCH_ASSOC);
        if(!$row||$row['status']!=='active'||empty($row['email_verified_at']))throw new NewConnectionDenied('Current active verified identity required.');
    }
    private function permission(int $user,string $permission,string $office): void
    {
        $q=$this->query('SELECT a.id FROM '.$this->table('staff_assignments').' a INNER JOIN '.$this->table('role_permissions').' rp ON rp.role_id=a.role_id INNER JOIN '.$this->table('permissions')." p ON p.id=rp.permission_id WHERE a.user_id=? AND p.code=? AND (a.office_scope=? OR a.office_scope='*') AND a.granted_at<=UTC_TIMESTAMP(6) AND a.revoked_at IS NULL AND (a.expires_at IS NULL OR a.expires_at>UTC_TIMESTAMP(6)) LIMIT 1 FOR UPDATE",[$user,$permission,$office]);
        if(!$q->fetchColumn())throw new NewConnectionDenied('Explicit scoped staff permission required.');
    }
    private function audit(int $app,int $actor,string $action,int $entity,array $safe): void
    {
        $this->query('INSERT INTO '.$this->table('audit_events')." (application_id,actor_user_id,action_code,entity_type,entity_id,safe_metadata_json,occurred_at) VALUES(?,?,?,'application',?,?,UTC_TIMESTAMP(6))",[$app,$actor,$action,$entity,json_encode($safe,JSON_THROW_ON_ERROR)]);
    }
    private function requestKey(string $key): void
    {
        if(!preg_match('/^[a-f0-9]{64}$/D',$key))throw new InvalidArgumentException('A 256-bit request key is required.');
    }
    private function transaction(callable $work): mixed
    {
        if($this->pdo->inTransaction())throw new LogicException('Foundation operations own their transactions.');
        $this->pdo->beginTransaction();
        try{$result=$work();$this->pdo->commit();return $result;}
        catch(Throwable $e){if($this->pdo->inTransaction())$this->pdo->rollBack();throw $e;}
    }
    /** Create no applicant data; foundation initialization is not a draft endpoint. */
    public function initialize(int $actor,string $key): string
    {
        $this->requestKey($key);
        return $this->transaction(function()use($actor,$key){
            $this->actor($actor);
            $old=$this->query('SELECT public_reference FROM '.$this->table('applications').' WHERE applicant_user_id=? AND creation_key=? FOR UPDATE',[$actor,$key])->fetchColumn();
            if($old)return $old;
            $reference=bin2hex(random_bytes(16));
            $this->query('INSERT INTO '.$this->table('applications')." (public_reference,applicant_user_id,creation_key,lifecycle_status,workflow_version,created_at,updated_at) VALUES(?,?,?,'draft',1,UTC_TIMESTAMP(6),UTC_TIMESTAMP(6))",[$reference,$actor,$key]);
            $app=(int)$this->pdo->lastInsertId();
            $this->query('INSERT INTO '.$this->table('application_revisions')." (application_id,revision_no,schema_version,snapshot_json,snapshot_sha256,submission_state,created_by,created_at) VALUES(?,1,1,'{}',?,'draft',?,UTC_TIMESTAMP(6))",[$app,hash('sha256','{}'),$actor]);
            $revision=(int)$this->pdo->lastInsertId();
            foreach(self::STAGES as $code=>$title)$this->query('INSERT INTO '.$this->table('stages').' (application_id,stage_code,status,revision_id,updated_at) VALUES(?,?,?,?,UTC_TIMESTAMP(6))',[$app,$code,$code===1?'in_progress':'not_started',$revision]);
            $this->query('UPDATE '.$this->table('applications').' SET current_revision_id=? WHERE id=?',[$revision,$app]);
            if((int)$this->query('SELECT COUNT(*) FROM '.$this->table('stages').' WHERE application_id=?',[$app])->fetchColumn()!==7)throw new LogicException('Seven-stage invariant failed.');
            $this->audit($app,$actor,'application.initialized',$app,['workflow_version'=>1]);
            return $reference;
        });
    }
    private function event(array $app,array $stage,int $actor,string $target,string $reason,string $key,string $fingerprint,int $revision): void
    {
        $version=(int)$stage['lock_version']+1;
        $decision=in_array($target,['approved','rejected','needs_action','on_hold'],true);
        $q=$this->query('UPDATE '.$this->table('stages')." SET status=?,revision_id=?,lock_version=?,decided_by=?,decision_reason=?,decided_at=CASE WHEN ? IS NULL THEN NULL ELSE UTC_TIMESTAMP(6) END,updated_at=UTC_TIMESTAMP(6) WHERE id=? AND lock_version=?",[$target,$revision,$version,$decision?$actor:null,$decision?$reason:null,$decision?$actor:null,$stage['id'],$stage['lock_version']]);
        if($q->rowCount()!==1)throw new NewConnectionConflict('Stale decision version.');
        $this->query('INSERT INTO '.$this->table('stage_events').' (application_id,stage_id,revision_id,actor_user_id,from_status,to_status,reason,stage_version,idempotency_key,request_sha256,occurred_at) VALUES(?,?,?,?,?,?,?,?,?,?,UTC_TIMESTAMP(6))',[$app['id'],$stage['id'],$revision,$actor,$stage['status'],$target,$reason,$version,$key,$fingerprint]);
    }
    /** Actor must come from authenticated server context, never a request body. */
    public function transition(int $actor,string $reference,int $code,string $target,int $expectedVersion,string $key,string $reason,bool $reopen=false): int
    {
        $this->requestKey($key);
        if(!isset(self::STAGES[$code])||!preg_match('/^[a-f0-9]{32}$/D',$reference)||$expectedVersion<0||trim($reason)===''||mb_strlen($reason)>1000)throw new InvalidArgumentException('Invalid workflow command.');
        $fingerprint=hash('sha256',json_encode([$actor,$reference,$code,$target,$expectedVersion,$reason,$reopen],JSON_THROW_ON_ERROR));
        return $this->transaction(function()use($actor,$reference,$code,$target,$expectedVersion,$key,$reason,$reopen,$fingerprint){
            $this->actor($actor);
            $app=$this->query('SELECT * FROM '.$this->table('applications').' WHERE public_reference=? FOR UPDATE',[$reference])->fetch(PDO::FETCH_ASSOC);
            if(!$app)throw new NewConnectionDenied('Application unavailable.');
            $rows=$this->query('SELECT * FROM '.$this->table('stages').' WHERE application_id=? ORDER BY stage_code FOR UPDATE',[$app['id']])->fetchAll(PDO::FETCH_ASSOC);
            if(count($rows)!==7)throw new LogicException('Incomplete workflow.');
            $stage=$rows[$code-1];
            $ownerAction=$code===1&&(int)$app['applicant_user_id']===$actor&&!$reopen
                &&(($stage['status']==='in_progress'&&$target==='submitted')||($stage['status']==='needs_action'&&$target==='in_progress'));
            // Replay still requires current authorization, including after revocation.
            $old=$this->query('SELECT request_sha256,stage_version,from_status FROM '.$this->table('stage_events').' WHERE application_id=? AND idempotency_key=?',[$app['id'],$key])->fetch(PDO::FETCH_ASSOC);
            if(!$ownerAction){
                // A matching owner submission replay remains an owner operation.
                if(!($old && $code===1 && (int)$app['applicant_user_id']===$actor && !$reopen && ($target==='submitted'||($target==='in_progress'&&$old['from_status']==='needs_action'))))$this->permission($actor,'stage.'.$code.'.manage',$app['office_code']);
            }
            if($reopen)$this->permission($actor,'workflow.reopen',$app['office_code']);
            if($old){if(!hash_equals($old['request_sha256'],$fingerprint))throw new NewConnectionConflict('Request key was reused for different input.');return (int)$old['stage_version'];}
            if((int)$stage['lock_version']!==$expectedVersion)throw new NewConnectionConflict('Stale decision version.');
            if($reopen){
                if(!in_array($stage['status'],['approved','rejected'],true)||$target!=='in_progress')throw new DomainException('Invalid reopen.');
            }elseif(!in_array($target,self::TRANSITIONS[$stage['status']],true))throw new DomainException('Invalid stage transition.');
            if(in_array($app['lifecycle_status'],['withdrawn','rejected','scheduled'],true)&&!$reopen)throw new DomainException('Application requires authorized reopening.');
            foreach($rows as $prior)if((int)$prior['stage_code']<$code&&$prior['status']!=='approved')throw new DomainException('Earlier stage approval is required.');
            $revision=(int)$app['current_revision_id'];
            if($code===1&&$target==='in_progress'){
                $this->query('INSERT INTO '.$this->table('application_revisions')." (application_id,revision_no,schema_version,snapshot_json,snapshot_sha256,submission_state,created_by,created_at) SELECT application_id,?,schema_version,snapshot_json,snapshot_sha256,'draft',?,UTC_TIMESTAMP(6) FROM ".$this->table('application_revisions').' WHERE id=?',[(int)$app['revision_no']+1,$actor,$revision]);
                $revision=(int)$this->pdo->lastInsertId();$app['revision_no']++;
            }
            if($code===1&&$target==='submitted'){
                $q=$this->query('UPDATE '.$this->table('application_revisions')." SET submission_state='submitted',submitted_at=UTC_TIMESTAMP(6) WHERE id=? AND submission_state='draft'",[$revision]);
                if($q->rowCount()!==1)throw new NewConnectionConflict('Revision already submitted.');
            }
            $this->event($app,$stage,$actor,$target,$reason,$key,$fingerprint,$revision);
            if($reopen||($code===1&&$target==='in_progress')){
                foreach($rows as $downstream){
                    if((int)$downstream['stage_code']<=$code)continue;
                    $derived=hash('sha256',$key.':invalidate:'.$downstream['stage_code']);
                    $this->event($app,$downstream,$actor,'not_started','Invalidated by earlier-stage correction/reopening',$derived,$fingerprint,$revision);
                }
            }
            $lifecycle=$target==='rejected'?'rejected':($code===7&&$target==='approved'?'scheduled':($code===1&&$target==='in_progress'?'draft':($code===1&&$target==='submitted'?'submitted':'processing')));
            $this->query('UPDATE '.$this->table('applications').' SET lifecycle_status=?,current_revision_id=?,revision_no=?,lock_version=lock_version+1,submitted_at=CASE WHEN ?=1 AND ?=\'submitted\' THEN UTC_TIMESTAMP(6) ELSE submitted_at END,updated_at=UTC_TIMESTAMP(6) WHERE id=?',[$lifecycle,$revision,$app['revision_no'],$code,$target,$app['id']]);
            $this->audit((int)$app['id'],$actor,$reopen?'stage.reopened':'stage.transition',(int)$app['id'],['stage'=>$code,'from'=>$stage['status'],'to'=>$target,'version'=>$expectedVersion+1]);
            return $expectedVersion+1;
        });
    }
}
