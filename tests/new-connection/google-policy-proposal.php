<?php
// TEST MODEL ONLY. Unapproved recommendation; not a provider validator or deployable auth service.
if(PHP_SAPI!=='cli'){http_response_code(404);exit;}
function proposedGoogleDecision(array $provider,?array $portal,?array $subjectOwner=null,bool $freshPortalProof=false,string $purpose='login'): string
{
    if(!is_string($provider['id']??null)||$provider['id']===''||!is_string($provider['email']??null)||!filter_var($provider['email'],FILTER_VALIDATE_EMAIL)||($provider['verified_email']??null)!==true)return 'deny_provider';
    if(!in_array($purpose,['login','link'],true))return 'deny_purpose';
    if($subjectOwner!==null){
        if($portal!==null&&$portal['id']!==$subjectOwner['id'])return 'binding_conflict';
        $portal=$subjectOwner;
        if(($portal['google_id']??null)!==$provider['id'])return 'binding_conflict';
    }
    if($portal===null)return 'portal_enrollment_required';
    if(($portal['status']??null)!=='active')return 'deny_status';
    if(empty($portal['email_verified_at']))return 'portal_verification_required';
    if(!empty($portal['google_id'])&&$portal['google_id']!==$provider['id'])return 'binding_conflict';
    if(strtolower(trim((string)($portal['email']??'')))!==strtolower(trim($provider['email'])))return 'portal_email_review_required';
    if(!empty($portal['google_id'])){
        return 'authenticate';
    }
    // Neither Gmail, hd nor matching email alone is authorization to attach a provider identity.
    if($purpose!=='link'||!$freshPortalProof)return 'explicit_portal_link_required';
    return 'link_after_portal_proof';
}
function proposedGooglePipeline(bool $validatedState,bool $validatedExchange,array $provider,?array $portal): string
{
    if(!$validatedState||!$validatedExchange)return 'deny_oauth_evidence';
    return proposedGoogleDecision($provider,$portal);
}
