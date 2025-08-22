<?php
// lib/ids.php
// Tiny ULID generator (sortable, URL-safe, 26 chars)
function ulid(): string {
    // Crockford’s base32
    $enc = '0123456789ABCDEFGHJKMNPQRSTVWXYZ';
    $nowMs = (int) floor(microtime(true) * 1000);
    $timePart = '';
    for ($i = 9; $i >= 0; $i--) {
        $timePart = $enc[$nowMs % 32] . $timePart;
        $nowMs = intdiv($nowMs, 32);
    }
    $randPart = '';
    for ($i = 0; $i < 16; $i++) {
        $randPart .= $enc[random_int(0, 31)];
    }
    return $timePart . $randPart; // 26 chars
}
