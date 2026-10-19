<?php
/** PALECO SOA requests are authorized only through the active portal account. */
function soaLatestBillMonth(PDO $pdo, int $userId, string $acctNo): ?string
{
    $ownership = $pdo->prepare("SELECT 1 FROM mco_portal.paleco_accounts WHERE user_id = ? AND AcctNo = ? AND status = 'active' LIMIT 1");
    $ownership->execute([$userId, $acctNo]);
    if (!$ownership->fetchColumn()) throw new DomainException('Unauthorized account.');
    // Legacy BillMonth values use YYYYMM. Numeric ordering avoids lexical assumptions.
    $stmt = $pdo->prepare("SELECT BillMonth FROM palecxzp_pal_db.billhistory WHERE AcctNo = ? AND BillMonth IS NOT NULL AND TRIM(BillMonth) <> '' ORDER BY CAST(BillMonth AS UNSIGNED) DESC, BillMonth DESC LIMIT 1");
    $stmt->execute([$acctNo]);
    $month = $stmt->fetchColumn();
    return $month === false ? null : trim((string) $month);
}
function soaAuthorizedSelectedAccount(PDO $pdo, int $userId, string $acctNo): ?array
{
    $stmt = $pdo->prepare("SELECT pa.AcctNo, m.AcctCode FROM mco_portal.paleco_accounts pa INNER JOIN palecxzp_pal_db.master m ON m.AcctNo = pa.AcctNo WHERE pa.user_id = ? AND pa.AcctNo = ? AND pa.status = 'active' LIMIT 1");
    $stmt->execute([$userId, $acctNo]);
    $account = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$account || trim((string) $account['AcctCode']) === '') throw new DomainException('Unauthorized account.');
    return $account;
}
function soaValidatedPdfUrl(string $url, array $allowedHosts): ?string
{
    $parts = parse_url($url);
    if (!is_array($parts) || strtolower((string) ($parts['scheme'] ?? '')) !== 'https') return null;
    $host = strtolower((string) ($parts['host'] ?? ''));
    return $host !== '' && in_array($host, $allowedHosts, true) ? $url : null;
}
function soaRequestPdfUrl(string $acctCode, string $month, array $config, ?callable $transport = null): array
{
    if (($config['api_key'] ?? '') === '') return ['ok' => false, 'message' => 'Statement of Account is currently unavailable.'];
    if (!function_exists('curl_init') && $transport === null) return ['ok' => false, 'message' => 'Statement of Account is currently unavailable.'];
    $transport ??= static function (string $endpoint, string $body, array $headers): array {
        $curl = curl_init($endpoint);
        curl_setopt_array($curl, [CURLOPT_POST => true, CURLOPT_POSTFIELDS => $body, CURLOPT_HTTPHEADER => $headers, CURLOPT_RETURNTRANSFER => true, CURLOPT_CONNECTTIMEOUT => 10, CURLOPT_TIMEOUT => 20, CURLOPT_FOLLOWLOCATION => false, CURLOPT_SSL_VERIFYPEER => true, CURLOPT_SSL_VERIFYHOST => 2]);
        $body = curl_exec($curl); $error = curl_error($curl); $status = (int) curl_getinfo($curl, CURLINFO_RESPONSE_CODE); curl_close($curl);
        return ['status' => $status, 'body' => is_string($body) ? $body : '', 'error' => $error];
    };
    try { $response = $transport($config['endpoint'], http_build_query(['acct' => $acctCode, 'month' => $month], '', '&', PHP_QUERY_RFC3986), ['Content-Type: application/x-www-form-urlencoded', 'X-API-Key: ' . $config['api_key'], 'Accept: application/json']); }
    catch (Throwable $e) { return ['ok' => false, 'message' => 'Statement of Account request timed out. Please try again.']; }
    if (($response['status'] ?? 0) !== 200) return ['ok' => false, 'message' => 'Statement of Account is currently unavailable.'];
    $payload = json_decode((string) ($response['body'] ?? ''), true);
    $url = is_array($payload) && !empty($payload['success']) ? soaValidatedPdfUrl((string) ($payload['url'] ?? ''), $config['allowed_pdf_hosts'] ?? []) : null;
    return $url ? ['ok' => true, 'url' => $url] : ['ok' => false, 'message' => 'Statement of Account is currently unavailable.'];
}