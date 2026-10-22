<?php
// Rendered only by the disposable CLI fixture router, never a portal controller.
if(!defined('NC_PROTOTYPE_FIXTURE')){http_response_code(404);return;}
$portalActivePage='new-connection';$portalPageTitle='Stage 1 prototype';
$portalUserDisplayName='Synthetic Applicant';$portalUserEmail=$context['email'];
$e=static fn($v)=>htmlspecialchars((string)$v,ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8');
$field=static function($path,$label,$type='text',$options=[])use($e){
    $required=(bool)preg_match('/(?:first_name|last_name|contact_number|city_municipality_code|barangay_code|street_purok_sitio|premises_classification_code|occupancy_code)$/',$path);
    $label=preg_replace('/\(optional,\s*([^)]*)\)/i','($1)',$label);
    $label=preg_replace('/\s*\(optional\)/i','',$label);
    echo '<div class="proto-field"><label for="'.$e($path).'">'.$e($label).($required?' <span class="proto-required" aria-hidden="true">*</span><span class="proto-sr"> Required</span>':'').'</label>';
    if($type==='select'){echo '<select id="'.$e($path).'" data-path="'.$e($path).'"><option value="">Choose an example</option>';foreach($options as $code=>$item)echo '<option value="'.$e($code).'">'.$e(is_array($item)?$item['name']:$item).'</option>';echo '</select>';}
    elseif($type==='textarea')echo '<textarea id="'.$e($path).'" data-path="'.$e($path).'" rows="3"></textarea>';
    else echo '<input id="'.$e($path).'" data-path="'.$e($path).'" type="'.$e($type).'" '.($type==='tel'?'inputmode="tel" autocomplete="tel"':'').'>';
    echo '<span id="'.$e($path).'-error" class="proto-error" hidden></span></div>';
};
?>
<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>Stage 1 prototype | PALECO MCO Portal</title>
<link rel="icon" href="assets/images/logo.png"><link rel="stylesheet" href="assets/css/portal-utilities.css"><link rel="stylesheet" href="assets/css/portal-shell.css"><link rel="stylesheet" href="assets/css/new-connection-prototype.css">
<link rel="stylesheet" href="assets/vendor/bootstrap-icons/bootstrap-icons.min.css">
<script>try{document.documentElement.classList.toggle('dark',localStorage.getItem('mco-theme')==='dark')}catch(e){}</script>
<script src="assets/js/theme.js" defer></script><script src="assets/js/portal-shell.js" defer></script><script src="assets/js/new-connection-prototype.js" defer></script>
</head><body class="bg-slate-50 text-slate-900 dark:bg-slate-950 dark:text-slate-100">
<?php require __DIR__.'/../partials/portal-sidebar.php'; ?>
<main class="portal-main"><?php require __DIR__.'/../partials/portal-header.php'; ?>
<div class="proto-content">
<aside class="proto-banner"><strong>Development Test Only — Do not enter real personal information.</strong> No portal application is being created. Catalog choices and acknowledgment text are unapproved examples.</aside>
<p id="expiry-warning" role="status"></p>
<h1 class="text-2xl font-bold">Applicant Information and Submission</h1>

<p><span class="proto-required" aria-hidden="true">*</span> Required fields. Unmarked fields are not required. Required labels apply only to this synthetic test. Save an incomplete valid draft at any time; review all sections before test submission. PALECO requirements remain unapproved.</p>
<p>Stage 1 of 7 · <span id="section-progress" aria-live="polite">Section 1 of 5</span></p>
<nav id="section-nav" aria-label="Stage 1 sections"><?php foreach(['Applicant Identity','Residential Address','Proposed Service Location','Service Request','Review and Acknowledgments'] as $n=>$label): ?><button type="button" data-section="<?= $n ?>"><?= $n+1 ?>. <?= $e($label) ?></button><?php endforeach ?></nav>
<div id="error-summary" role="alert" tabindex="-1" hidden></div>
<p id="save-status" role="status">Unsaved prototype demonstration.</p>
<form id="prototype-form" novalidate autocomplete="off">
<section data-step="0" class="proto-panel" aria-labelledby="identity-heading"><h2 id="identity-heading" tabindex="-1">Applicant Identity</h2>
<p>Enter the synthetic applicant name, which may differ from the signed-in account name. Confirm the prefilled details. Names are not split automatically.</p><div class="proto-grid">
<?php foreach(['first_name'=>'First name','middle_name'=>'Middle name (optional)','last_name'=>'Last name','extension_name'=>'Name extension (optional)','contact_number'=>'Mobile number'] as $name=>$label)$field('applicant.'.$name,$label,$name==='contact_number'?'tel':'text'); ?>
<div class="proto-field"><label for="prototype-email">Verified portal email (synthetic, read-only)</label><input id="prototype-email" type="email" readonly value="<?= $e($context['email']) ?>"><small>Email cannot be changed through this form.</small></div></div>
<p>Mobile formats: 09XXXXXXXXX, +639XXXXXXXXX, 639XXXXXXXXX or 9XXXXXXXXX; spaces and hyphens are accepted.</p>
<label class="proto-check"><input type="checkbox" data-path="applicant.profile_confirmed" id="applicant.profile_confirmed"> I have reviewed these application-specific identity details.</label><span class="proto-error" id="applicant.profile_confirmed-error" hidden></span>
<div class="proto-field" id="education-ui-demo"><label for="education-attainment">Educational attainment</label>
<select id="education-attainment" aria-describedby="education-ui-note"><option value="">Choose educational attainment</option>
<?php foreach(['No Formal Education','Elementary Undergraduate','Elementary Graduate','High School Undergraduate','High School Graduate','Senior High School Graduate','Technical / Vocational Graduate','College Undergraduate','College Graduate',"Postgraduate (Master's Degree)",'Postgraduate (Doctorate)','Other'] as $choice): ?><option value="<?= $e($choice) ?>"><?= $e($choice) ?></option><?php endforeach ?></select>
<div id="education-other-group" hidden><label for="education-other">Specify educational attainment</label><input id="education-other" type="text" maxlength="100" disabled></div>
<small id="education-ui-note">UI demonstration only: education is not required and is not included in saved drafts or submission under the existing v1 contract. Use synthetic details only.</small></div></section>
<?php foreach(['residential_address'=>'Residential Address','service_location'=>'Proposed Service Location'] as $section=>$label): ?>
<section data-step="<?= $section==='residential_address'?1:2 ?>" class="proto-panel" hidden><h2 tabindex="-1"><?= $e($label) ?></h2><p>Philippine address layout with a synthetic catalog. These are not official localities or eligibility determinations.</p>
<?php if($section==='service_location'): ?><label class="proto-check"><input type="checkbox" id="service_location.same_as_residential" data-path="service_location.same_as_residential"> Same as Residential Address</label><p>While selected, residential address changes are copied here. Editing this service address turns copying off.</p><?php endif ?>
<div class="proto-grid"><?php
$field($section.'.province_code','Province (where applicable)','select',$context['catalog']['provinces']);
$field($section.'.city_municipality_code','City / municipality — synthetic','select',$context['catalog']['cities']);
$field($section.'.barangay_code','Barangay — synthetic','select',$context['catalog']['barangays']);
foreach(['street_purok_sitio'=>'Street / purok / sitio','house_lot_unit_building'=>'House / lot / unit / building (optional)','additional_address'=>'Additional address (optional)','postal_code'=>'Postal code (optional, four digits)'] as $name=>$text)$field($section.'.'.$name,$text);
if($section==='service_location')$field($section.'.landmark','Landmark (optional)');
?></div><?php if($section==='service_location'): ?>
<aside class="proto-banner" id="service-geography-blocker"><strong>Palawan service-location integration pending.</strong> Province will be server-fixed to Palawan after the coverage catalog is approved. The selectors above remain synthetic fixtures, not verified Palawan locations or evidence of PALECO eligibility. No real coordinates are accepted.</aside>
<div id="service-map-placeholder" role="region" aria-labelledby="service-map-heading"><h3 id="service-map-heading">Confirmed service-location pin <span class="proto-required" aria-hidden="true">*</span><span class="proto-sr"> Required for the planned geographic contract</span></h3><p>Map unavailable: current boundaries, PALECO eligibility and map-provider approval are required. Existing synthetic v1 tests do not validate a location pin.</p><button id="confirm-service-pin" type="button" disabled>Confirm service-location pin — unavailable</button><p id="service-pin-status" role="status">No pin selected or confirmed.</p></div>
<?php endif ?></section><?php endforeach ?>
<section data-step="3" class="proto-panel" hidden><h2 tabindex="-1">Service Request</h2><p><strong>New Electric Service Connection</strong> — fixed request type.</p><p>All choices below are prototype-only and are not official PALECO classifications.</p><div class="proto-grid">
<?php $field('service_request.premises_classification_code','Premises classification — example','select',$context['catalog']['premises']);$field('service_request.occupancy_code','Occupancy — example','select',$context['catalog']['occupancies']); ?>
<div id="connection-category" hidden><?php $field('service_request.connection_type_code','Connection category (required for example shop)','select',$context['catalog']['connections']); ?></div>
<div id="occupancy-other" hidden><?php $field('service_request.occupancy_other','Other occupancy details (required when Other is selected)'); ?></div>
</div><?php $field('service_request.requested_service_details','Requested service clarification (optional, 500 characters)','textarea'); ?></section>
<section data-step="4" class="proto-panel" hidden><h2 tabindex="-1">Review and Acknowledgments</h2><p>Read-only portal summary, not an official PALECO form. Approved originals are unavailable; there is no PDF, print or real submission action.</p><div id="review-summary"></div>
<label class="proto-check"><input id="review.information_confirmed" data-path="review.information_confirmed" type="checkbox"> I have reviewed the information above.</label><span class="proto-error" id="review.information_confirmed-error" hidden></span>
<h3>Draft acknowledgment text — approval pending</h3>
<?php $notices=[
'privacy_notice'=>'I have read the New Connection privacy notice and understand how the information in this application will be used for the purposes described in that notice.',
'information_accuracy'=>'I have reviewed the information in this application and confirm that it is accurate to the best of my knowledge. I understand that I may need to correct information identified during verification.',
'application_verification'=>'I understand that submitting this application does not mean it has been approved. PALECO personnel will review the application and required supporting documents before it can proceed.',
'physical_signing'=>'I understand that I will complete and preview application information online, and PALECO personnel will print the official forms for my handwritten signatures during the office payment visit. Checking this box is an online acknowledgment, not my handwritten signature.'
];foreach($notices as $code=>$text): ?><label class="proto-check"><input type="checkbox" id="acknowledgments.<?= $e($code) ?>" data-path="acknowledgments.<?= $e($code) ?>"><span><strong>PENDING / draft only:</strong> <?= $e($text) ?></span></label><span class="proto-error" id="acknowledgments.<?= $e($code) ?>-error" hidden></span><?php endforeach ?>
<p>No approved privacy notice has been supplied. These controls demonstrate review behavior only; they do not authorize real collection or provide a handwritten/electronic signature.</p>
<button id="check-readiness" type="button">Check prototype readiness — no submission</button></section>
<div class="proto-actions"><button id="back-section" type="button">Back</button><button id="save-demo" type="button"><i class="bi bi-save" aria-hidden="true"></i> Save Draft — disposable fixture only</button><button id="next-section" type="button">Continue</button></div>
</form></div></main>
<script id="prototype-context" type="application/json"><?= json_encode(['input'=>$initial,'catalog'=>$context['catalog'],'email'=>$context['email'],'csrf'=>$csrf,'expires'=>$expires],JSON_HEX_TAG|JSON_HEX_AMP|JSON_HEX_APOS|JSON_HEX_QUOT|JSON_THROW_ON_ERROR) ?></script>
</body></html>
