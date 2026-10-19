<?php

// Pure input tests do not require a PHP session or a writable session directory.
require_once __DIR__ . '/../app/services/account-linking.php';

function accountLinkAssert(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

accountLinkAssert(normalizePalecoAccCode('12-3456-7890') === '1234567890', 'Formatted AccCode was not accepted.');
accountLinkAssert(normalizePalecoAccCode(' 1234567890 ') === '1234567890', 'Unformatted AccCode was not canonicalized.');
accountLinkAssert(normalizePalecoAccCode('ab-1234-5678') === 'AB12345678', 'Alphabetic AccCode was not uppercased.');
accountLinkAssert(normalizePalecoAccCode('12_3456_7890') === null, 'Unsupported AccCode separators were accepted.');
accountLinkAssert(normalizePalecoAccCode('12-345-67890') === null, 'Malformed AccCode separator placement was accepted.');
accountLinkAssert(normalizePalecoAccCode('123') === null, 'Malformed AccCode was accepted.');
accountLinkAssert(normalizePalecoMeterSerial(' 00123A ') === '00123A', 'Meter serial leading zeroes were not preserved.');
accountLinkAssert(normalizePalecoMeterSerial("meter\nserial") === null, 'Meter serial control characters were accepted.');
accountLinkAssert(normalizePalecoMeterSerial(str_repeat('1', 21)) === null, 'Oversized meter serial was accepted.');

$verificationSql = palecoAccountVerificationSql();
accountLinkAssert(str_contains($verificationSql, "REPLACE(TRIM(AcctCode), '-', '') = :acct_code"), 'Verification query does not map normalized input to master.AcctCode.');
accountLinkAssert(str_contains($verificationSql, 'TRIM(MeterSerial) = :meter_serial'), 'Verification query does not require MeterSerial on the same master row.');

// These expectations replace the old development-only throttle and cross-user ban.
foreach (['1250' => '1250.00', '1250.0' => '1250.00', '1,250.00' => '1250.00', '₱1,250.00' => '1250.00', '0' => '0.00', '0001.2' => '1.20', '99999999999999999.99' => '99999999999999999.99'] as $input => $expected) {
    accountLinkAssert(normalizePalecoBillAmount((string) $input) === $expected, 'Valid currency normalization failed.');
}
foreach (['-1', '+1', '1e3', '1.001', '1,25.00', '12,34,567', '1,250,00', '1 250', '.50', 'NaN', '100000000000000000', '', '₱', '1.00x', "1\n2", "1250\n", "\x001250", str_repeat('1', 81)] as $input) {
    accountLinkAssert(normalizePalecoBillAmount($input) === null, 'Malformed currency was accepted.');
}
foreach (['202609', '202601', '202612'] as $month) accountLinkAssert(palecoLinkMonthValid($month), 'Valid month rejected.');
foreach (['202600', '202613', '20261', '202609x', '000001', "202609\n"] as $month) accountLinkAssert(!palecoLinkMonthValid($month), 'Invalid month accepted.');
accountLinkAssert(palecoLinkMonthLabel('202609') === 'September 2026', 'Month label incorrect.');
putenv('PALECO_LINK_BILLING_ENABLED');
putenv('PALECO_LINK_POLICY_APPROVAL');
$originalServer = $_SERVER;
$originalEnvironment = [];
foreach (['APP_ENV', 'MCO_APP_ENV', 'PALECO_LINK_LOCAL_TESTING'] as $key) {
    $originalEnvironment[$key] = getenv($key);
    putenv($key);
}
accountLinkAssert(!(require __DIR__ . '/../config/account-linking.php')['enabled'], 'Feature must default to disabled.');
putenv('PALECO_LINK_BILLING_ENABLED=1');
accountLinkAssert(!(require __DIR__ . '/../config/account-linking.php')['enabled'], 'Approval reference is required.');
putenv('PALECO_LINK_POLICY_APPROVAL=local-test-only');
accountLinkAssert((require __DIR__ . '/../config/account-linking.php')['enabled'], 'Approved server configuration was ignored.');
putenv('PALECO_LINK_BILLING_ENABLED');
putenv('PALECO_LINK_POLICY_APPROVAL');
putenv('PALECO_LINK_LOCAL_TESTING=1');
putenv('MCO_APP_ENV=development');
$_SERVER['SERVER_ADDR'] = '127.0.0.1';
$_SERVER['REMOTE_ADDR'] = '127.0.0.1';
accountLinkAssert((require __DIR__ . '/../config/account-linking.php')['enabled'], 'Explicit loopback development opt-in failed.');
$_SERVER['REMOTE_ADDR'] = '192.0.2.1';
$_SERVER['HTTP_X_FORWARDED_FOR'] = '127.0.0.1';
$_SERVER['HTTP_HOST'] = 'localhost';
accountLinkAssert(!(require __DIR__ . '/../config/account-linking.php')['enabled'], 'Host/forwarded headers enabled a remote client.');
$_SERVER['REMOTE_ADDR'] = '127.0.0.1';
$_SERVER['SERVER_ADDR'] = '192.0.2.2';
accountLinkAssert(!(require __DIR__ . '/../config/account-linking.php')['enabled'], 'Non-loopback server enabled local testing.');
$_SERVER['SERVER_ADDR'] = '127.0.0.1';
putenv('APP_ENV=production');
accountLinkAssert(!(require __DIR__ . '/../config/account-linking.php')['enabled'], 'Production inherited local testing without approval.');
putenv('APP_ENV');
putenv('MCO_APP_ENV');
accountLinkAssert(!(require __DIR__ . '/../config/account-linking.php')['enabled'], 'Missing development environment enabled local testing.');
$_SERVER = $originalServer;
foreach ($originalEnvironment as $key => $value) putenv($value === false ? $key : $key . '=' . $value);
echo "Account linking input and configuration tests passed.\n";
