<?php

require_once __DIR__ . '/../middleware/auth.php';
require_once __DIR__ . '/../../config/consumer-education.php';

function educationEscape($value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

$userDisplayName = trim((string) ($_SESSION['user_name'] ?? ''));
$userEmail = trim((string) ($_SESSION['user_email'] ?? ''));

if ($userDisplayName === '') {
    $userDisplayName = $userEmail !== '' ? $userEmail : 'Member';
}

try {
    $playlistUrl = consumerEducationPlaylistUrl();
    $embedUrl = consumerEducationEmbedUrl();
    $playlist = consumerEducationPlaylist();
    $videos = $playlist['videos'];
    $initialVideo = consumerEducationInitialVideo($videos);
} catch (Throwable $e) {
    http_response_code(500);
    exit('Consumer Education is temporarily unavailable.');
}

$navigation = [
    ['href' => 'dashboard.php?page=dashboard', 'label' => 'Dashboard'],
    ['href' => 'dashboard.php?page=bills', 'label' => 'My Bills'],
    ['href' => 'dashboard.php?page=account-settings', 'label' => 'Account & Settings'],
    ['href' => 'consumer-education.php', 'label' => 'Consumer Education'],
];
require __DIR__ . '/../views/pages/consumer-education.php';
