<?php
require_once __DIR__.'/../../helpers/philippine-mobile.php';

/** Pure prototype contract. Context is server supplied; no persistence or I/O. */
final class NewConnectionApplicantContract
{
    public const VERSION='stage1.v1-draft-20261020';
    public const ACKS=['privacy_notice','information_accuracy','application_verification','physical_signing'];
    public static function fields(): array {
        $address=['province_code'=>32,'city_municipality_code'=>32,'barangay_code'=>32,'street_purok_sitio'=>160,'house_lot_unit_building'=>100,'additional_address'=>160,'postal_code'=>4];
        return ['applicant'=>['first_name'=>25,'middle_name'=>25,'last_name'=>25,'extension_name'=>25,'contact_number'=>64,'profile_confirmed'=>false],
            'residential_address'=>$address,'service_location'=>$address+['landmark'=>200,'same_as_residential'=>false],
            'service_request'=>['connection_type_code'=>32,'premises_classification_code'=>32,'occupancy_code'=>32,'occupancy_other'=>100,'requested_service_details'=>500],
            'review'=>['information_confirmed'=>false],'acknowledgments'=>array_fill_keys(self::ACKS,false)];
    }
    public static function emptyInput(): array {
        $result=[];foreach(self::fields() as $section=>$fields)foreach($fields as $name=>$limit)$result[$section][$name]=$limit===false?false:null;
        return $result;
    }
    public static function validate(string $json,array $context,bool $complete=false): array {
        $errors=[];$values=self::emptyInput();
        $fail=static function(string $path,string $message)use(&$errors){$errors[$path]=$message;};
        if(strlen($json)>65536)return ['valid'=>false,'ready'=>false,'errors'=>['form'=>'Request exceeds 64 KiB.'],'snapshot'=>null,'input'=>$values];
        try{$object=json_decode($json,false,32,JSON_THROW_ON_ERROR);}catch(Throwable $e){$object=null;}
        if(!$object instanceof stdClass)return ['valid'=>false,'ready'=>false,'errors'=>['form'=>'Provide a JSON object.'],'snapshot'=>null,'input'=>$values];
        $schema=self::fields();
        foreach(get_object_vars($object) as $section=>$data){
            if(!isset($schema[$section])){$fail($section,'Unknown or server-owned section.');continue;}
            if(!$data instanceof stdClass){$fail($section,'Provide an object.');continue;}
            foreach(get_object_vars($data) as $name=>$value){
                $path=$section.'.'.$name;
                if(!array_key_exists($name,$schema[$section])){$fail($path,'Unknown or server-owned field.');continue;}
                $limit=$schema[$section][$name];
                if($limit===false){if(!is_bool($value))$fail($path,'Use a JSON boolean.');else $values[$section][$name]=$value;continue;}
                if($value===null)continue;
                if(!is_string($value)||!mb_check_encoding($value,'UTF-8')){$fail($path,'Enter valid text.');continue;}
                $multiline=$name==='requested_service_details';
                $value=str_replace(["\r\n","\r"],"\n",$value);
                if(preg_match($multiline?'/[\x00-\x09\x0B-\x1F\x7F]/':'/[\x00-\x1F\x7F]/',$value)){$fail($path,'Control characters are not allowed.');continue;}
                $value=trim($value);
                if(mb_strlen($value)>$limit){$fail($path,"Use at most $limit characters.");continue;}
                $values[$section][$name]=$value===''?null:$value;
            }
        }
        $mobile=$values['applicant']['contact_number'];
        try{$values['applicant']['contact_number']=portalNormalizeMobileNumber($mobile);}catch(DomainException $e){$fail('applicant.contact_number',$e->getMessage());}
        if($values['service_location']['same_as_residential'])foreach(array_keys($schema['residential_address']) as $field)$values['service_location'][$field]=$values['residential_address'][$field];
        $snapshot=$values;unset($snapshot['acknowledgments']);
        foreach(['residential_address','service_location'] as $section){
            $a=&$snapshot[$section];$city=$a['city_municipality_code'];$barangay=$a['barangay_code'];$province=$a['province_code'];
            $catalog=$context['catalog'];
            if($province!==null&&!isset($catalog['provinces'][$province]))$fail($section.'.province_code','Select a synthetic province.');
            if($city!==null&&!isset($catalog['cities'][$city]))$fail($section.'.city_municipality_code','Select a synthetic city/municipality.');
            if($city!==null&&isset($catalog['cities'][$city])&&$catalog['cities'][$city]['province']!==$province)$fail($section.'.province_code','Province does not match the selected city.');
            if($barangay!==null&&(!isset($catalog['barangays'][$barangay])||$catalog['barangays'][$barangay]['city']!==$city))$fail($section.'.barangay_code','Barangay must belong to the selected city.');
            if($a['postal_code']!==null&&!preg_match('/^[0-9]{4}$/D',$a['postal_code']))$fail($section.'.postal_code','Use exactly four digits.');
            $a['country_code']='PH';$a['province_name']=$catalog['provinces'][$province]['name']??null;
            $a['city_municipality_name']=$catalog['cities'][$city]['name']??null;$a['barangay_name']=$catalog['barangays'][$barangay]['name']??null;unset($a);
        }
        $request=&$values['service_request'];
        foreach(['premises_classification_code'=>'premises','occupancy_code'=>'occupancies','connection_type_code'=>'connections'] as $field=>$catalogKey)if($request[$field]!==null&&!isset($context['catalog'][$catalogKey][$request[$field]]))$fail('service_request.'.$field,'Select a prototype-only option.');
        $rules=$context['catalog']['rules']??['connection_categories_by_premises'=>['shop'=>array_keys($context['catalog']['connections'])],'occupancy_other_codes'=>['other']];
        $needsOther=in_array($request['occupancy_code'],$rules['occupancy_other_codes'],true);
        $categories=$rules['connection_categories_by_premises'][$request['premises_classification_code']]??[];
        if(!$needsOther&&$request['occupancy_other']!==null)$fail('service_request.occupancy_other','Other details do not apply to the selected occupancy.');
        if($request['connection_type_code']!==null&&!in_array($request['connection_type_code'],$categories,true))$fail('service_request.connection_type_code','Connection category does not match the selected premises.');
        if(isset($context['catalog']['eligibility']))foreach(['residential_address','service_location'] as $section){
            $code=$values[$section]['barangay_code'];
            // Residency is not service-area eligibility; apply eligibility only to proposed supply.
            if($section==='service_location'&&$code!==null&&($context['catalog']['eligibility'][$code]??false)!==true)$fail($section.'.barangay_code','Proposed service location is not eligible in this catalog.');
        }
        $snapshot['service_request']['request_kind']='new_service';
        if(!is_string($context['email']??null)||strlen($context['email'])>254||!filter_var($context['email'],FILTER_VALIDATE_EMAIL))$fail('form','Synthetic server email context is invalid.');
        $snapshot['applicant']['email']=$context['email'];$snapshot['contract_version']=self::VERSION;
        $snapshot['catalog_versions']=$context['catalog']['versions']??['localities'=>$context['catalog']['version'],'service_requests'=>$context['catalog']['version']];
        $required=['applicant.first_name','applicant.last_name','applicant.contact_number','service_request.premises_classification_code','service_request.occupancy_code'];
        foreach(['residential_address','service_location'] as $section)foreach(['city_municipality_code','barangay_code','street_purok_sitio'] as $field)$required[]=$section.'.'.$field;
        if($needsOther)$required[]='service_request.occupancy_other';
        if($categories!==[])$required[]='service_request.connection_type_code';
        $missing=[];foreach($required as $path){[$section,$field]=explode('.',$path);if($snapshot[$section][$field]===null)$missing[$path]='Complete this field before a prototype readiness check.';}
        foreach(['applicant.profile_confirmed','review.information_confirmed'] as $path){[$section,$field]=explode('.',$path);if(!$values[$section][$field])$missing[$path]='Review and confirm this information.';}
        foreach(self::ACKS as $code)if(!$values['acknowledgments'][$code])$missing['acknowledgments.'.$code]='Review this draft acknowledgment.';
        $ready=$errors===[]&&$missing===[];
        if($complete)$errors+=$missing;
        $canonical=self::canonical($snapshot);
        if(strlen($canonical)>32768)$fail('form','Snapshot exceeds 32 KiB.');
        return ['valid'=>$errors===[],'ready'=>$ready&&$errors===[],'errors'=>$errors,'snapshot'=>$snapshot,'input'=>$values,'canonical'=>$canonical,'sha256'=>hash('sha256',$canonical),'approval'=>'prototype_only'];
    }
    public static function canonical(array $value): string {
        $sort=static function(array $a)use(&$sort):array{ksort($a,SORT_STRING);foreach($a as &$v)if(is_array($v))$v=$sort($v);return $a;};
        return json_encode($sort($value),JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR);
    }
}
