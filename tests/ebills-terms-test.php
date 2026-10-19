<?php
require_once __DIR__ . '/../app/services/ebills-enrollment.php';
$checks=0;
function termsCheck(bool $ok,string $message):void{global $checks;$checks++;if(!$ok)throw new RuntimeException($message);}
$terms=EBillsEnrollment::termsHtml();
termsCheck(EBillsEnrollment::termsVersion()==='sha256:877128fd81faaf381ca5e7bbd35ad1c8925fd6b272202ed05048deab5921d4bf','Official snapshot changed: review provenance and consent version.');
termsCheck(substr_count($terms,'<li>')===10,'Official visible clauses were omitted or added.');
termsCheck(!preg_match('/<(?!\/?(?:p|ol|li|b)>)/i',$terms),'Terms contain unreviewed tags, attributes or scripts.');
$expected=['Statement of Account.','Payment.','Start of Electronic Billing.','Printed Bill Copy.','Cancellation.','Updating Contact Information.','System Compatibility and Blocking.','Discrepancies.','Notices.','Acknowledgments.'];
foreach($expected as $heading)termsCheck(str_contains($terms,'<b>'.$heading.'</b>'),'Missing official clause.');
termsCheck(str_contains($terms,'By accepting these EBTC terms you agree and represent'),'Concluding authority paragraph omitted.');
function dashboardEscape($value):string{return htmlspecialchars((string)$value,ENT_QUOTES,'UTF-8');}
$csrf='test-csrf';ob_start();include __DIR__ . '/../app/views/partials/ebills-ui.php';$html=ob_get_clean();
termsCheck(str_contains($html,$terms),'Modal does not display the complete official snapshot.');
termsCheck(strpos($html,'data-ebills-screen="1"')<strpos($html,'data-ebills-screen="2"'),'Terms do not precede personal information.');
termsCheck(str_contains($html,'data-ebills-screen="2" hidden')&&str_contains($html,'data-ebills-screen="3" hidden'),'Information/review exposed initially.');
termsCheck(str_contains($html,'data-ebills-consent disabled')&&!preg_match('/data-ebills-consent[^>]*\bchecked\b/',$html),'Consent is not initially unchecked/disabled.');
termsCheck(str_contains($html,'data-ebills-email readonly'),'Registered email is editable.');
termsCheck(str_contains($html,'data-ebills-terms')&&str_contains($html,'overflow-y-auto')&&str_contains($html,'tabindex="0"'),'Terms missing readable keyboard-scrollable region.');
termsCheck(str_contains($html,'dark:bg-slate-900')&&str_contains($html,'max-h-[90dvh]'),'Responsive/theme styling removed.');
termsCheck(str_contains($html,'data-ebills-summary hidden'),'Account details not hidden initially.');
termsCheck(!str_contains($html,'Official PALECO terms source')&&!str_contains($html,EBillsEnrollment::TERMS_SOURCE),'External terms link remains visible.');
termsCheck(str_contains($html,'grid grid-cols-1 gap-3 sm:grid-cols-2" data-ebills-names'),'Name grid is not responsive.');
echo "Official eBills terms/render tests passed ($checks assertions).\n";
