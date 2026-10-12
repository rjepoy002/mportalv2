<?php
/** Server-only Statement of Account integration settings. */
return [
    'endpoint' => 'https://soa.paleco.net/soa/generate.php',
    'api_key' => trim((string) getenv('dcf287a955d99b2a4a0cc19d1fd9a59663d6ea6d3698fc1fb878c4f465f8b59d')),
    // Comma-separated environment override supports a separate, verified PDF host.
    'allowed_pdf_hosts' => array_values(array_filter(array_map(
        static fn (string $host): string => strtolower(trim($host)),
        explode(',', (string) (getenv('PALECO_SOA_PDF_HOSTS') ?: 'soa.paleco.net'))
    ))),
];