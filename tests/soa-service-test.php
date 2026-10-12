<?php
require_once __DIR__ . '/../includes/soa-service.php';
$config = ['endpoint' => 'https://soa.paleco.net/soa/generate.php', 'api_key' => 'test-secret', 'allowed_pdf_hosts' => ['soa.paleco.net']];
$captured = [];
$result = soaRequestPdfUrl('02-0503-7438', '202601', $config, static function ($endpoint, $body, $headers) use (&$captured) { $captured = compact('endpoint', 'body', 'headers'); return ['status' => 200, 'body' => json_encode(['success' => true, 'url' => 'https://soa.paleco.net/files/token.pdf']), 'error' => '']; });
if (!$result['ok'] || $result['url'] !== 'https://soa.paleco.net/files/token.pdf') throw new RuntimeException('SOA success response was not accepted.');
if ($captured['endpoint'] !== $config['endpoint'] || !str_contains($captured['body'], 'acct=02-0503-7438') || !str_contains($captured['body'], 'month=202601')) throw new RuntimeException('SOA request payload was incorrect.');
if (!soaValidatedPdfUrl('https://soa.paleco.net/a.pdf', ['soa.paleco.net']) || soaValidatedPdfUrl('http://soa.paleco.net/a.pdf', ['soa.paleco.net']) || soaValidatedPdfUrl('https://evil.example/a.pdf', ['soa.paleco.net'])) throw new RuntimeException('SOA PDF URL allowlist validation was incorrect.');
if (soaRequestPdfUrl('02-0503-7438', '202601', $config, static fn () => ['status' => 200, 'body' => '{"success":true,"url":"https://evil.example/a.pdf"}', 'error' => ''])['ok']) throw new RuntimeException('Untrusted SOA PDF URL was accepted.');
if (soaRequestPdfUrl('02-0503-7438', '202601', $config, static fn () => ['status' => 500, 'body' => '', 'error' => ''])['ok']) throw new RuntimeException('SOA upstream failure was accepted.');
if (soaRequestPdfUrl('02-0503-7438', '202601', ['endpoint' => $config['endpoint'], 'api_key' => '', 'allowed_pdf_hosts' => $config['allowed_pdf_hosts']], static fn () => ['status' => 200, 'body' => '{}', 'error' => ''])['ok']) throw new RuntimeException('Missing SOA API key was accepted.');
$endpointSource = file_get_contents(__DIR__ . '/../soa.php');
foreach (['csrf_token', "\$_SESSION['SelectedAcctNo']", 'soaAuthorizedSelectedAccount', 'Cache-Control: no-store'] as $required) if (!str_contains($endpointSource, $required)) throw new RuntimeException('SOA endpoint security control is missing: ' . $required);
echo "SOA service tests passed.\n";