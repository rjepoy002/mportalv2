<?php
/** Pure synthetic v2 contract. No connection factory, workflow mutation or production adapter. */
final class NewConnectionApplicantContractV2
{
    public const VERSION='stage1.v2-synthetic-20261022';
    public const EDUCATION=['none'=>'No Formal Education','elementary_undergraduate'=>'Elementary Undergraduate','elementary_graduate'=>'Elementary Graduate','high_school_undergraduate'=>'High School Undergraduate','high_school_graduate'=>'High School Graduate','senior_high_school_graduate'=>'Senior High School Graduate','technical_vocational_graduate'=>'Technical / Vocational Graduate','college_undergraduate'=>'College Undergraduate','college_graduate'=>'College Graduate','masters'=>"Postgraduate (Master's Degree)",'doctorate'=>'Postgraduate (Doctorate)','other'=>'Other'];
    public static function fields():array {
        return ['personal'=>['date_of_birth'=>10,'gender'=>32,'civil_status'=>16],
            'spouse'=>['first_name'=>25,'middle_name'=>25,'last_name'=>25],
            'additional'=>['education_code'=>40,'education_other'=>100,'occupation'=>100,'income_source'=>160,'annual_income_php'=>13,'dependent_count'=>'integer','religion'=>100,'social_affiliation'=>160,'tin'=>32],
            'service_location'=>['city_municipality_psgc'=>32,'barangay_psgc'=>32,'street_purok_sitio'=>160,'house_lot_building'=>100,'postal_code'=>4,'landmark_directions'=>200,'pin'=>['latitude'=>'number','longitude'=>'number','confirmed'=>'boolean']],
            'confirmations'=>['profile_fingerprint'=>64,'profile_confirmed'=>'boolean','information_confirmed'=>'boolean']];
    }
    public static function emptyInput():array {
        $empty=static function(array $schema)use(&$empty):array{$result=[];foreach($schema as $key=>$type)$result[$key]=is_array($type)?$empty($type):($type==='boolean'?false:null);return $result;};
        return $empty(self::fields());
    }
    public static function profileFingerprint(array $context):string {return hash('sha256',self::canonical($context['profile']));}
    public static function canonical(array $value):string {
        $sort=static function(array $a)use(&$sort):array{ksort($a,SORT_STRING);foreach($a as &$v)if(is_array($v))$v=$sort($v);return $a;};
        return json_encode($sort($value),JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR);
    }
    public static function validate(string $json,array $context,bool $complete=false):array {
        if(($context['synthetic_only']??false)!==true)throw new LogicException('Synthetic context required');
        $errors=[];$input=self::emptyInput();$fail=static function(string $path,string $message)use(&$errors):void{$errors[$path]=$message;};
        $root=null;if(strlen($json)>65536)$fail('form','Input exceeds 64 KiB.');else try{$root=json_decode($json,false,16,JSON_THROW_ON_ERROR);}catch(Throwable $e){$fail('form','Provide valid JSON.');}
        $parse=static function($object,array $schema,array &$out,string $prefix='')use(&$parse,$fail):void{
            if(!$object instanceof stdClass){$fail($prefix?:'form','Provide an object.');return;}
            foreach(get_object_vars($object) as $key=>$value){$path=$prefix===''?$key:$prefix.'.'.$key;if(!array_key_exists($key,$schema)){$fail($path,'Unknown or server-owned field.');continue;}$type=$schema[$key];
                if(is_array($type)){$parse($value,$type,$out[$key],$path);continue;}
                if($type==='boolean'){if(!is_bool($value))$fail($path,'Use a boolean.');else $out[$key]=$value;continue;}
                if($value===null)continue;
                if($type==='integer'){if(!is_int($value)||$value<0||$value>999)$fail($path,'Use a whole number from 0 to 999.');else $out[$key]=$value;continue;}
                if($type==='number'){if((!is_float($value)&&!is_int($value))||!is_finite((float)$value))$fail($path,'Use a finite numeric coordinate.');else $out[$key]=$value;continue;}
                if(!is_string($value)||!mb_check_encoding($value,'UTF-8')){$fail($path,'Use valid text.');continue;}
                $value=trim(str_replace(["\r\n","\r"],"\n",$value));
                if(mb_strlen($value)>$type||preg_match($key==='landmark_directions'?'/[\x00-\x09\x0B-\x1F\x7F]/':'/[\x00-\x1F\x7F]/',$value)){$fail($path,'Text is too long or contains control characters.');continue;}
                $out[$key]=$value===''?null:$value;
            }
        };
        $parse($root,self::fields(),$input);
        $personal=&$input['personal'];$age=null;$today=$context['as_of'];
        $date=$personal['date_of_birth'];if($date!==null){$dob=DateTimeImmutable::createFromFormat('!Y-m-d',$date,new DateTimeZone('Asia/Manila'));if(!$dob||$dob->format('Y-m-d')!==$date||$date>$today||substr($date,0,4)==='0000')$fail('personal.date_of_birth','Use a valid date of birth, not in the future.');else{$age=(int)substr($today,0,4)-(int)$dob->format('Y');if(substr($today,5)<$dob->format('m-d'))$age--;}}
        foreach(['gender'=>['male','female','prefer_not_to_say'],'civil_status'=>['single','married','widowed','separated']] as $key=>$options)if($personal[$key]!==null&&!in_array($personal[$key],$options,true))$fail('personal.'.$key,'Select a listed option.');
        if($personal['civil_status']!=='married')$input['spouse']=array_fill_keys(array_keys($input['spouse']),null);
        $additional=&$input['additional'];if($additional['education_code']!==null&&!array_key_exists($additional['education_code'],self::EDUCATION))$fail('additional.education_code','Select a listed education option.');
        if($additional['education_code']!=='other')$additional['education_other']=null;
        if($additional['annual_income_php']!==null){if(!preg_match('/^(?:0|[1-9][0-9]{0,9})(?:\.[0-9]{1,2})?$/D',$additional['annual_income_php']))$fail('additional.annual_income_php','Use a nonnegative decimal amount, at most two decimal places.');else{$parts=explode('.',$additional['annual_income_php']);$additional['annual_income_php']=$parts[0].'.'.str_pad($parts[1]??'',2,'0');}}
        if($additional['tin']!==null&&!preg_match('/^[0-9 -]+$/D',$additional['tin']))$fail('additional.tin','Use digits, spaces or hyphens; no verification is implied.');
        foreach(['religion','social_affiliation','income_source','annual_income_php','dependent_count','tin','occupation','education_code','education_other'] as $key)if(!($context['sensitive_allowed']??false)&&$additional[$key]!==null)$fail('additional.'.$key,'Sensitive fields are not authorized in this context.');
        $location=&$input['service_location'];$catalog=$context['catalog'];$city=$location['city_municipality_psgc'];$barangay=$location['barangay_psgc'];
        if($city!==null&&!isset($catalog['cities'][$city]))$fail('service_location.city_municipality_psgc','Select a synthetic fixture locality.');
        if($barangay!==null&&(!isset($catalog['barangays'][$barangay])||$catalog['barangays'][$barangay]['city']!==$city))$fail('service_location.barangay_psgc','Barangay must belong to the selected fixture locality.');
        if($location['postal_code']!==null&&!preg_match('/^[0-9]{4}$/D',$location['postal_code']))$fail('service_location.postal_code','Use four digits.');
        $pin=&$location['pin'];$lat=$pin['latitude'];$lng=$pin['longitude'];$verified=false;
        if($lat!==null&&($lat < -90||$lat > 90))$fail('service_location.pin.latitude','Latitude out of range.');
        if($lng!==null&&($lng < -180||$lng > 180))$fail('service_location.pin.longitude','Longitude out of range.');
        if(($lat===null)!==($lng===null))$fail('service_location.pin','Supply both coordinates or leave both empty.');
        if($lat!==null&&$lng!==null){$bounds=$catalog['barangays'][$barangay]['bounds']??null;$verified=$bounds&&$lat>=$bounds[0]&&$lat<=$bounds[2]&&$lng>=$bounds[1]&&$lng<=$bounds[3];if(!$verified)$fail('service_location.pin','Point does not belong to the selected synthetic fixture box.');}
        if($pin['confirmed']&&!$verified)$fail('service_location.pin.confirmed','A matching synthetic point is required.');
        $fingerprint=self::profileFingerprint($context);if($input['confirmations']['profile_confirmed']&&!hash_equals($fingerprint,$input['confirmations']['profile_fingerprint']??'')){$input['confirmations']['profile_confirmed']=false;$input['confirmations']['information_confirmed']=false;$fail('confirmations.profile_confirmed','Profile changed. Refresh and confirm again.');}
        $missing=[];foreach(['personal.date_of_birth','personal.gender','personal.civil_status','service_location.city_municipality_psgc','service_location.barangay_psgc','service_location.street_purok_sitio'] as $path){[$a,$b]=explode('.',$path);if($input[$a][$b]===null)$missing[$path]='Required before synthetic readiness review.';}
        if($personal['civil_status']==='married')foreach(['first_name','last_name'] as $key)if($input['spouse'][$key]===null)$missing['spouse.'.$key]='Required when Married.';
        if(!$pin['confirmed'])$missing['service_location.pin.confirmed']='Confirm the synthetic point.';
        foreach(['profile_confirmed','information_confirmed'] as $key)if(!$input['confirmations'][$key])$missing['confirmations.'.$key]='Review and confirm.';
        $profile=$context['profile'];if(($profile['status']??'')!=='active'||empty($profile['email_verified_at']))$fail('identity','Active verified synthetic profile required.');
        foreach(['first_name','last_name','contact_number','email'] as $key)if(empty($profile[$key]))$missing['identity.'.$key]='Complete the synthetic profile outside this form.';
        $ready=$errors===[]&&$missing===[];if($complete)$errors+=$missing;
        $snapshot=['contract_version'=>self::VERSION,'identity'=>$profile+['profile_fingerprint'=>$fingerprint],'personal'=>$personal+['age_years'=>$age,'age_as_of'=>$today],'spouse'=>$input['spouse'],'additional'=>$additional,'service_location'=>$location+['province_context'=>'Palawan','catalog_version'=>$catalog['version'],'geometry_version'=>$catalog['geometry_version'],'eligibility_version'=>$catalog['eligibility_version'],'eligibility'=>'synthetic_only_not_service_approval'],'confirmations'=>$input['confirmations']];
        $canonical=self::canonical($snapshot);if(strlen($canonical)>32768)$fail('form','Snapshot exceeds 32 KiB.');
        return ['version'=>self::VERSION,'valid'=>$errors===[],'ready'=>$ready&&$errors===[],'errors'=>$errors,'input'=>$input,'snapshot'=>$snapshot,'sha256'=>hash('sha256',$canonical),'profile_fingerprint'=>$fingerprint];
    }
}
