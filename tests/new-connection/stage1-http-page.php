<?php
if(!defined('NC_PROTOTYPE_FIXTURE')){http_response_code(404);return;}
ob_start();require __DIR__.'/app/views/pages/new-connection-prototype.php';$html=ob_get_clean();
$html=str_replace('</head>','<link rel="stylesheet" href="/assets/css/stage1-http.css"></head>',$html);
$html=str_replace('<script src="assets/js/new-connection-prototype.js" defer></script>','<script src="/assets/js/stage1-http-ui.js" defer></script><script src="assets/js/new-connection-prototype.js" defer></script>',$html);
$html=str_replace('</form></div></main>','</form><section id="submission-result" class="proto-panel" hidden tabindex="-1"></section><dialog id="fixture-error"><h2>Test request needs attention</h2><p id="fixture-error-text"></p><button id="close-fixture-error" type="button">Close</button><a id="fixture-reauth" hidden>Reauthenticate synthetic session</a></dialog></div></main>',$html);
$html=str_replace('<div id="error-summary"','<div class="proto-actions"><button id="refresh-catalog" type="button" hidden>Refresh published test catalog</button><button id="find-applications" type="button">Find my test applications</button><div id="found-applications" tabindex="-1"></div><button id="create-application" type="button">Create test application</button><button id="resume-draft" type="button">Resume saved draft</button><button id="request-review" type="button">Review saved information</button><button id="retry-command" type="button" hidden>Retry exact create/save command</button><button id="retry-submission" type="button" hidden>Retry exact test submission</button></div><div id="error-summary"',$html);
$html=str_replace('Check prototype readiness — no submission','Submit Application — synthetic test only',$html);
$html=str_replace('Draft acknowledgment text — approval pending','Published synthetic test acknowledgments',$html);
$html=str_replace('<strong>PENDING / draft only:</strong>','<strong>TEST ONLY:</strong>',$html);
$html=str_replace('<p>Read-only portal summary, not an official PALECO form. Approved originals are unavailable; there is no PDF, print or real submission action.</p>','<p>Private review of the saved synthetic snapshot. Stage 1 submission is not application approval. No official form, printing or physical signature is produced.</p>',$html);
$html=str_replace('Catalog choices and acknowledgment text are unapproved examples.','All writes target a disposable test database. Notices are synthetic automated-test publications.',$html);
$html=str_replace('No portal application is being created.','No actual portal application is being created.',$html);
$html=str_replace('"csrf":', '"fixtureReference":'.json_encode($reference).',"fixtureIntegration":true,"fixtureGoverned":'.json_encode(!empty($cfg['governed'])).',"csrf":',$html);
echo str_replace('<script>', '<script nonce="'.htmlspecialchars($nonce,ENT_QUOTES).'">',$html);
