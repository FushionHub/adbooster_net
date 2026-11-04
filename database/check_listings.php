<?php
/**
 * Diagnostic — list the active marketplace / directory listings.
 *
 * The platform stores listings in `marketplace_items` (the legacy
 * `marketplace_listings` table is unused by the application and no longer
 * has the columns the app needs).
 */
require_once __DIR__ . '/../config/site_setting_and_config.php';

$listings = db()->fetchAll(
    "SELECT id, title, item_type, business_type, category, address, phone, whatsapp,
            price, image_url, seller_verified, rating
     FROM marketplace_items
     WHERE status = 'active'
     ORDER BY featured DESC, created_at DESC
     LIMIT 8"
);

echo "Active listings count: " . count($listings) . "\n";
foreach ($listings as $l) {
    $type  = $l['item_type'] ?: 'item';
    $biz   = !empty($l['business_type']) ? ' (' . $l['business_type'] . ')' : '';
    $where = trim((string)$l['address']) !== '' ? $l['address'] : 'no address';
    echo "- ID {$l['id']}: {$l['title']} [{$l['category']}] {$type}{$biz} @ {$where}"
        . " (Phone: {$l['phone']}, WA: {$l['whatsapp']}, Price: {$l['price']})\n";
}
