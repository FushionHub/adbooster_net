<?php declare(strict_types=1);

/**
 * Dynamic XML sitemap — https://adsbooster.net/sitemap.xml
 *
 * The `.htaccess` maps /sitemap.xml onto this file. It is generated from
 * live content (blog, marketplace, jobs, bounties) plus the static
 * marketing pages, so new content is indexed without a rebuild.
 */

require_once __DIR__ . '/config/site_setting_and_config.php';

// Canonical base URL (never derived from the Host header — that would let
// anyone mint arbitrary sitemap entries).
$baseUrl = rtrim((string)get_setting('site_url', 'https://adsbooster.net'), '/');
if (!preg_match('#^https?://#i', $baseUrl)) {
    $baseUrl = 'https://adsbooster.net';
}
$today = date('Y-m-d');

/**
 * XML-escape a value for element content.
 */
function xml_escape(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES | ENT_XML1, 'UTF-8');
}

/**
 * Normalise a date for <lastmod> (ISO 8601 / W3C datetime).
 */
function sitemap_date(?string $value, string $fallback): string
{
    if (empty($value)) {
        return $fallback;
    }
    $ts = strtotime((string)$value);
    if ($ts === false) {
        return $fallback;
    }
    return date('c', $ts);
}

$urls = [];

/**
 * Register a URL in the sitemap.
 */
function sitemap_add(array &$urls, string $loc, string $priority, string $changefreq, ?string $lastmod = null): void
{
    $urls[] = [
        'loc'       => $loc,
        'priority'  => $priority,
        'changefreq' => $changefreq,
        'lastmod'   => $lastmod,
    ];
}

// ── static marketing pages ───────────────────────────────────────────────
foreach ([
    ['/', '1.0', 'daily'],
    ['/about', '0.7', 'monthly'],
    ['/pricing-plan', '0.9', 'weekly'],
    ['/faq', '0.6', 'monthly'],
    ['/contact', '0.5', 'monthly'],
    ['/blog', '0.8', 'daily'],
    ['/marketplace', '0.9', 'daily'],
    ['/jobs', '0.8', 'daily'],
    ['/bounties', '0.8', 'daily'],
    ['/developer-docs', '0.9', 'weekly'],
    ['/status', '0.5', 'hourly'],
    ['/search', '0.5', 'weekly'],
] as [$loc, $prio, $freq]) {
    sitemap_add($urls, $loc, $prio, $freq, $today);
}

// ── blog posts ───────────────────────────────────────────────────────────
try {
    $posts = db()->fetchAll(
        "SELECT slug, title, excerpt, updated_at, published_at, featured_image
         FROM blog_posts
         WHERE status = 'published'
         ORDER BY published_at DESC
         LIMIT 500"
    );
    foreach ($posts as $post) {
        $slug = (string)($post['slug'] ?? '');
        if ($slug === '') {
            continue;
        }
        sitemap_add(
            $urls,
            '/blog-details/' . rawurlencode($slug),
            '0.7',
            'weekly',
            sitemap_date((string)($post['updated_at'] ?? $post['published_at'] ?? ''), $today)
        );
    }
} catch (\Throwable $e) {
    // Blog table unavailable — the static pages still make a valid sitemap.
}

// ── marketplace / directory listings ─────────────────────────────────────
try {
    $items = db()->fetchAll(
        "SELECT id, slug, title, updated_at, created_at
         FROM marketplace_items
         WHERE status = 'active'
         ORDER BY created_at DESC
         LIMIT 1000"
    );
    foreach ($items as $item) {
        sitemap_add(
            $urls,
            '/listing-details/' . (int)$item['id'],
            '0.6',
            'weekly',
            sitemap_date((string)($item['updated_at'] ?? $item['created_at'] ?? ''), $today)
        );
    }
} catch (\Throwable $e) {
}

// ── jobs ─────────────────────────────────────────────────────────────────
try {
    $jobs = db()->fetchAll(
        "SELECT id, created_at FROM jobs WHERE status = 'open' ORDER BY created_at DESC LIMIT 500"
    );
    foreach ($jobs as $job) {
        sitemap_add(
            $urls,
            '/job-details/' . (int)$job['id'],
            '0.6',
            'weekly',
            sitemap_date((string)($job['created_at'] ?? ''), $today)
        );
    }
} catch (\Throwable $e) {
}

// ── creator bounties ─────────────────────────────────────────────────────
try {
    $bounties = db()->fetchAll(
        "SELECT id, created_at FROM creator_bounties WHERE status = 'open' ORDER BY created_at DESC LIMIT 500"
    );
    foreach ($bounties as $bounty) {
        sitemap_add(
            $urls,
            '/bounty-details/' . (int)$bounty['id'],
            '0.6',
            'weekly',
            sitemap_date((string)($bounty['created_at'] ?? ''), $today)
        );
    }
} catch (\Throwable $e) {
}

// ── de-duplicate ─────────────────────────────────────────────────────────
$seen = [];
$unique = [];
foreach ($urls as $u) {
    if (isset($seen[$u['loc']])) {
        continue;
    }
    $seen[$u['loc']] = true;
    $unique[] = $u;
}

// ── render ───────────────────────────────────────────────────────────────
header('Content-Type: application/xml; charset=utf-8');
header('X-Robots-Tag: noindex, follow');

echo '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
echo '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9"' . "\n";
echo '        xmlns:image="http://www.google.com/schemas/sitemap-image/1.1">' . "\n";

foreach ($unique as $u) {
    echo "  <url>\n";
    echo '    <loc>' . xml_escape($baseUrl . $u['loc']) . "</loc>\n";
    echo '    <lastmod>' . xml_escape((string)($u['lastmod'] ?? $today)) . "</lastmod>\n";
    echo '    <changefreq>' . xml_escape((string)$u['changefreq']) . "</changefreq>\n";
    echo '    <priority>' . xml_escape((string)$u['priority']) . "</priority>\n";
    echo "  </url>\n";
}

echo '</urlset>' . "\n";
