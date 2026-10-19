<?php

require_once __DIR__ . '/../helpers/philippine-mobile.php';

function portalProfileValues(array $input): array
{
    $values=[];
    foreach(['first_name','last_name','middle_name','extension_name'] as $key){
        $value=is_string($input[$key]??null)?trim($input[$key]):'';
        if(!mb_check_encoding($value,'UTF-8')||mb_strlen($value)>25||preg_match('/[\x00-\x1F\x7F]/',$value)) throw new DomainException('Each name field must be valid text of at most 25 characters.');
        $values[$key]=$value!==''?$value:null;
    }
    if(!$values['first_name']||!$values['last_name']) throw new DomainException('Please enter and confirm your first and last names.');
    $contact=is_string($input['contact_number']??null)?trim($input['contact_number']):'';
    if($contact!==''&&!preg_match('/^09[0-9]{9}$/D',$contact)) throw new DomainException('Enter a Philippine mobile number in 09XXXXXXXXX format.');
    $values['contact_number']=$contact!==''?$contact:null;
    return $values;
}

final class EBillsEnrollment
{
    public const CONSENT='I have read and accept the e-Billing Terms and Conditions set by PALECO';
    public const TERMS_SOURCE='https://paleco.net/paleco/mco_portal_login/mco_portal_main/mco_portal_ebills_terms/';
    public static function termsHtml():string
    {
        $html=file_get_contents(__DIR__ . '/../../resources/ebills-terms.html');
        if($html===false||trim($html)==='')throw new RuntimeException('Official terms unavailable.');
        return $html;
    }
    public static function termsVersion():string{return 'sha256:'.hash('sha256',self::termsHtml());}
    public function __construct(private PDO $pdo){}
    private function query(string $sql,array $parameters=[]):PDOStatement{$s=$this->pdo->prepare($sql);$s->execute($parameters);return $s;}
    public function profile(int $userId,bool $lock=false):array
    {
        if($userId<=0)throw new DomainException('Please sign in to continue.');
        $row=$this->query('SELECT id,first_name,last_name,middle_name,extension_name,email,contact_number FROM mco_portal.users WHERE id=?'.($lock?' FOR UPDATE':''),[$userId])->fetch(PDO::FETCH_ASSOC);
        if(!$row)throw new DomainException('Please sign in to continue.');return $row;
    }
    private function authorized(int $userId,string $acctNo,bool $lock=false):array
    {
        $link=$this->query("SELECT AcctNo FROM mco_portal.paleco_accounts WHERE user_id=? AND AcctNo=? AND status='active'".($lock?' FOR UPDATE':''),[$userId,$acctNo])->fetchColumn();
        if(!$link)throw new DomainException('This account is not linked to your profile.');
        $account=$this->query('SELECT AcctCode,Name,Address FROM palecxzp_pal_db.master WHERE AcctNo=?',[$link])->fetch(PDO::FETCH_ASSOC);
        if(!$account||trim((string)$account['AcctCode'])==='')throw new DomainException('The PALECO account code is unavailable.');return $account;
    }
    public function statuses(array $accounts):array
    {
        $codes=array_values(array_unique(array_filter(array_map(fn($a)=>trim((string)($a['AcctCode']??'')),$accounts))));
        if(!$codes)return [];
        $rows=$this->query('SELECT DISTINCT m.AcctCode FROM palecxzp_pal_db.master m INNER JOIN palecxzp_pal_db.ebills e ON e.AccCode=m.AcctCode WHERE m.AcctCode IN ('.implode(',',array_fill(0,count($codes),'?')).')',$codes)->fetchAll(PDO::FETCH_COLUMN);
        return array_fill_keys(array_map('trim',$rows),true);
    }
    public function details(int $userId,string $acctNo):array
    {
        $account=$this->authorized($userId,$acctNo);
        $profile=$this->profile($userId);unset($profile['id']);
        $enrolled=(bool)$this->query('SELECT AccCode FROM palecxzp_pal_db.ebills WHERE AccCode=? LIMIT 1',[$account['AcctCode']])->fetchColumn();
        $problem='';try{$this->validateEmail($profile);}catch(DomainException $e){$problem=$e->getMessage();}
        $token='';
        if(!$enrolled&&!$problem){
            $now=time();
            foreach($_SESSION['ebills_terms']??[] as $key=>$state){if(($state['expires']??0)<=$now)unset($_SESSION['ebills_terms'][$key]);}
            while(count($_SESSION['ebills_terms']??[])>=20)array_shift($_SESSION['ebills_terms']);
            $token=bin2hex(random_bytes(32));
            $_SESSION['ebills_terms'][$token]=['user'=>$userId,'account'=>$acctNo,'version'=>self::termsVersion(),'expires'=>$now+1800,'accepted_at'=>null];
        }
        return ['account'=>$account,'profile'=>$profile,'enrolled'=>$enrolled,'problem'=>$problem,'terms_token'=>$token,'terms_version'=>self::termsVersion()];
    }
    private function consentState(int $userId,string $acctNo,array $input):array
    {
        $token=is_string($input['terms_token']??null)?$input['terms_token']:'';
        $state=$_SESSION['ebills_terms'][$token]??null;
        if(!$state||$state['user']!==$userId||$state['account']!==$acctNo||$state['expires']<=time()||!hash_equals(self::termsVersion(),$state['version'])||($input['terms_version']??'')!==$state['version'])
            throw new DomainException('Please reopen enrollment and accept the current official e-Billing Terms and Conditions.');
        return $state;
    }
    public function acceptTerms(int $userId,string $acctNo,array $input,string $csrf):void
    {
        $this->csrf($userId,$input,$csrf);$this->authorized($userId,$acctNo);$this->profile($userId);
        $this->consentState($userId,$acctNo,$input);
        if(($input['consent']??'')!=='1')throw new DomainException('Please explicitly accept the official e-Billing Terms and Conditions.');
        $_SESSION['ebills_terms'][$input['terms_token']]['accepted_at']=gmdate('c');
    }
    private function validateEmail(array $profile):void
    {
        if(strlen($profile['email'])>50||!filter_var($profile['email'],FILTER_VALIDATE_EMAIL))throw new DomainException('Your registered email is not supported by the billing system. Please contact PALECO support.');
    }
    private function validateEnrollmentProfile(array $profile):void
    {
        portalProfileValues(array_replace($profile,['contact_number'=>'']));
        foreach(['first_name','last_name','middle_name','extension_name'] as $key){
            if(preg_match('/[\x{10000}-\x{10FFFF}]/u',(string)$profile[$key]))throw new DomainException('The provided name contains characters unsupported by the billing system. Please contact PALECO support.');
        }
        $this->validateEmail($profile);
    }
    private function csrf(int $userId,array $input,string $csrf):void
    {
        if($userId<=0||$csrf===''||!is_string($input['csrf_token']??null)||!hash_equals($csrf,$input['csrf_token']))throw new DomainException('Your session expired. Please refresh and try again.');
    }
    public function saveProfile(int $userId,array $input,string $csrf):void
    {
        $this->csrf($userId,$input,$csrf);
        $input['contact_number']=portalNormalizeMobileNumber($input['contact_number']??null);
        $v=portalProfileValues($input);
        $this->pdo->beginTransaction();try{
            $this->profile($userId,true);
            $this->query('UPDATE mco_portal.users SET first_name=?,last_name=?,middle_name=?,extension_name=?,contact_number=? WHERE id=?',[...array_values($v),$userId]);
            $this->pdo->commit();
        }catch(Throwable $e){if($this->pdo->inTransaction())$this->pdo->rollBack();throw $e;}
    }
    public function enroll(int $userId,string $acctNo,array $input,string $csrf):bool
    {
        $this->csrf($userId,$input,$csrf);
        $state=$this->consentState($userId,$acctNo,$input);
        if(!$state['accepted_at'])throw new DomainException('Accept the official terms before completing enrollment.');
        if(($input['consent']??'')!=='1')throw new DomainException('Consent is required to enroll in eBills.');
        $values=portalProfileValues($input);
        $contact=is_string($input['contact_number']??null)?trim($input['contact_number']):'';
        if(!preg_match('/^09[0-9]{9}$/D',$contact))throw new DomainException('Enter a Philippine mobile number in 09XXXXXXXXX format.');
        $this->pdo->beginTransaction();try{
            $profile=$this->profile($userId,true);
            $profile=array_replace($profile,$values);$this->validateEnrollmentProfile($profile);
            $account=$this->authorized($userId,$acctNo,true);
            if($this->query('SELECT AccCode FROM palecxzp_pal_db.ebills WHERE AccCode=?',[$account['AcctCode']])->fetchColumn()){$this->pdo->commit();return false;}
            try {
                $this->query('INSERT INTO palecxzp_pal_db.ebills (AccCode,DateCreated,TimeStamp,FirstName,LastName,MiddleName,ExtensionName,Email,ContactNumber) VALUES (?,NOW(),NOW(),?,?,?,?,?,?)',[$account['AcctCode'],$profile['first_name'],$profile['last_name'],$profile['middle_name'],$profile['extension_name'],$profile['email'],$contact]);
            } catch (PDOException $e) {
                if (($e->errorInfo[1]??null)===1062) {$this->pdo->rollBack();return false;}
                throw $e;
            }
            $this->query('UPDATE mco_portal.users SET first_name=?,last_name=?,middle_name=?,extension_name=?,contact_number=? WHERE id=?',[...array_values($values),$userId]);
            $audit=json_encode(['account_code'=>$account['AcctCode'],'consent_version'=>$state['version'],'terms_source'=>self::TERMS_SOURCE,'consent'=>self::CONSENT,'accepted_at'=>$state['accepted_at'],'timestamp'=>gmdate('c')],JSON_THROW_ON_ERROR);
            $this->query('INSERT INTO mco_portal.activity_logs (user_id,action,description) VALUES (?,?,?)',[$userId,'ebills_enrolled',$audit]);
            $this->pdo->commit();return true;
        }catch(Throwable $e){if($this->pdo->inTransaction())$this->pdo->rollBack();throw $e;}
    }
}

function prepareEBillsStep3(PDO $pdo,int $userId,?string $acctNo):?array
{
    if(!$acctNo)return null;
    try{
        $s=$pdo->prepare("SELECT m.AcctCode FROM mco_portal.paleco_accounts pa INNER JOIN palecxzp_pal_db.master m ON m.AcctNo=pa.AcctNo WHERE pa.user_id=? AND pa.AcctNo=? AND pa.status='active'");
        $s->execute([$userId,$acctNo]);$code=$s->fetchColumn();if($code===false){unset($_SESSION['ebills_step3_acct']);return null;}
        $token=bin2hex(random_bytes(32));$_SESSION['dashboard_account_selection_tokens'][$token]=$acctNo;
        $status=null;try{$map=(new EBillsEnrollment($pdo))->statuses([['AcctCode'=>$code]]);$status=isset($map[$code]);}catch(Throwable $e){error_log('eBills status lookup unavailable.');}
        return ['code'=>$code,'token'=>$token,'enrolled'=>$status];
    }catch(Throwable $e){error_log('eBills optional step unavailable.');return ['code'=>'code unavailable','token'=>'','enrolled'=>null];}
}
