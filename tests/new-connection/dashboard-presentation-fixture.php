<?php
// Used only in the copied loopback test tree. Never invoke dashboard controller.
if (!is_file(dirname(__DIR__,2).'/browser-config.json')) { http_response_code(404); return; }
$fixtureRoot=dirname(__DIR__,2);
require $fixtureRoot.'/app/middleware/account-context.php';
require_once $fixtureRoot.'/app/services/ebills-enrollment.php';
$controller=file_get_contents($fixtureRoot.'/app/controllers/dashboard.php');
$start=strpos($controller,'function formatBillMonth');
$end=strpos($controller,'function syncLegacyEbillsAccounts');
// Only pure display function definitions, not bootstrap/actions/synchronization.
eval(substr($controller,$start,$end-$start));
$nav=['dashboard'=>'Dashboard','bills'=>'My Bills','account-settings'=>'Account & Settings'];
$page=($_GET['page']??'')==='account-settings'?'account-settings':'dashboard';
$userDisplayName='Synthetic Applicant';$userEmail='synthetic@example.invalid';$flash=null;
$selectedAccount=['id'=>1,'user_id'=>7,'AcctNo'=>'SYNTHETIC','AcctCode'=>'1234567890','Name'=>'SYNTHETIC ACCOUNT',
    'Address'=>'Synthetic test address','Status'=>'A','is_primary'=>1,'account_nickname'=>null];
$linkedAccounts=[$selectedAccount];$accountSelectionTokens=['SYNTHETIC'=>'synthetic-selection'];
$csrf=dashboardCsrfToken();$ebillsStatus=[];$ebillsStep3=null;$accountLinkPending=null;$accountLinkFormError=null;
$accountLinker=new class { public function enabled(): bool { return true; } };
$portalProfile=['first_name'=>'Synthetic','last_name'=>'Applicant','middle_name'=>null,'extension_name'=>null,'contact_number'=>'09123456789'];
$billsTab='unpaid';$unpaidBillsError=false;$unpaidBillSummary=['total_due'=>0,'unpaid_count'=>0,'overdue_count'=>0];
$latestBill=null;$consumptionHistory=[['BillMonth'=>'202601','KWH'=>100],['BillMonth'=>'202602','KWH'=>120]];
$billHistory=[];$unpaidBills=[];$soaMonth=null;$isDevelopmentMode=false;$chartLabels=['JAN 2026','FEB 2026'];$chartValues=[100,120];
require $fixtureRoot.'/app/views/pages/dashboard.php';
