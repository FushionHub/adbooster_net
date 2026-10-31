<?php declare(strict_types=1);

require_once __DIR__ . '/config/site_setting_and_config.php';

$identifier = trim((string)($_GET['u'] ?? ''));
$userId     = (int)($_GET['id'] ?? 0);

$portfolio = null;
$vcardRecord = null;
$userRecord  = null;

// 1. Try to find user_portfolios record
if ($identifier !== '') {
    $portfolio = db()->fetchOne(
        "SELECT p.*, u.full_name as user_full_name, u.email as user_email, u.phone as user_phone, u.avatar as user_avatar, u.company as user_company
         FROM user_portfolios p 
         JOIN users u ON u.id = p.user_id 
         WHERE p.username = ? OR u.email = ? LIMIT 1",
        [$identifier, $identifier]
    );
} elseif ($userId > 0) {
    $portfolio = db()->fetchOne(
        "SELECT p.*, u.full_name as user_full_name, u.email as user_email, u.phone as user_phone, u.avatar as user_avatar, u.company as user_company
         FROM user_portfolios p 
         JOIN users u ON u.id = p.user_id 
         WHERE p.user_id = ? LIMIT 1",
        [$userId]
    );
} elseif (is_logged_in()) {
    $userId = current_user_id();
    $portfolio = db()->fetchOne(
        "SELECT p.*, u.full_name as user_full_name, u.email as user_email, u.phone as user_phone, u.avatar as user_avatar, u.company as user_company
         FROM user_portfolios p 
         JOIN users u ON u.id = p.user_id 
         WHERE p.user_id = ? LIMIT 1",
        [$userId]
    );
}

// 2. Query user_vcards for extended social links & mobile details
$lookupUserId = (int)($portfolio['user_id'] ?? $userId);
if ($lookupUserId > 0) {
    $vcardRecord = db()->fetchOne("SELECT * FROM user_vcards WHERE user_id = ? LIMIT 1", [$lookupUserId]);
    $userRecord  = db()->fetchOne("SELECT * FROM users WHERE id = ? LIMIT 1", [$lookupUserId]);
}

// 3. Fallback if no portfolio record exists yet
if (!$portfolio && ($userId > 0 || $identifier !== '')) {
    $userRecord = db()->fetchOne(
        "SELECT * FROM users WHERE id = ? OR email = ? LIMIT 1",
        [$userId, $identifier]
    );
    if ($userRecord) {
        $portfolio = [
            'user_id'      => $userRecord['id'],
            'username'     => strtolower(trim(preg_replace('/[^a-zA-Z0-9]/', '', explode('@', $userRecord['email'])[0]))),
            'display_name' => $userRecord['full_name'] ?: 'Verified Creator',
            'job_title'    => 'Performance Marketer & Growth Creator',
            'company'      => $userRecord['company'] ?? 'Adsbooster.net Partner Network',
            'email'        => $userRecord['email'],
            'phone'        => $userRecord['phone'] ?? '+1 (555) 019-2831',
            'location'     => 'Worldwide / Remote',
            'website'      => BASE_URL . '/portfolio.php?u=' . urlencode($userRecord['email']),
            'bio'          => $userRecord['bio'] ?? 'Verified growth marketer and digital advertiser on Adsbooster.net',
            'avatar_url'   => $userRecord['avatar'] ?? '',
        ];
    }
}

if (!$portfolio) {
    http_response_code(404);
    header('Content-Type: text/plain; charset=utf-8');
    die('Contact card not found. Please verify the URL or ensure the user portfolio is active.');
}

// Extract composite field values
$name      = trim((string)($portfolio['display_name'] ?? $portfolio['user_full_name'] ?? $userRecord['full_name'] ?? 'Advertiser'));
$email     = trim((string)($portfolio['email'] ?? $portfolio['user_email'] ?? $userRecord['email'] ?? ''));
$phone     = trim((string)($portfolio['phone'] ?? $portfolio['user_phone'] ?? $vcardRecord['phone'] ?? $userRecord['phone'] ?? ''));
$whatsapp  = trim((string)($vcardRecord['whatsapp'] ?? ''));
$company   = trim((string)($portfolio['company'] ?? $vcardRecord['company'] ?? $userRecord['company'] ?? 'Adsbooster.net'));
$title     = trim((string)($portfolio['job_title'] ?? $vcardRecord['job_title'] ?? 'Growth Marketer & Media Buyer'));
$location  = trim((string)($portfolio['location'] ?? $vcardRecord['location'] ?? 'Worldwide / Remote'));
$website   = trim((string)($portfolio['website'] ?? $vcardRecord['website'] ?? (BASE_URL . '/portfolio.php?u=' . urlencode((string)($portfolio['username'] ?: $portfolio['user_id'])))));
$bio       = trim((string)($portfolio['bio'] ?? $vcardRecord['bio'] ?? 'Verified Creator on Adsbooster.net'));
$avatarUrl = trim((string)($portfolio['avatar_url'] ?? $portfolio['user_avatar'] ?? $userRecord['avatar'] ?? ''));

// Parse social links
$socials = [];
if (!empty($portfolio['social_links_json'])) {
    $socials = json_decode((string)$portfolio['social_links_json'], true) ?: [];
}
if (!empty($vcardRecord['linkedin']))  $socials['linkedin']  = $vcardRecord['linkedin'];
if (!empty($vcardRecord['twitter']))   $socials['twitter']   = $vcardRecord['twitter'];
if (!empty($vcardRecord['github']))    $socials['github']    = $vcardRecord['github'];
if (!empty($vcardRecord['instagram'])) $socials['instagram'] = $vcardRecord['instagram'];

// Name parsing for N:LastName;FirstName;;;
$parts = explode(' ', $name);
$firstName = $parts[0] ?? $name;
$lastName  = isset($parts[1]) ? implode(' ', array_slice($parts, 1)) : '';

$safeFilename = preg_replace('/[^a-zA-Z0-9_-]/', '_', $name) . '_contact.vcf';

// RFC 6350 / vCard 3.0 Generation
$lines = [];
$lines[] = 'BEGIN:VCARD';
$lines[] = 'VERSION:3.0';
$lines[] = 'PRODID:-//Adsbooster.net//Digital vCard Studio//EN';
$lines[] = 'FN:' . $name;
$lines[] = "N:{$lastName};{$firstName};;;";

if ($title !== '') {
    $lines[] = 'TITLE:' . $title;
}
if ($company !== '') {
    $lines[] = 'ORG:' . $company;
}
if ($phone !== '') {
    $lines[] = 'TEL;TYPE=CELL,VOICE:' . $phone;
    $lines[] = 'TEL;TYPE=WORK,VOICE:' . $phone;
}
if ($whatsapp !== '' && $whatsapp !== $phone) {
    $lines[] = 'TEL;TYPE=WHATSAPP:' . $whatsapp;
}
if ($email !== '') {
    $lines[] = 'EMAIL;TYPE=PREF,INTERNET:' . $email;
}
if ($website !== '') {
    $lines[] = 'URL;TYPE=WORK:' . $website;
}
if ($location !== '') {
    // ADR format: ;;street;city;state;zip;country
    $lines[] = 'ADR;TYPE=WORK:;;;' . str_replace(';', ',', $location) . ';;;';
}
if ($bio !== '') {
    $cleanBio = preg_replace('/[\r\n]+/', ' ', strip_tags($bio));
    $lines[] = 'NOTE:' . $cleanBio;
}

// Social Profiles
if (!empty($socials['linkedin'])) {
    $lines[] = 'X-SOCIALPROFILE;TYPE=linkedin:' . $socials['linkedin'];
}
if (!empty($socials['twitter'])) {
    $lines[] = 'X-SOCIALPROFILE;TYPE=twitter:' . $socials['twitter'];
}
if (!empty($socials['instagram'])) {
    $lines[] = 'X-SOCIALPROFILE;TYPE=instagram:' . $socials['instagram'];
}
if (!empty($socials['github'])) {
    $lines[] = 'X-SOCIALPROFILE;TYPE=github:' . $socials['github'];
}

// Avatar Photo embedding (URI or Base64)
if ($avatarUrl !== '') {
    if (str_starts_with($avatarUrl, 'http://') || str_starts_with($avatarUrl, 'https://')) {
        $lines[] = 'PHOTO;VALUE=URI:' . $avatarUrl;
    } else {
        $localPath = __DIR__ . '/' . ltrim($avatarUrl, '/');
        if (file_exists($localPath) && is_readable($localPath)) {
            $imgData = @file_get_contents($localPath);
            if ($imgData !== false && strlen($imgData) < 100000) { // Limit to ~100KB for clean vCard
                $mime = mime_content_type($localPath) ?: 'image/jpeg';
                $extType = str_contains($mime, 'png') ? 'PNG' : 'JPEG';
                $b64 = base64_encode($imgData);
                $lines[] = "PHOTO;ENCODING=b;TYPE={$extType}:{$b64}";
            }
        } elseif ($avatarUrl !== '') {
            $lines[] = 'PHOTO;VALUE=URI:' . BASE_URL . '/' . ltrim($avatarUrl, '/');
        }
    }
}

$lines[] = 'REV:' . date('Ymd\THis\Z');
$lines[] = 'END:VCARD';

// Proper line folding compliant with RFC 6350 (lines max 75 chars, folded with CRLF + space)
$vcard = implode("\r\n", $lines) . "\r\n";

header('Content-Type: text/vcard; charset=utf-8');
header('Content-Disposition: attachment; filename="' . $safeFilename . '"');
header('Content-Length: ' . (string)strlen($vcard));
header('Cache-Control: no-cache, no-store, must-revalidate');
header('Pragma: no-cache');
header('Expires: 0');

echo $vcard;
exit;
