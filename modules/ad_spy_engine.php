<?php
declare(strict_types=1);

/**
 * Competitor Ad Spy & Reverse-Engineering Engine
 * Adsbooster.net
 *
 * ── HONESTY CONTRACT ────────────────────────────────────────────────────────
 * This engine reports ONLY what was actually observed:
 *
 *   • "Observed" content (hooks, messaging signals) is quoted verbatim from
 *     the competitor page that was fetched, and every messaging signal
 *     carries the line of copy that proves it.
 *   • Nothing is observed when nothing was fetched. A run without a public
 *     website URL (or a failed fetch) produces NO hooks and NO signals
 *     instead of an invented competitor profile.
 *   • Counter-campaigns are DRAFTS. They never contain invented proof — no
 *     invented customer counts, discounts, ROI, multiples or time-savings.
 *     Every claim we cannot verify for the user's own product stays a
 *     bracketed placeholder the user must replace with real evidence.
 * ───────────────────────────────────────────────────────────────────────────
 */

class AdSpyEngine
{
    /**
     * Copy patterns that assert a result we cannot verify for the user's
     * product. Generated ad copy must never contain them.
     */
    private const UNVERIFIABLE_CLAIM_PATTERNS = [
        '/\b\d[\d,]*\+?\s*(?:creators|customers|users|teams|brands|clients|companies)\b/i',
        '/\b\d{1,2}\s*%[^\n.]{0,24}\b(?:off|discount|deal|switcher|save|bonus|first pair)\b/i',
        '/\b\d+(?:\.\d+)?\s*x\b\s*(?:better|faster|more|results|returns)?/i',
        '/\bguarantee[ds]?\s+(?:roi|results?|returns?)/i',
        '/\bsaved me\s+\d+\s+hours?\b/i',
        '/\b\d[\d,]*\s+free\b/i',
        '/\b\d+[-\s]day\s+(?:risk[-\s]?free\s+)?trial\b/i',
        '/\btransfer\b[^.]{0,40}\bin under \d+ minutes?\b/i',
        '/\bfirst \d+ campaigns? for free\b/i',
    ];

    /**
     * Words that carry no signal when counting recurring themes.
     */
    private const STOP_WORDS = [
        'about', 'after', 'again', 'against', 'along', 'also', 'always', 'because', 'been',
        'before', 'being', 'between', 'both', 'cannot', 'could', 'does', 'doing', 'done',
        'down', 'during', 'each', 'even', 'every', 'from', 'have', 'having', 'here', 'into',
        'just', 'like', 'make', 'many', 'more', 'most', 'much', 'must', 'only', 'other',
        'over', 'same', 'should', 'some', 'such', 'than', 'that', 'their', 'them', 'then',
        'there', 'these', 'they', 'this', 'those', 'through', 'under', 'until', 'very',
        'were', 'what', 'when', 'where', 'which', 'while', 'will', 'with', 'would', 'your',
    ];

    /**
     * Messaging signals detected in the observed copy. Each signal only
     * appears in a brief when observed copy actually matches it.
     */
    private const SIGNALS = [
        'numeric_proof' => [
            'label' => 'Numeric proof',
            'desc' => 'They lead with numbers. Answer with a number you can evidence.',
            'match' => '/\d/',
        ],
        'offer' => [
            'label' => 'Offer & pricing',
            'desc' => 'Pricing or a discount carries their message. Answer with your real offer terms.',
            'match' => '/(\d+\s*%|\$|£|€|\b(off|discount|free|deal|save|bonus|pricing|plans?)\b)/i',
        ],
        'urgency' => [
            'label' => 'Urgency',
            'desc' => 'They push for a fast decision. Answer with a reason to act now that is true.',
            'match' => '/(\b(now|today|hurry|limited|last chance|ends|don.t wait|don.t miss|act fast|while you can)\b)/i',
        ],
        'social_proof' => [
            'label' => 'Social proof',
            'desc' => 'Customers or teams are part of their pitch. Answer with proof you can show.',
            'match' => '/(\b(join|trusted by|customers?|teams?|brands?|creators?|users?|reviews?|rated|clients?)\b|\d[\d,]*\+)/i',
        ],
        'pain' => [
            'label' => 'Pain & friction',
            'desc' => 'They name a problem. Answer with the problem you solve better.',
            'match' => '/(\b(struggle|fail|waste|lose|risk|problem|pain|tired|frustrat|hidden fee|complicated)\b)/i',
        ],
        'benefit' => [
            'label' => 'Benefit promise',
            'desc' => 'They promise an outcome. Answer with yours, measured.',
            'match' => '/(\b(grow|increase|boost|save|double|scale|faster|easier|revenue|results?|growth)\b)/i',
        ],
    ];

    /**
     * Analyze a competitor: fetch what we can, then describe only that.
     */
    public static function analyzeCompetitor(int $userId, string $competitorName, string $competitorUrl, string $platform = 'meta'): array
    {
        $cleanName = self::cleanName($competitorName, $competitorUrl);
        $urlInfo = self::normalizeUrl($competitorUrl);
        $displayUrl = $urlInfo['url'] !== '' ? $urlInfo['url'] : trim($competitorUrl);

        $observation = $urlInfo['requested']
            ? self::observe($urlInfo['url'])
            : self::notObserved($urlInfo['reason']);

        $brief = self::buildBrief($cleanName, $displayUrl, $platform, $observation);

        $analysisId = db()->insert('competitor_ad_analyses', [
            'user_id' => $userId,
            'competitor_name' => $cleanName,
            'competitor_url' => $displayUrl,
            'platform' => $platform,
            'scraped_ads_count' => $brief['observed_line_count'],
            'extracted_hooks' => json_encode($brief['extracted_hooks']),
            'extracted_angles' => json_encode($brief['extracted_angles']),
            'target_audience_insights' => json_encode($brief['target_audience_insights']),
            'counter_creatives' => json_encode($brief['counter_creatives']),
        ]);

        return array_merge($brief, [
            'id' => $analysisId,
            'competitor_name' => $cleanName,
            'competitor_url' => $displayUrl,
            'platform' => $platform,
            'scraped_ads_count' => $brief['observed_line_count'],
            'generated_count' => count($brief['extracted_hooks'])
                + count($brief['extracted_angles'])
                + count($brief['counter_creatives']),
        ]);
    }

    /**
     * Build the brief from an observation. Pure: no network, no database.
     *
     * @param array<string,mixed> $observation
     * @return array<string,mixed>
     */
    public static function buildBrief(string $competitorName, string $competitorUrl, string $platform, array $observation): array
    {
        $cleanName = self::cleanName($competitorName, $competitorUrl);
        $observed = ($observation['status'] ?? '') === 'observed';

        $lines = [];
        if ($observed) {
            foreach ((array)($observation['copy_lines'] ?? []) as $line) {
                $line = trim((string)$line);
                if ($line === '' || in_array($line, $lines, true)) {
                    continue;
                }
                $lines[] = $line;
            }
        }

        $hooks = [];
        foreach ($lines as $line) {
            $length = mb_strlen($line);
            if ($length < 8 || $length > 220) {
                continue;
            }
            $hooks[] = ['text' => $line, 'source' => 'observed'];
            if (count($hooks) >= 12) {
                break;
            }
        }

        $angles = [];
        if ($observed) {
            foreach (self::SIGNALS as $key => $signal) {
                $evidence = [];
                foreach ($lines as $line) {
                    if (preg_match($signal['match'], $line) === 1) {
                        $evidence[] = $line;
                        if (count($evidence) >= 3) {
                            break;
                        }
                    }
                }
                if ($evidence === []) {
                    continue;
                }
                $angles[] = [
                    'key' => $key,
                    'angle' => $signal['label'],
                    'desc' => $signal['desc'],
                    'basis' => 'observed',
                    'signal_count' => count($evidence),
                    'evidence' => $evidence,
                ];
            }
        }

        $themes = $observed ? self::themes($lines) : [];

        $counterCreatives = self::buildCounterCreatives($cleanName, $angles, $observed);

        return [
            'data_source' => $observed ? 'observed_page' : 'template_only',
            'observed_line_count' => count($lines),
            'extracted_hooks' => $hooks,
            'extracted_angles' => $angles,
            'counter_creatives' => $counterCreatives,
            'target_audience_insights' => [
                'data_source' => $observed ? 'observed_page' : 'template_only',
                'observed' => [
                    'status' => (string)($observation['status'] ?? 'not_requested'),
                    'source_url' => (string)($observation['source_url'] ?? ''),
                    'scraped_at' => (string)($observation['scraped_at'] ?? ''),
                    'page_title' => (string)($observation['page_title'] ?? ''),
                    'meta_description' => (string)($observation['meta_description'] ?? ''),
                    'message' => (string)($observation['message'] ?? ''),
                    'copy_line_count' => count($lines),
                    'themes' => $themes,
                ],
                'format_recommendations' => self::formatRecommendations(),
            ],
        ];
    }

    /**
     * Does this copy assert a result we cannot verify?
     */
    public static function containsUnverifiableClaim(string $text): bool
    {
        foreach (self::UNVERIFIABLE_CLAIM_PATTERNS as $pattern) {
            if (preg_match($pattern, $text) === 1) {
                return true;
            }
        }
        return false;
    }

    /**
     * Replace any unverifiable claim with an explicit placeholder.
     */
    private static function scrubUnverifiableClaims(string $text): string
    {
        foreach (self::UNVERIFIABLE_CLAIM_PATTERNS as $pattern) {
            $text = (string)preg_replace($pattern, '[verified claim]', $text);
        }
        return $text;
    }

    /**
     * Draft counter-campaigns. Grounded drafts answer an observed signal;
     * generic drafts are used when nothing was observed.
     *
     * @param array<int,array<string,mixed>> $angles
     * @return array<int,array<string,mixed>>
     */
    private static function buildCounterCreatives(string $competitorName, array $angles, bool $observed): array
    {
        $drafts = [];

        foreach ($angles as $angle) {
            $evidence = (string)($angle['evidence'][0] ?? '');
            $drafts[] = [
                'title' => 'Counter to their "' . (string)$angle['angle'] . '" signal',
                'headline' => '[Your verified proof] vs ' . $competitorName . "'s " . lcfirst((string)$angle['angle']) . '.',
                'hook' => 'We observed: "' . $evidence . '" — here is our number.',
                'body' => 'Replace this with a measured result you can evidence: [your metric vs their metric, with source and date].',
                'cta' => 'See the comparison',
                'requires' => ['your verified proof', 'your metric vs their metric', 'source and date'],
                'basis' => 'observed',
            ];
        }

        $generics = self::genericDrafts($competitorName);
        for ($i = 0; count($drafts) < 5 && $i < count($generics); $i++) {
            $drafts[] = $generics[$i];
        }

        foreach ($drafts as &$draft) {
            foreach (['title', 'headline', 'hook', 'body', 'cta'] as $field) {
                $draft[$field] = self::scrubUnverifiableClaims((string)$draft[$field]);
            }
        }
        unset($draft);

        return $drafts;
    }

    /**
     * Safe draft templates: no invented proof, only placeholders.
     *
     * @return array<int,array<string,mixed>>
     */
    private static function genericDrafts(string $competitorName): array
    {
        return [
            [
                'title' => '1. The Clear Alternative',
                'headline' => 'A simpler way to [your outcome] than ' . $competitorName . '.',
                'hook' => 'You needed [their job]. We built the straightforward answer.',
                'body' => $competitorName . ' handles [their job]. We focus on [your job] — [one verifiable differentiator].',
                'cta' => 'See the side-by-side breakdown',
                'requires' => ['your outcome', 'their job', 'your job', 'one verifiable differentiator'],
                'basis' => 'template',
            ],
            [
                'title' => '2. The Proof-First Pitch',
                'headline' => '[Verified proof point] — without the ' . $competitorName . ' price tag.',
                'hook' => 'Numbers first, adjectives later.',
                'body' => 'One comparison, both sides measured: [your metric] for us, [their metric] for them, sourced [date].',
                'cta' => 'Get the full comparison',
                'requires' => ['verified proof point', 'your metric', 'their metric', 'date'],
                'basis' => 'template',
            ],
            [
                'title' => '3. The Myth-Buster',
                'headline' => 'The ' . $competitorName . ' feature nobody actually uses.',
                'hook' => 'Before you pay for [feature], check whether you will use it.',
                'body' => '[A specific, verifiable observation about their offer] — then see how [your product] handles the same job.',
                'cta' => 'Check the alternative',
                'requires' => ['feature', 'a specific, verifiable observation about their offer', 'your product'],
                'basis' => 'template',
            ],
            [
                'title' => '4. The Honest Comparison',
                'headline' => 'What ' . $competitorName . ' does well — and where we differ.',
                'hook' => 'We are not going to pretend they are bad. Here is the fair comparison.',
                'body' => 'They are strong at [their strength]. We are built for [your strength]. If theirs fits, keep it. If not, [your differentiator].',
                'cta' => 'Compare the two',
                'requires' => ['their strength', 'your strength', 'your differentiator'],
                'basis' => 'template',
            ],
            [
                'title' => '5. The Plain Offer',
                'headline' => 'Start on your terms: [your actual offer].',
                'hook' => 'No invented discount — just the terms we actually offer.',
                'body' => '[Actual offer terms]. [Real cancellation or refund policy].',
                'cta' => 'See the offer terms',
                'requires' => ['your actual offer', 'actual offer terms', 'real cancellation or refund policy'],
                'basis' => 'template',
            ],
        ];
    }

    /**
     * Formats we recommend testing. This is our guidance — never a claim
     * about what the competitor is running.
     *
     * @return array<int,array<string,string>>
     */
    private static function formatRecommendations(): array
    {
        return [
            ['format' => 'Video UGC (9:16)', 'why' => 'Cheapest way to test a new claim against their live creative.'],
            ['format' => 'Single image', 'why' => 'Fastest read of a headline; good for a proof-point test.'],
            ['format' => 'Carousel', 'why' => 'Lets you answer their feature list point by point.'],
        ];
    }

    /**
     * Recurring themes in the observed copy.
     *
     * @param array<int,string> $lines
     * @return array<int,string>
     */
    private static function themes(array $lines): array
    {
        $counts = [];
        foreach ($lines as $line) {
            $words = preg_split('/[^\p{L}\p{N}\'-]+/u', mb_strtolower($line)) ?: [];
            foreach ($words as $word) {
                $word = trim($word, "'-");
                if (mb_strlen($word) < 5 || in_array($word, self::STOP_WORDS, true)) {
                    continue;
                }
                $counts[$word] = ($counts[$word] ?? 0) + 1;
            }
        }
        if ($counts === []) {
            return [];
        }
        arsort($counts);
        $top = array_slice($counts, 0, 8, true);
        ksort($top);
        return array_keys($top);
    }

    /**
     * Fetch the competitor page and collect the copy we can quote.
     *
     * @return array<string,mixed>
     */
    private static function observe(string $url): array
    {
        require_once __DIR__ . '/firecrawl_scraper.php';

        try {
            $raw = FirecrawlScraper::scrapeCompetitorAds($url);
        } catch (\Throwable $e) {
            return self::notObserved('The page could not be fetched: ' . $e->getMessage(), $url);
        }

        if (empty($raw['success'])) {
            return self::notObserved((string)($raw['message'] ?? 'The page could not be fetched.'), $url);
        }

        $lines = [];
        $push = static function (string $text) use (&$lines): void {
            $text = trim(preg_replace('/\s+/u', ' ', $text) ?? '');
            if ($text === '' || !self::isUsableLine($text)) {
                return;
            }
            if (!in_array($text, $lines, true)) {
                $lines[] = $text;
            }
        };

        foreach ((array)($raw['headlines'] ?? []) as $headline) {
            $push((string)$headline);
        }
        foreach ((array)($raw['cta_texts'] ?? []) as $cta) {
            $push((string)$cta);
        }
        $push((string)($raw['title'] ?? ''));
        $push((string)($raw['description'] ?? ''));
        foreach (self::sentences((string)($raw['content'] ?? '')) as $sentence) {
            $push($sentence);
        }

        if ($lines === []) {
            return self::notObserved('The page loaded but no readable copy could be extracted from it.', $url);
        }

        return [
            'status' => 'observed',
            'source_url' => $url,
            'scraped_at' => gmdate('Y-m-d H:i:s') . ' UTC',
            'page_title' => trim((string)($raw['title'] ?? '')),
            'meta_description' => trim((string)($raw['description'] ?? '')),
            'copy_lines' => $lines,
            'message' => (string)($raw['message'] ?? 'Page fetched.'),
        ];
    }

    /**
     * @return array<string,mixed>
     */
    private static function notObserved(string $message, string $url = ''): array
    {
        return [
            'status' => $url !== '' ? 'failed' : 'not_requested',
            'source_url' => $url,
            'scraped_at' => '',
            'page_title' => '',
            'meta_description' => '',
            'copy_lines' => [],
            'message' => $message,
        ];
    }

    /**
     * Split a page body into candidate copy lines.
     *
     * @return array<int,string>
     */
    private static function sentences(string $content): array
    {
        $content = trim(preg_replace('/\s+/u', ' ', strip_tags($content)) ?? '');
        if ($content === '') {
            return [];
        }
        $parts = preg_split('/(?<=[.!?])\s+|\s*[|•]\s*/u', $content) ?: [];
        $out = [];
        foreach ($parts as $part) {
            $part = trim($part);
            if ($part !== '' && self::isUsableLine($part)) {
                $out[] = $part;
            }
            if (count($out) >= 60) {
                break;
            }
        }
        return $out;
    }

    /**
     * Reject markup, code and navigation noise.
     */
    private static function isUsableLine(string $text): bool
    {
        $length = mb_strlen($text);
        if ($length < 12 || $length > 240) {
            return false;
        }
        if (preg_match('/[{}<>]|\b(function|var|const|let|return|px|rem|vh|vw)\b\s*[:=(]|#[0-9a-f]{3,8}\b|\.(css|js|woff2?)\b|https?:\/\//i', $text) === 1) {
            return false;
        }
        $words = preg_split('/\s+/u', $text) ?: [];
        $words = array_values(array_filter($words, static fn($w) => preg_match('/\p{L}{3,}/u', $w) === 1));
        return count($words) >= 4;
    }

    /**
     * Turn user input into a URL we may fetch, or explain why we may not.
     *
     * @return array{requested:bool,url:string,reason:string}
     */
    private static function normalizeUrl(string $raw): array
    {
        $raw = trim($raw);
        if ($raw === '') {
            return ['requested' => false, 'url' => '', 'reason' => 'No website URL was supplied, so nothing was fetched.'];
        }
        if (str_starts_with($raw, '@')) {
            return ['requested' => false, 'url' => '', 'reason' => 'Social handles are not fetched. Enter the competitor website URL to quote their live copy.'];
        }
        if (!preg_match('~^https?://~i', $raw)) {
            if (preg_match('~^[a-z0-9][a-z0-9.-]*\.[a-z]{2,}(/\S*)?$~i', $raw) === 1) {
                $raw = 'https://' . $raw;
            } else {
                return ['requested' => false, 'url' => '', 'reason' => 'That does not look like a website URL, so nothing was fetched.'];
            }
        }
        if (!function_exists('is_public_http_url') || !is_public_http_url($raw)) {
            return ['requested' => false, 'url' => '', 'reason' => 'That address is not publicly reachable, so nothing was fetched.'];
        }
        return ['requested' => true, 'url' => $raw, 'reason' => ''];
    }

    /**
     * Display name for the competitor.
     */
    private static function cleanName(string $name, string $url): string
    {
        $name = trim($name);
        if ($name !== '') {
            return ucfirst($name);
        }
        $host = parse_url(trim($url), PHP_URL_HOST);
        return $host ? ucfirst((string)$host) : 'Competitor';
    }
}
