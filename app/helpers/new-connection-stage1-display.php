<?php
function newConnectionStage1DisplayAvailable(array $server): bool {
    $config=require __DIR__.'/../../config/new-connection-stage1-display.php';
    return $config['enabled'] && $config['development']
        && in_array($server['REMOTE_ADDR']??'', ['127.0.0.1','::1'],true)
        && in_array($server['SERVER_ADDR']??'', ['127.0.0.1','::1'],true)
        && !isset($server['HTTP_FORWARDED']) && !isset($server['HTTP_X_FORWARDED_FOR']) && !isset($server['HTTP_X_FORWARDED_HOST'])
        && preg_match('/^(?:localhost|127\.0\.0\.1|\[::1\])(?::[0-9]{1,5})?$/Di',$server['HTTP_HOST']??'')===1;
}

/** Render the existing prototype, replacing every control server-side before output. */
function newConnectionStage1ReadOnly(): string {
    require_once __DIR__.'/../services/new-connection/applicant-contract.php';
    if (!defined('NC_PROTOTYPE_FIXTURE')) define('NC_PROTOTYPE_FIXTURE',true);
    $context=['email'=>'display-only@example.invalid','catalog'=>array_fill_keys(['provinces','cities','barangays','premises','occupancies','connections'],[])];
    $initial=NewConnectionApplicantContract::emptyInput();$csrf='';$expires=0;
    ob_start(); require __DIR__.'/../views/pages/new-connection-prototype.php';$html=ob_get_clean();
    $dom=new DOMDocument();$previous=libxml_use_internal_errors(true);
    try {$dom->loadHTML('<?xml encoding="utf-8" ?>'.$html);}
    finally {libxml_clear_errors();libxml_use_internal_errors($previous);}
    $xpath=new DOMXPath($dom);
    // Explicit display-only rules: never rewrite shared interactive source or declaration text globally.
    foreach(iterator_to_array($xpath->query('//header//h1|//title')) as $title)$title->textContent='Stage 1 — Applicant Information';
    $sectionNotes=[
        '0'=>'Provisional applicant identity fields, separate from the portal account identity. No profile details are loaded.',
        '1'=>'Provisional residential address structure. Locality choices require an approved authoritative catalog.',
        '2'=>'Provisional service location structure. Same-address copying is a proposed option, unavailable in this display.',
        '3'=>'Provisional New Electric Service Connection classifications. Official premises, occupancy and conditional categories await approval.',
        '4'=>'Provisional review and declaration structure only. No approved privacy notice or acknowledgment is represented; no consent is recorded.'
    ];
    foreach($sectionNotes as $step=>$note){
        $section=$xpath->query('//*[@data-step="'.$step.'"]')->item(0);
        foreach(iterator_to_array($xpath->query('./p',$section)) as $paragraph)$section->removeChild($paragraph);
        $section->insertBefore($dom->createElement('p',$note),$section->getElementsByTagName('h2')->item(0)->nextSibling);
    }
    $confirmationLabels=[
        'applicant.profile_confirmed'=>'Applicant identity confirmation — provisional',
        'service_location.same_as_residential'=>'Same as residential address — provisional option',
        'review.information_confirmed'=>'Information review confirmation — provisional',
        'acknowledgments.privacy_notice'=>'Privacy notice acknowledgment — approval pending',
        'acknowledgments.information_accuracy'=>'Information accuracy declaration — approval pending',
        'acknowledgments.application_verification'=>'Application and document verification acknowledgment — approval pending',
        'acknowledgments.physical_signing'=>'Offline printing, handwritten signing and payment acknowledgment — approval pending'
    ];
    foreach(iterator_to_array($xpath->query('//label[contains(@class,"proto-check")]')) as $label){
        $input=$label->getElementsByTagName('input')->item(0);
        $path=$input?$input->getAttribute('data-path'):'';
        $label->textContent=$confirmationLabels[$path]??'Provisional confirmation field';
    }
    foreach(iterator_to_array($xpath->query('//*[@data-step]//label[not(contains(@class,"proto-check"))]')) as $label)$label->appendChild($dom->createTextNode(' — provisional'));
    foreach(iterator_to_array($xpath->query('//*[@id="connection-category" or @id="occupancy-other"]')) as $conditional)$conditional->removeAttribute('hidden');
    foreach(iterator_to_array($xpath->query('//*[@data-step]//small')) as $hint)$hint->parentNode->removeChild($hint);
    foreach(iterator_to_array($xpath->query('//script|//form//button|//nav[@id="section-nav"]|//*[@id="expiry-warning"]|//*[@id="save-status"]|//*[@id="error-summary"]')) as $node) $node->parentNode->removeChild($node);
    foreach(iterator_to_array($xpath->query('//input|//select|//textarea')) as $node) {
        $text=$dom->createElement('span','Display only — no information collected');
        $node->parentNode->replaceChild($text,$node);
    }
    foreach(iterator_to_array($xpath->query('//*[@data-step]')) as $node)$node->removeAttribute('hidden');
    foreach(iterator_to_array($xpath->query('//form')) as $node){while($node->firstChild)$node->parentNode->insertBefore($node->firstChild,$node);$node->parentNode->removeChild($node);}
    // Shell scripts only: no editor, API bridge, proof or persistence JavaScript.
    foreach(['assets/js/theme.js','assets/js/portal-shell.js'] as $src){$script=$dom->createElement('script');$script->setAttribute('src',$src);$script->setAttribute('defer','');$dom->getElementsByTagName('body')->item(0)->appendChild($script);}
    $banner=$xpath->query('//*[contains(@class,"proto-banner")]')->item(0);
    $banner->textContent='Stage 1 Read-Only Development Display — provisional fields only. No data entry, saving or submission. Do not enter real personal information.';
    $heading=$xpath->query('//*[contains(@class,"proto-content")]//h1')->item(0);
    $heading->textContent='Stage 1 — Applicant Information';
    $guidance=$heading->nextSibling;while($guidance&&$guidance->nodeName!=='p')$guidance=$guidance->nextSibling;
    if($guidance)$guidance->textContent='Provisional field labels and draft declarations are shown for review. No applicant data is loaded or collected.';
    $progress=$xpath->query('//*[@id="section-progress"]')->item(0);
    if($progress)$progress->parentNode->parentNode->removeChild($progress->parentNode);
    foreach(iterator_to_array($xpath->query('//label[@for]')) as $label)$label->removeAttribute('for');
    return $dom->saveHTML();
}
