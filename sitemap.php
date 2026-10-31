<?php declare(strict_types=1);

require_once __DIR__ . '/config/site_setting_and_config.php';

$baseUrl = 'https://' . ($_SERVER['HTTP_HOST'] ?? 'adsbooster.net');
$date = date('Y-m-d');

$urls = [
    ['loc' => '/', 'priority' => '1.0', 'changefreq' => 'daily'],
    ['loc' => '/about', 'priority' => '0.8', 'changefreq' => 'monthly'],
    ['loc' => '/pricing-plan', 'priority' => '0.9', 'changefreq' => 'monthly'],
    ['loc' => '/faq', 'priority' => '0.6', 'changefreq' => 'monthly'],
    ['loc' => '/contact', 'priority' => '0.7', 'changefreq' => 'monthly'],
    ['loc' => '/blog', 'priority' => '0.8', 'changefreq' => 'daily'],
    ['loc' => '/marketplace', 'priority' => '0.8', 'changefreq' => 'daily'],
    ['loc' => '/team', 'priority' => '0.5', 'changefreq' => 'monthly'],
    ['loc' => '/login', 'priority' => '0.3', 'changefreq' => 'monthly'],
    ['loc' => '/register', 'priority' => '0.5', 'changefreq' => 'monthly'],
];

// Add blog posts
try {
    $posts = db()->fetchAll("SELECT slug, updated_at FROM blog_posts WHERE status = 'published' ORDER BY published_at DESC LIMIT 100");
    foreach ($posts as $post) {
        $urls[] = [
            'loc' => '/blog-details?slug=' . $post['slug'],
            'priority' => '0.6',
            'changefreq' => 'weekly',
            'lastmod' => date('Y-m-d', strtotime($post['updated_at'])),
        ];
    }
} catch (\Throwable $e) {}

// Add marketplace items
try {
    $items = db()->fetchAll("SELECT slug, updated_at FROM marketplace_items WHERE status = 'active' ORDER BY created_at DESC LIMIT 100");
    foreach ($items as $item) {
        $urls[] = [
            'loc' => '/marketplace?item=' . $item['slug'],
            'priority' => '0.6',
            'changefreq' => 'weekly',
            'lastmod' => date('Y-m-d', strtotime($item['updated_at'])),
        ];
    }
} catch (\Throwable $e) {}

header('Content-Type: application/xml; charset=utf-8');
echo '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
echo '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n";
foreach ($urls as $url) {
    echo '  <url>' . "\n";
    echo '    <loc>' . e($baseUrl . $url['loc']) . '</loc>' . "\n";
    echo '    <lastmod>' . ($url['lastmod'] ?? $date) . '</lastmod>' . "\n";
    echo '    <changefreq>' . $url['changefreq'] . '</changefreq>' . "\n";
    echo '    <priority>' . $url['priority'] . '</priority>' . "\n";
    echo '  </url>' . "\n";
}
echo '</urlset>';
