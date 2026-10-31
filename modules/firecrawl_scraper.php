<?php declare(strict_types=1);

/**
 * Firecrawl Web Scraping Module — Adsbooster.net
 *
 * Provides website scraping for market research, competitor analysis,
 * and content extraction via the Firecrawl API.
 * Falls back to cURL-based scraping when no API key is configured.
 */

require_once __DIR__ . '/../config/db.php';

class FirecrawlScraper
{
    private const API_BASE = 'https://api.firecrawl.dev/v1';

    /**
     * Scrape a URL and return structured content.
     */
    public static function scrapeUrl(string $url, array $options = []): array
    {
        $apiKey = get_setting('firecrawl_api_key');

        if (!empty($apiKey)) {
            return self::firecrawlScrape($url, $apiKey, $options);
        }

        return self::fallbackScrape($url);
    }

    /**
     * Crawl an entire website and return multiple pages.
     */
    public static function crawlSite(string $baseUrl, int $limit = 50): array
    {
        $apiKey = get_setting('firecrawl_api_key');

        if (!empty($apiKey)) {
            return self::firecrawlCrawl($baseUrl, $apiKey, $limit);
        }

        return ['success' => false, 'pages' => [], 'message' => 'Firecrawl API key not configured. Add your key in Settings.'];
    }

    /**
     * Extract structured data from a URL using Firecrawl's extract endpoint.
     */
    public static function extractData(string $url, array $schema): array
    {
        $apiKey = get_setting('firecrawl_api_key');
        if (empty($apiKey)) {
            return ['success' => false, 'data' => null, 'message' => 'Firecrawl API key required for extraction.'];
        }

        $payload = json_encode([
            'urls'   => [$url],
            'schema' => $schema,
        ]);

        $ch = curl_init(self::API_BASE . '/extract');
        curl_setopt_array($ch, [
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => $payload,
            CURLOPT_HTTPHEADER     => [
                'Authorization: Bearer ' . $apiKey,
                'Content-Type: application/json',
            ],
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 60,
        ]);
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($httpCode === 200) {
            $data = json_decode($response, true);
            return ['success' => true, 'data' => $data['data'] ?? $data, 'message' => 'Data extracted.'];
        }

        return ['success' => false, 'data' => null, 'message' => "Firecrawl extract failed (HTTP {$httpCode})."];
    }

    /**
     * Scrape competitor ad copy from a URL.
     */
    public static function scrapeCompetitorAds(string $url): array
    {
        $result = self::scrapeUrl($url, ['onlyMainContent' => true]);
        if (!$result['success']) return $result;

        $content = $result['content'] ?? '';
        $meta = $result['metadata'] ?? [];

        // Extract potential ad copy patterns
        $adPatterns = [];
        if (preg_match_all('/<h[1-6][^>]*>(.*?)<\/h[1-6]>/i', $content, $matches)) {
            $adPatterns['headlines'] = array_slice(array_map('strip_tags', $matches[1]), 0, 10);
        }
        if (preg_match_all('/<(?:p|span|div)[^>]*class="[^"]*(?:cta|button|cta-button|hero)[^"]*"[^>]*>(.*?)<\/(?:p|span|div)>/i', $content, $matches)) {
            $adPatterns['cta_texts'] = array_slice(array_map('strip_tags', $matches[1]), 0, 5);
        }

        return [
            'success'   => true,
            'title'     => $meta['title'] ?? '',
            'description' => $meta['description'] ?? '',
            'headlines' => $adPatterns['headlines'] ?? [],
            'cta_texts' => $adPatterns['cta_texts'] ?? [],
            'content'   => mb_substr(strip_tags($content), 0, 2000),
            'message'   => 'Competitor data scraped.',
        ];
    }

    // ─── Firecrawl API Calls ───

    private static function firecrawlScrape(string $url, string $apiKey, array $options): array
    {
        $payload = json_encode(array_merge([
            'url'             => $url,
            'onlyMainContent' => $options['onlyMainContent'] ?? true,
            'formats'         => ['markdown', 'html'],
        ], $options));

        $ch = curl_init(self::API_BASE . '/scrape');
        curl_setopt_array($ch, [
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => $payload,
            CURLOPT_HTTPHEADER     => [
                'Authorization: Bearer ' . $apiKey,
                'Content-Type: application/json',
            ],
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 30,
        ]);
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($httpCode === 200) {
            $data = json_decode($response, true);
            $page = $data['data'] ?? $data;
            return [
                'success'   => true,
                'content'   => $page['markdown'] ?? $page['html'] ?? '',
                'html'      => $page['html'] ?? '',
                'metadata'  => $page['metadata'] ?? [],
                'message'   => 'Page scraped successfully.',
            ];
        }

        return ['success' => false, 'content' => '', 'message' => "Firecrawl scrape failed (HTTP {$httpCode})."];
    }

    private static function firecrawlCrawl(string $baseUrl, string $apiKey, int $limit): array
    {
        $payload = json_encode([
            'url'    => $baseUrl,
            'limit'  => $limit,
            'scrapeOptions' => ['onlyMainContent' => true, 'formats' => ['markdown']],
        ]);

        $ch = curl_init(self::API_BASE . '/crawl');
        curl_setopt_array($ch, [
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => $payload,
            CURLOPT_HTTPHEADER     => [
                'Authorization: Bearer ' . $apiKey,
                'Content-Type: application/json',
            ],
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 30,
        ]);
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($httpCode === 200) {
            $data = json_decode($response, true);
            return [
                'success'  => true,
                'job_id'   => $data['id'] ?? '',
                'pages'    => $data['data'] ?? [],
                'message'  => 'Crawl job started. Poll for results.',
            ];
        }

        return ['success' => false, 'pages' => [], 'message' => "Firecrawl crawl failed (HTTP {$httpCode})."];
    }

    // ─── cURL Fallback (no API key) ───

    private static function fallbackScrape(string $url): array
    {
        // SSRF guard: never let an authenticated user point the server at
        // loopback, RFC1918, link-local or cloud-metadata addresses.
        if (!function_exists('is_public_http_url') || !is_public_http_url($url)) {
            return [
                'success' => false,
                'content' => '',
                'message' => 'That address is not publicly reachable. Enter a normal public website URL.',
            ];
        }

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => false,   // no open redirect chain
            CURLOPT_TIMEOUT        => 15,
            CURLOPT_MAXREDIRS      => 3,
            CURLOPT_USERAGENT      => 'AdsboosterBot/1.0',
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
            CURLOPT_PROTOCOLS      => CURLPROTO_HTTP | CURLPROTO_HTTPS,
        ]);
        $html = curl_exec($ch);
        $httpCode = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $redirect = (string)curl_getinfo($ch, CURLINFO_REDIRECT_URL);
        curl_close($ch);

        // Manually follow a small number of redirects, re-validating each hop.
        $hops = 0;
        while ($httpCode >= 300 && $httpCode < 400 && $redirect !== '' && $hops < 3) {
            if (!is_public_http_url($redirect)) {
                return ['success' => false, 'content' => '', 'message' => 'Redirect target is not publicly reachable.'];
            }
            $hops++;
            $ch = curl_init($redirect);
            curl_setopt_array($ch, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_FOLLOWLOCATION => false,
                CURLOPT_TIMEOUT        => 15,
                CURLOPT_USERAGENT      => 'AdsboosterBot/1.0',
                CURLOPT_SSL_VERIFYPEER => true,
                CURLOPT_SSL_VERIFYHOST => 2,
                CURLOPT_PROTOCOLS      => CURLPROTO_HTTP | CURLPROTO_HTTPS,
            ]);
            $html = curl_exec($ch);
            $httpCode = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $redirect = (string)curl_getinfo($ch, CURLINFO_REDIRECT_URL);
            curl_close($ch);
        }

        if ($httpCode !== 200 || !$html) {
            return ['success' => false, 'content' => '', 'message' => "Failed to fetch URL (HTTP {$httpCode})."];
        }

        $title = '';
        if (preg_match('/<title[^>]*>(.*?)<\/title>/is', $html, $m)) {
            $title = trim(strip_tags($m[1]));
        }

        $description = '';
        if (preg_match('/<meta[^>]*name=["\']description["\'][^>]*content=["\'](.*?)["\']/is', $html, $m)) {
            $description = trim($m[1]);
        }

        $content = strip_tags($html);
        $content = preg_replace('/\s+/', ' ', $content);
        $content = trim($content);

        return [
            'success'   => true,
            'content'   => mb_substr($content, 0, 5000),
            'metadata'  => ['title' => $title, 'description' => $description],
            'message'   => 'Page scraped (basic mode — add Firecrawl API key for better results).',
        ];
    }
}
