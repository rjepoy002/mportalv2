<?php
// Independent development-only opt-in. Never enables the application feature.
$environment = getenv('APP_ENV') ?: (getenv('MCO_APP_ENV') ?: '');
return [
    'enabled' => getenv('MCO_NEW_CONNECTION_PREVIEW_ENABLED') === '1',
    'development' => in_array($environment, ['local', 'development'], true),
];
