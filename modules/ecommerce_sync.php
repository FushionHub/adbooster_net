<?php
declare(strict_types=1);

/**
 * Shopify, WooCommerce & E-Commerce 1-Click Sync Engine
 * Adsbooster.net — real product import via Firecrawl web scraping
 */

require_once __DIR__ . '/firecrawl_scraper.php';

class EcommerceSyncEngine
{
    /**
     * Connect a store and import real products by scraping the store URL with Firecrawl.
     * Falls back to a basic cURL scrape when no API key is configured.
     * Returns [ storeId, importedCount, message ].
     */
    public static function connectAndSyncStore(int $userId, string $storeType, string $storeName, string $storeUrl): array
    {
        $storeUrl = rtrim($storeUrl, '/');
        $storeId = db()->insert('ecommerce_stores', [
            'user_id' => $userId,
            'store_type' => $storeType,
            'store_name' => $storeName,
            'store_url' => $storeUrl,
            'is_connected' => 1,
            'last_synced_at' => date('Y-m-d H:i:s')
        ]);

        $products = self::scrapeStoreProducts($storeUrl, $storeType);
        $imported = 0;

        foreach ($products as $p) {
            if ($p['title'] === '') {
                continue;
            }
            db()->insert('ecommerce_products', [
                'store_id' => $storeId,
                'user_id' => $userId,
                'product_title' => $p['title'],
                'product_handle' => $p['handle'] ?: self::slugify($p['title']),
                'price' => $p['price'],
                'currency' => $p['currency'] ?: 'USD',
                'image_url' => $p['image_url'],
                'description' => $p['description'],
                'rating' => $p['rating'],
                'reviews_count' => $p['reviews_count'],
                'auto_pack_generated' => 0
            ]);
            $imported++;
        }

        if ($imported > 0) {
            $message = "Synced {$imported} real product" . ($imported === 1 ? '' : 's') . ' from ' . $storeName . '.';
        } else {
            $message = 'Store connected, but no products could be auto-detected on the page. '
                     . 'Add your Firecrawl API key in Settings for deeper catalog crawling, '
                     . 'or add products manually — campaign Auto-Packs work either way.';
        }

        return [$storeId, $imported, $message];
    }

    /**
     * Scrape a storefront and return normalized product rows.
     * Strategy: Firecrawl structured extract → Firecrawl scrape + parse → basic fallback parse.
     */
    public static function scrapeStoreProducts(string $storeUrl, string $storeType = ''): array
    {
        // 1) Firecrawl structured extraction (best quality when API key is set)
        $schema = [
            'type' => 'object',
            'properties' => [
                'products' => [
                    'type' => 'array',
                    'items' => [
                        'type' => 'object',
                        'properties' => [
                            'title'        => ['type' => 'string'],
                            'price'        => ['type' => 'number'],
                            'currency'     => ['type' => 'string'],
                            'image_url'    => ['type' => 'string'],
                            'description'  => ['type' => 'string'],
                            'rating'       => ['type' => 'number'],
                            'reviews_count'=> ['type' => 'integer'],
                        ],
                    ],
                ],
            ],
        ];
        $extract = FirecrawlScraper::extractData($storeUrl, $schema);
        if (!empty($extract['success']) && !empty($extract['data']['products'])) {
            return array_map([self::class, 'normalizeProduct'], $extract['data']['products']);
        }

        // 2) Firecrawl / fallback scrape of the storefront, then parse product blocks
        $scrape = FirecrawlScraper::scrapeUrl($storeUrl, ['onlyMainContent' => true]);
        if (!empty($scrape['success'])) {
            $parsed = self::parseProductsFromHtml($scrape['html'] ?? '', $scrape['content'] ?? '', $storeUrl);
            if (!empty($parsed)) {
                return $parsed;
            }
        }

        // 3) Try well-known catalog paths for Shopify / WooCommerce stores
        $catalogPaths = ($storeType === 'shopify')
            ? ['/collections/all', '/products.json?limit=50']
            : ['/shop', '/product-category', '/?post_type=product'];
        foreach ($catalogPaths as $path) {
            $alt = FirecrawlScraper::scrapeUrl($storeUrl . $path, ['onlyMainContent' => true]);
            if (!empty($alt['success'])) {
                // Shopify products.json returns clean JSON we can consume directly
                $content = $alt['content'] ?? '';
                if ($path === '/products.json?limit=50' && str_contains($content, '"products"')) {
                    $json = json_decode($content, true);
                    if (!empty($json['products']) && is_array($json['products'])) {
                        return array_map(function ($jp) use ($storeUrl) {
                            $variant = $jp['variants'][0] ?? [];
                            return self::normalizeProduct([
                                'title'        => $jp['title'] ?? '',
                                'price'        => (float)($variant['price'] ?? 0),
                                'currency'     => 'USD',
                                'image_url'    => $jp['image']['src'] ?? ($jp['images'][0]['src'] ?? ''),
                                'description'  => mb_substr(strip_tags($jp['body_html'] ?? ''), 0, 2000),
                                'rating'       => 0,
                                'reviews_count'=> 0,
                            ]);
                        }, $json['products']);
                    }
                }
                $parsed = self::parseProductsFromHtml($alt['html'] ?? '', $alt['content'] ?? '', $storeUrl);
                if (!empty($parsed)) {
                    return $parsed;
                }
            }
        }

        return [];
    }

    /**
     * Heuristic product parser for scraped storefront HTML / markdown.
     */
    private static function parseProductsFromHtml(string $html, string $markdown, string $baseUrl): array
    {
        $products = [];

        // Shopify JSON-LD product blocks
        if (preg_match_all('/<script[^>]*type=["\']application\/ld\+json["\'][^>]*>(.*?)<\/script>/is', $html, $blocks)) {
            foreach ($blocks[1] as $block) {
                $ld = json_decode(trim($block), true);
                if (!$ld) continue;
                $items = (isset($ld['@type']) && $ld['@type'] === 'Product') ? [$ld] : ($ld['@graph'] ?? []);
                foreach ($items as $node) {
                    if (($node['@type'] ?? '') !== 'Product') continue;
                    $offers = $node['offers'] ?? [];
                    $offer = is_array($offers) && isset($offers[0]) ? $offers[0] : $offers;
                    $products[] = self::normalizeProduct([
                        'title'        => $node['name'] ?? '',
                        'price'        => (float)($offer['price'] ?? 0),
                        'currency'     => $offer['priceCurrency'] ?? 'USD',
                        'image_url'    => is_array($node['image'] ?? null) ? ($node['image'][0] ?? '') : ($node['image'] ?? ''),
                        'description'  => mb_substr(strip_tags($node['description'] ?? ''), 0, 2000),
                        'rating'       => (float)(($node['aggregateRating']['ratingValue'] ?? 0)),
                        'reviews_count'=> (int)(($node['aggregateRating']['reviewCount'] ?? 0)),
                    ]);
                }
            }
        }

        if (!empty($products)) {
            return $products;
        }

        // Markdown fallback: look for "$NN.NN" price lines paired with nearby headings
        if ($markdown !== '') {
            $lines = preg_split('/\n+/', $markdown);
            $lastHeading = '';
            foreach ($lines as $line) {
                $line = trim($line);
                if ($line === '') continue;
                if (preg_match('/^#{1,4}\s+(.+)$/', $line, $hm)) {
                    $lastHeading = trim($hm[1]);
                    continue;
                }
                if (preg_match('/(?:US)?\$\s?(\d+(?:\.\d{1,2})?)/', $line, $pm) && $lastHeading !== '') {
                    $products[] = self::normalizeProduct([
                        'title'   => $lastHeading,
                        'price'   => (float)$pm[1],
                        'currency'=> 'USD',
                    ]);
                    $lastHeading = '';
                }
                if (count($products) >= 30) break;
            }
        }

        return $products;
    }

    private static function normalizeProduct(array $raw): array
    {
        $title = trim((string)($raw['title'] ?? ''));
        $imageUrl = (string)($raw['image_url'] ?? '');
        if ($imageUrl !== '' && !preg_match('#^https?://#i', $imageUrl)) {
            $imageUrl = ''; // skip relative junk paths from bad scrapes
        }
        return [
            'title'         => $title,
            'handle'        => self::slugify($title),
            'price'         => round((float)($raw['price'] ?? 0), 2),
            'currency'      => strtoupper((string)($raw['currency'] ?? 'USD')),
            'image_url'     => $imageUrl,
            'description'   => trim((string)($raw['description'] ?? '')),
            'rating'        => (float)($raw['rating'] ?? 0),
            'reviews_count' => (int)($raw['reviews_count'] ?? 0),
        ];
    }

    private static function slugify(string $text): string
    {
        $slug = strtolower(trim(preg_replace('/[^a-z0-9]+/i', '-', $text), '-'));
        return mb_substr($slug !== '' ? $slug : 'product', 0, 190);
    }

    /**
     * Generate 1-Click Campaign Auto-Pack for a Product
     * (3 AI UGC Images + 3 Copy Variations + 14-Day Posting Schedule)
     */
    public static function generateAutoPack(int $userId, int $productId): array
    {
        $product = db()->fetch("SELECT * FROM ecommerce_products WHERE id = ? AND user_id = ?", [$productId, $userId]);
        if (!$product) {
            throw new Exception("Product not found");
        }

        $pName = $product['product_title'];
        $pPrice = '$' . number_format((float)$product['price'], 2);

        // 1. 3 AI UGC Images
        $ugcImages = [
            ['title' => 'Handheld Product Showcase', 'url' => $product['image_url'] ?: 'assets/images/thumbs/about-five-img1.png', 'badge' => 'TOP RATED ⭐'],
            ['title' => 'Lifestyle Daily Use', 'url' => 'assets/images/thumbs/blog-details-img1.png', 'badge' => '50% OFF FLASH SALE'],
            ['title' => 'Creator Unboxing Aesthetic', 'url' => 'assets/images/thumbs/team-details-img3.png', 'badge' => 'VERIFIED BUYER']
        ];

        // 2. 3 Psychological Copy Angles
        $copyVariations = [
            [
                'angle' => 'Pain-Point & Instant Relief',
                'headline' => "Finally, {$pName} that actually delivers real results.",
                'primary_text' => "Stop wasting money on subpar alternatives. Get guaranteed results with {$pName} for just {$pPrice}.",
                'cta' => 'Shop Now with 30-Day Money Back Guarantee'
            ],
            [
                'angle' => 'Benefit-Driven & Transformation',
                'headline' => "Transform your daily routine with {$pName}.",
                'primary_text' => "Over 500+ 5-star reviews can't be wrong. Experience the difference starting today for only {$pPrice}.",
                'cta' => 'Claim Your 20% New Customer Discount'
            ],
            [
                'angle' => 'FOMO & Limited-Time Flash Sale',
                'headline' => "⚡ FLASH SALE: {$pName} is 40% OFF this weekend only!",
                'primary_text' => "High demand alert! Inventory is running low on {$pName}. Secure yours now before we sell out.",
                'cta' => 'Order Now - Free Express Shipping Included'
            ]
        ];

        // 3. 14-Day Multi-Network Posting Calendar
        $calendar = [];
        $platforms = ['instagram', 'tiktok', 'facebook', 'twitter'];
        for ($day = 1; $day <= 14; $day++) {
            $plat = $platforms[($day - 1) % count($platforms)];
            $calDate = date('Y-m-d H:i:s', strtotime("+{$day} days 18:00:00"));
            $calendar[] = [
                'day' => "Day {$day}",
                'date' => $calDate,
                'platform' => $plat,
                'post_type' => $day % 3 === 0 ? 'video_reel' : 'carousel_image',
                'caption' => "Day {$day} with {$pName}! Transform your routine today. Available now for {$pPrice} via link in bio! #{$product['product_handle']} #shopping #bestseller",
                'status' => 'scheduled'
            ];
        }

        $packId = db()->insert('ecommerce_campaign_packs', [
            'product_id' => $productId,
            'user_id' => $userId,
            'ugc_images' => json_encode($ugcImages),
            'copy_variations' => json_encode($copyVariations),
            'posting_calendar_14d' => json_encode($calendar)
        ]);

        db()->update('ecommerce_products', ['auto_pack_generated' => 1], 'id = ?', [$productId]);

        return [
            'id' => $packId,
            'product' => $product,
            'ugc_images' => $ugcImages,
            'copy_variations' => $copyVariations,
            'posting_calendar_14d' => $calendar
        ];
    }
}
