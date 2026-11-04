<?php declare(strict_types=1);

/**
 * SEO Meta — one place that decides what search engines, social networks
 * and AI answer engines see for a page.
 *
 * Resolution order for every field:
 *   1. per-page override saved from Admin -> SEO Management
 *      (dynamic_content, section_key = 'seo')
 *   2. site-wide default from Admin -> Settings (site_settings)
 *   3. a hard-coded, sensible fallback
 *
 * Everything the header renders goes through this file, which is what makes
 * the admin SEO screen functional rather than a form that saves nothing.
 */

require_once __DIR__ . '/../config/site_setting_and_config.php';

class SeoMeta
{
    /** Absolute, reachable default social-share image (1200x630). */
    public const DEFAULT_OG_IMAGE = '/assets/images/og/adsbooster-og.png';

    /**
     * The page slug used for per-page overrides.
     * /about.php -> "about", /blog-details.php -> "blog-details".
     */
    public static function currentSlug(): string
    {
        $file = basename((string)($_SERVER['SCRIPT_NAME'] ?? ''));
        $slug = preg_replace('/\.php$/i', '', $file);
        return strtolower((string)$slug);
    }

    /**
     * Per-page override value, or '' when the page has no override.
     */
    public static function override(string $slug, string $key): string
    {
        if ($slug === '' || $key === '') {
            return '';
        }
        try {
            $row = db()->fetchOne(
                "SELECT content_value FROM dynamic_content
                 WHERE page_slug = ? AND section_key = 'seo' AND item_key = ?
                 LIMIT 1",
                [$slug, $key]
            );
            return trim((string)($row['content_value'] ?? ''));
        } catch (\Throwable $e) {
            return '';
        }
    }

    /**
     * Resolve every meta field for a page.
     *
     * @return array{
     *   slug:string, title:string, description:string, keywords:string,
     *   og_image:string, og_type:string, canonical:string, robots:string,
     *   twitter_site:string
     * }
     */
    public static function resolve(?string $slug = null): array
    {
        $slug = $slug ?? self::currentSlug();

        $siteName  = (string)get_setting('site_name', 'Adsbooster.net');
        $siteSlogan = (string)get_setting('site_slogan', 'Digital Marketing Agency');

        $defaultTitle = (string)get_setting('seo_default_title', $siteName . ' — ' . $siteSlogan);
        $defaultDesc  = (string)get_setting(
            'seo_default_description',
            'Adsbooster.net is the all-in-one advertising automation platform: launch paid campaigns on Meta, TikTok and Google Ads, publish organic social posts, generate AI UGC creatives and track conversions server-side.'
        );
        $defaultKeywords = (string)get_setting(
            'seo_keywords',
            'advertising automation, meta ads, tiktok ads, google ads, social media scheduler, AI ad creative, UGC ads, CAPI tracking, ads API'
        );

        // Page-level title set by the PHP page itself (best signal).
        $pageTitle = isset($GLOBALS['pageTitle']) ? trim((string)$GLOBALS['pageTitle']) : '';
        $pageDesc  = isset($GLOBALS['pageDescription']) ? trim((string)$GLOBALS['pageDescription']) : '';

        $title = self::override($slug, 'meta_title');
        if ($title === '') {
            $title = $pageTitle !== '' ? $pageTitle : $defaultTitle;
        }

        $description = self::override($slug, 'meta_description');
        if ($description === '') {
            $description = $pageDesc !== '' ? $pageDesc : $defaultDesc;
        }
        $description = self::clamp($description, 300);

        $keywords = self::override($slug, 'meta_keywords');
        if ($keywords === '') {
            $keywords = $defaultKeywords;
        }

        $ogImage = self::override($slug, 'og_image');
        if ($ogImage === '') {
            $ogImage = (string)get_setting('seo_og_image', self::DEFAULT_OG_IMAGE);
        }
        $ogImage = self::absoluteImage($ogImage);

        return [
            'slug'         => $slug,
            'title'        => self::clamp($title, 95),
            'description'  => $description,
            'keywords'     => $keywords,
            'og_image'     => $ogImage,
            'og_type'      => self::override($slug, 'og_type') ?: 'website',
            'canonical'    => self::canonical(),
            'robots'       => self::override($slug, 'robots')
                ?: (string)get_setting('seo_default_robots', 'index, follow, max-snippet:-1, max-image-preview:large, max-video-preview:-1'),
            'twitter_site' => (string)get_setting('seo_twitter_site', '@adsbooster'),
        ];
    }

    /**
     * Absolute URL of the current request (https when appropriate).
     */
    public static function canonical(): string
    {
        $scheme = 'http';
        if ((!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
            || (($_SERVER['SERVER_PORT'] ?? '') === '443')
            || (strtolower((string)($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '')) === 'https')
            || preg_match('/^adsbooster\.net(:\d+)?$/i', (string)($_SERVER['HTTP_HOST'] ?? ''))) {
            $scheme = 'https';
        }

        $host = (string)($_SERVER['HTTP_HOST'] ?? 'adsbooster.net');
        $uri  = (string)($_SERVER['REQUEST_URI'] ?? '/');

        // Drop tracking parameters so canonicals stay stable.
        $parts = parse_url($uri);
        $path  = (string)($parts['path'] ?? '/');
        $query = [];
        if (!empty($parts['query'])) {
            parse_str((string)$parts['query'], $query);
            unset($query['utm_source'], $query['utm_medium'], $query['utm_campaign'], $query['utm_term'], $query['utm_content'], $query['gclid'], $query['fbclid']);
        }
        $qs = http_build_query($query);

        return $scheme . '://' . $host . $path . ($qs !== '' ? '?' . $qs : '');
    }

    /**
     * Turn a relative image path into an absolute URL.
     */
    public static function absoluteImage(string $path): string
    {
        $path = trim($path);
        if ($path === '') {
            $path = self::DEFAULT_OG_IMAGE;
        }
        if (preg_match('#^https?://#i', $path)) {
            return $path;
        }
        $base = defined('BASE_URL') ? (string)BASE_URL : '';
        if ($base === '') {
            $host = (string)($_SERVER['HTTP_HOST'] ?? 'adsbooster.net');
            $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
            $base = $scheme . '://' . $host;
        }
        return $base . '/' . ltrim($path, '/');
    }

    /**
     * Truncate a string to a maximum length on a word boundary.
     */
    public static function clamp(string $text, int $max): string
    {
        $text = trim(preg_replace('/\s+/u', ' ', $text) ?? $text);
        if (mb_strlen($text) <= $max) {
            return $text;
        }
        $cut = mb_substr($text, 0, $max);
        $sp = mb_strrpos($cut, ' ');
        if ($sp !== false && $sp > (int)($max * 0.6)) {
            $cut = mb_substr($cut, 0, $sp);
        }
        return rtrim($cut, " ,.;:-") . '…';
    }
}
