<?php
// lib/magic_link.php
// Helper functions to create and verify one-day contractor schedule links.

if (!defined('MAGIC_LINK_SECRET')) {
    define('MAGIC_LINK_SECRET', getenv('MAGIC_LINK_SECRET') ?: 'change-this-secret');
}

function magic_link_token(int $contractorId, string $date): string {
    $payload = $contractorId . '|' . $date;
    $hash = hash_hmac('sha256', $payload, MAGIC_LINK_SECRET, true);
    return rtrim(strtr(base64_encode($hash), '+/', '-_'), '=');
}

function magic_link_verify(int $contractorId, string $date, string $token): bool {
    $expected = magic_link_token($contractorId, $date);
    return hash_equals($expected, $token);
}