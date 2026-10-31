<?php
require_once __DIR__ . '/../config/site_setting_and_config.php';

$listings = db()->fetchAll("SELECT id, title, category, city, country, phone, whatsapp, price, image_url, is_verified, rating FROM marketplace_listings WHERE status = 'active' ORDER BY is_featured DESC, created_at DESC LIMIT 8");
echo "Active listings count: " . count($listings) . "\n";
foreach ($listings as $l) {
    echo "- ID {$l['id']}: {$l['title']} [{$l['category']}] in {$l['city']}, {$l['country']} (Phone: {$l['phone']}, WA: {$l['whatsapp']})\n";
}
