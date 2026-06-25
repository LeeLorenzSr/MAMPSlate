<?php
declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/includes/bootstrap.php';

requireMethod('GET');

$user = $apiAuth->authenticateRequest();
if (!$user || !(bool)$user['is_active']) {
    jsonResponse([
        'ok' => false,
        'error' => 'unauthorized',
        'message' => 'Valid API credentials are required.',
    ], 401);
}

jsonResponse([
    'ok' => true,
    'user' => [
        'id' => (int)$user['id'],
        'email' => $user['email'],
        'display_name' => $user['display_name'],
        'role' => $user['role_name'],
        'slug' => $user['slug'] ?? null,
        'bio' => $user['bio'] ?? null,
        'cover_photo' => $user['cover_photo'] ?? null,
        'social_links' => [
            'github' => $user['social_github'] ?: null,
            'linkedin' => $user['social_linkedin'] ?: null,
            'website' => $user['social_website'] ?: null,
        ],
    ],
]);
