<?php
require_once __DIR__.'/drafts.php';
require_once __DIR__.'/publication.php';
require_once __DIR__.'/review-keys.php';

/** Synthetic submission foundation ONLY. No production-schema adapter or HTTP endpoint.
 * Inherits draft operations; shares their transaction ownership and row-lock order.
 */
final class NewConnectionStage1Submission extends NewConnectionDrafts
{
    private Closure $publication;
    private Closure $clock;
    private ?NewConnectionPublication $registry;
    private NewConnectionReviewKeys $keys;
    public function __construct(PDO $pdo,string $schema,callable $publication,string|NewConnectionReviewKeys $reviewSecret,?callable $clock=null)
    {
        if(!preg_match('/^mco_nc_test_[a-f0-9]{16}$/D',$schema))throw new NewConnectionDenied('Submission foundation requires a disposable schema.');
        $this->keys=is_string($reviewSecret)?new NewConnectionReviewKeys('fixture-default',['fixture-default'=>$reviewSecret]):$reviewSecret;
        $this->registry=$publication instanceof NewConnectionPublication?$publication:null;
        if($this->registry&&!$this->registry->matches($pdo,$schema))throw new LogicException('Publication provider must share the exact PDO transaction.');
        $this->publication=Closure::fromCallable($publication);$this->clock=$clock===null?static fn()=>time():Closure::fromCallable($clock);
        // Current publication is checked for NEW commands, not successful receipt replays.
        $initial=($this->publication)();
        if(isset($initial['bundle_id'])&&!$this->registry)throw new LogicException('Use the typed transaction-bound publication registry.');
        if(!is_array($initial)||($initial['environment']??null)!=='synthetic_test'||!is_array($initial['catalog']??null))throw new NewConnectionDenied('Synthetic test context required.');
        parent::__construct($pdo,$schema,$initial['catalog']);
    }
    private function policy(): array
    {
        $policy=($this->publication)();
        if(!is_array($policy)||($policy['environment']??null)!=='synthetic_test'||($policy['catalog_approved_for_test']??false)!==true||!is_array($policy['catalog']??null)||!is_string($policy['catalog']['version']??null)||!is_array($policy['notices']??null))throw new NewConnectionDenied('Published test policy required.');
        $codes=array_keys($policy['notices']);$required=NewConnectionApplicantContract::ACKS;sort($codes);sort($required);
        if($codes!==$required)throw new NewConnectionDenied('Published test notices required.');
        $notices=[];
        foreach(NewConnectionApplicantContract::ACKS as $code){
            $notice=$policy['notices'][$code];
            if(!is_array($notice)||($notice['published']??false)!==true||($notice['test_only']??false)!==true||!is_string($notice['version']??null)||!preg_match('/^TEST-[A-Za-z0-9._-]{1,90}$/D',$notice['version'])||!is_string($notice['content']??null)||$notice['content']===''||strlen($notice['content'])>32768||!mb_check_encoding($notice['content'],'UTF-8'))throw new NewConnectionDenied('Published test notices required.');
            // Never trust a caller/browser-supplied content hash; derive it from published bytes.
            $notices[$code]=['version'=>$notice['version'],'sha256'=>hash('sha256',$notice['content']),'content'=>$notice['content']];
        }
        return ['catalog'=>$policy['catalog'],'notices'=>$notices,'publication'=>isset($policy['epoch'])?['bundle_id'=>$policy['bundle_id'],'epoch'=>$policy['epoch'],'sha256'=>$policy['bundle_sha256']]:null];
    }
    private function expected(array $expected): array
    {
        $keys=['application_version','revision_id','revision_no','revision_sha256'];$given=array_keys($expected);sort($keys);sort($given);
        if($keys!==$given)throw new InvalidArgumentException('Invalid submission versions.');
        foreach(['application_version'=>0,'revision_id'=>1,'revision_no'=>1] as $name=>$min)if(!is_int($expected[$name])||$expected[$name]<$min)throw new InvalidArgumentException('Invalid submission versions.');
        if(!is_string($expected['revision_sha256'])||!preg_match('/^[a-f0-9]{64}$/D',$expected['revision_sha256']))throw new InvalidArgumentException('Invalid submission versions.');
        ksort($expected);return $expected;
    }
    private function matchVersions(array $app,array $revision,array $expected): void
    {
        $current=$this->receipt($app,$revision);foreach($expected as $key=>$value)if($current[$key]!==$value)throw new NewConnectionConflict('Draft changed. Review again.');
    }
    private function prepared(array $app,array $revision,array $user,array $policy): array
    {
        $snapshot=json_decode($revision['snapshot_json'],true,32,JSON_THROW_ON_ERROR);
        $input=NewConnectionApplicantContract::emptyInput();
        foreach($input as $section=>&$fields)foreach($fields as $field=>&$value)if($section!=='acknowledgments'&&array_key_exists($field,$snapshot[$section]??[]))$value=$snapshot[$section][$field];
        unset($fields,$value);
        // Completeness simulation for review; selections are separately required at submit.
        $input['review']['information_confirmed']=true;$input['acknowledgments']=array_fill_keys(NewConnectionApplicantContract::ACKS,true);
        $result=NewConnectionApplicantContract::validate(json_encode($input,JSON_THROW_ON_ERROR),['email'=>$user['email'],'catalog'=>$policy['catalog']],true);
        if(!$result['ready'])throw new NewConnectionDraftInvalid($result['errors']);
        $draft=$result['snapshot'];$draft['review']['information_confirmed']=false;
        if(!hash_equals($revision['snapshot_sha256'],hash('sha256',NewConnectionApplicantContract::canonical($draft))))throw new NewConnectionConflict('Saved content or policy changed. Save and review again.');
        return $result;
    }
    private function binding(int $actor,array $app,array $revision,array $policy): array
    {
        $notices=[];foreach($policy['notices'] as $code=>$notice)$notices[$code]=['version'=>$notice['version'],'sha256'=>$notice['sha256']];
        return ['purpose'=>'stage1.synthetic.review.v2','actor'=>$actor,'application'=>(int)$app['id'],'reference'=>$app['public_reference'],
            'application_version'=>(int)$app['lock_version'],'revision_id'=>(int)$revision['id'],'revision_no'=>(int)$revision['revision_no'],
            'snapshot_sha256'=>$revision['snapshot_sha256'],'contract'=>NewConnectionApplicantContract::VERSION,
            'catalog_versions'=>$policy['catalog']['versions']??['localities'=>$policy['catalog']['version'],'service_requests'=>$policy['catalog']['version']],'publication'=>$policy['publication'],
            'catalog_sha256'=>hash('sha256',NewConnectionApplicantContract::canonical($policy['catalog'])),'notices'=>$notices];
    }
    protected function validationContext(array $user): array
    {
        if(!$this->registry)return parent::validationContext($user);
        $policy=$this->policy();return ['email'=>$user['email'],'catalog'=>$policy['catalog']];
    }
    private function proof(array $binding): string {return $this->keys->issue($binding,($this->clock)());}
    private function verify(string $proof,array $binding): string {return $this->keys->verify($proof,$binding,($this->clock)());}
    /** Server-issued review of the saved draft. Proof is not authentication or CSRF. */
    public function review(int $authenticatedUser,string $reference,array $expected): array
    {
        $expected=$this->expected($expected);
        return $this->transaction(function()use($authenticatedUser,$reference,$expected){
            $user=$this->actor($authenticatedUser);[$app,$revision]=$this->locked($authenticatedUser,$reference);$this->matchVersions($app,$revision,$expected);
            $policy=$this->policy();$result=$this->prepared($app,$revision,$user,$policy);
            return ['proof'=>$this->proof($this->binding($authenticatedUser,$app,$revision,$policy)),'expires_in'=>300,'snapshot'=>$result['snapshot'],'notices'=>$policy['notices']];
        });
    }
    /** Private read of the current revision's committed outcome; never creates a command. */
    public function submissionReceipt(int $authenticatedUser,string $reference): array
    {
        return $this->transaction(function()use($authenticatedUser,$reference){
            $this->actor($authenticatedUser);[$app,$revision]=$this->owned($authenticatedUser,$reference);
            $json=$this->query('SELECT receipt_json FROM '.$this->table('submission_requests').' WHERE application_id=? AND revision_id=? AND actor_user_id=?',[$app['id'],$revision['id'],$authenticatedUser])->fetchColumn();
            if(!$json)throw new NewConnectionDenied('Submission receipt unavailable.');
            return json_decode($json,true,32,JSON_THROW_ON_ERROR);
        });
    }
    /** All content comes from the locked saved draft; browser command contains selections only. */
    public function submit(int $authenticatedUser,string $reference,array $expected,string $key,string $proof,string $selectionsJson): array
    {
        $this->key($key);$expected=$this->expected($expected);
        if(strlen($proof)>8192||strlen($selectionsJson)>8192)throw new NewConnectionDraftInvalid(['form'=>'Submission command too large.']);
        $fingerprint=hash('sha256',json_encode(['stage1.submit',$authenticatedUser,$reference,$expected,$proof,$selectionsJson],JSON_THROW_ON_ERROR));
        return $this->transaction(function()use($authenticatedUser,$reference,$expected,$key,$proof,$selectionsJson,$fingerprint){
            $user=$this->actor($authenticatedUser);[$app,$revision]=$this->owned($authenticatedUser,$reference);
            $old=$this->query('SELECT request_sha256,receipt_json FROM '.$this->table('submission_requests').' WHERE application_id=? AND actor_user_id=? AND idempotency_key=? FOR UPDATE',[$app['id'],$authenticatedUser,$key])->fetch(PDO::FETCH_ASSOC);
            if($old){if(!hash_equals($old['request_sha256'],$fingerprint))throw new NewConnectionConflict('Submission key reused with different content.');return json_decode($old['receipt_json'],true,32,JSON_THROW_ON_ERROR);}
            [$app,$revision]=$this->locked($authenticatedUser,$reference);$this->matchVersions($app,$revision,$expected);$this->limit($authenticatedUser);
            $policy=$this->policy();$result=$this->prepared($app,$revision,$user,$policy);$proofKey=$this->verify($proof,$this->binding($authenticatedUser,$app,$revision,$policy));
            try{$selections=json_decode($selectionsJson,false,16,JSON_THROW_ON_ERROR);}catch(Throwable $e){throw new NewConnectionDraftInvalid(['form'=>'Invalid acknowledgment selections.']);}
            if(!$selections instanceof stdClass||count(get_object_vars($selections))!==2||($selections->review_confirmed??null)!==true||!($selections->acknowledgments??null) instanceof stdClass)throw new NewConnectionDraftInvalid(['form'=>'Confirm review and all required acknowledgments.']);
            $chosen=get_object_vars($selections->acknowledgments);$codes=array_keys($chosen);$required=NewConnectionApplicantContract::ACKS;sort($codes);sort($required);
            if($codes!==$required)throw new NewConnectionDraftInvalid(['form'=>'Select exactly the required acknowledgments.']);
            foreach($chosen as $value)if($value!==true)throw new NewConnectionDraftInvalid(['form'=>'Select every required acknowledgment.']);
            $stages=$this->query('SELECT * FROM '.$this->table('stages').' WHERE application_id=? ORDER BY stage_code FOR UPDATE',[$app['id']])->fetchAll(PDO::FETCH_ASSOC);$stage=$stages[0];
            if(!in_array('submitted',NewConnectionWorkflow::TRANSITIONS[$stage['status']],true))throw new NewConnectionDenied('Stage 1 transition unavailable.');
            foreach($stages as $row)if((int)$row['stage_code']>1&&$row['status']!=='not_started')throw new NewConnectionDenied('Downstream workflow inconsistent.');
            foreach($policy['notices'] as $code=>$notice)$this->query('INSERT INTO '.$this->table('consents').' (application_id,revision_id,applicant_user_id,acknowledgment_code,document_version,document_sha256,acknowledged_at) VALUES(?,?,?,?,?,?,UTC_TIMESTAMP(6))',[$app['id'],$revision['id'],$authenticatedUser,$code,$notice['version'],$notice['sha256']]);
            $q=$this->query('UPDATE '.$this->table('application_revisions')." SET snapshot_json=?,snapshot_sha256=?,submission_state='submitted',submitted_at=UTC_TIMESTAMP(6) WHERE application_id=? AND id=? AND submission_state='draft'",[$result['canonical'],$result['sha256'],$app['id'],$revision['id']]);
            if($q->rowCount()!==1)throw new NewConnectionConflict('Revision already submitted.');
            $q=$this->query('UPDATE '.$this->table('stages')." SET status='submitted',lock_version=lock_version+1,updated_at=UTC_TIMESTAMP(6) WHERE id=? AND lock_version=? AND status='in_progress'",[$stage['id'],$stage['lock_version']]);
            if($q->rowCount()!==1)throw new NewConnectionConflict('Stage changed.');
            $this->query('INSERT INTO '.$this->table('stage_events')." (application_id,stage_id,revision_id,actor_user_id,from_status,to_status,reason,stage_version,idempotency_key,request_sha256,occurred_at) VALUES(?,?,?,?,'in_progress','submitted','Synthetic test Stage 1 submission',?,?,?,UTC_TIMESTAMP(6))",[$app['id'],$stage['id'],$revision['id'],$authenticatedUser,(int)$stage['lock_version']+1,$key,$fingerprint]);
            $q=$this->query('UPDATE '.$this->table('applications')." SET lifecycle_status='submitted',lock_version=lock_version+1,submitted_at=UTC_TIMESTAMP(6),updated_at=UTC_TIMESTAMP(6) WHERE id=? AND lock_version=? AND lifecycle_status='draft'",[$app['id'],$app['lock_version']]);
            if($q->rowCount()!==1)throw new NewConnectionConflict('Application changed.');
            $app['lock_version']++;$revision['snapshot_sha256']=$result['sha256'];
            $receipt=$this->receipt($app,$revision)+['stage_version'=>(int)$stage['lock_version']+1,'status'=>'submitted'];
            if($policy['publication']!==null)$this->query('INSERT INTO '.$this->table('submission_publications')." (application_id,revision_id,applicant_user_id,bundle_id,scope,epoch,proof_key_id,occurred_at) VALUES(?,?,?,?,'STAGE1',?,?,UTC_TIMESTAMP(6))",[$app['id'],$revision['id'],$authenticatedUser,$policy['publication']['bundle_id'],$policy['publication']['epoch'],$proofKey]);
            $this->audit((int)$app['id'],$authenticatedUser,'stage1.submitted',(int)$app['lock_version'],(int)$revision['revision_no']);
            $this->query('INSERT INTO '.$this->table('submission_requests').' (application_id,actor_user_id,revision_id,idempotency_key,request_sha256,receipt_json,occurred_at) VALUES(?,?,?,?,?,?,UTC_TIMESTAMP(6))',[$app['id'],$authenticatedUser,$revision['id'],$key,$fingerprint,json_encode($receipt,JSON_THROW_ON_ERROR)]);
            return $receipt;
        });
    }
}
