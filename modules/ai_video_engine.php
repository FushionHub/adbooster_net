<?php

declare(strict_types=1);

/**
 * AI Video Engine — Adsbooster.net
 *
 * Real multi-provider text-to-video / UGC video generation.
 *
 * Rendering backends (resolved in this order):
 *   1. `veo`       — Google Veo via the Gemini API (GEMINI_API_KEY)
 *   2. `replicate` — Replicate async predictions (REPLICATE_API_TOKEN)
 *   3. `fal`       — fal.ai queue (FAL_KEY)
 *   4. `runway`    — Runway Gen-3 Alpha Turbo (RUNWAY_API_KEY)
 *   5. `pollinations` — FREE, keyless. Renders photoreal scene stills via
 *                    the free Pollinations image API (api key not required)
 *                    and composites them into a real video in the browser
 *                    (same compositor as the built-in Storyboard Studio).
 *   6. `storyboard`— Built-in renderer. Photoreal scene stills come from
 *                    Google Gemini image models (Nano Banana 2) when a key
 *                    is configured, curated first-party stills otherwise;
 *                    the browser composites them (Ken Burns + captions +
 *                    voiceover) into a real MP4/WebM via canvas.captureStream
 *                    + MediaRecorder.
 *
 * Credits are only consumed once a real artifact exists.
 */

require_once __DIR__ . '/gemini_engine.php';

class AIVideoEngine
{
    public const UPLOAD_DIR  = 'uploads/videos';
    public const SCENE_DIR   = 'uploads/videos/scenes';

    /** Pollinations anonymous free tier exhausted for this PHP request (skip remaining scenes). */
    private static bool $pollinationsExhausted = false;

    // ───────────────────────────────────────────────────────────
    //  Catalogs
    // ───────────────────────────────────────────────────────────

    public static function getAvatars(): array
    {
        return [
            ['id' => 'lexi_genz',      'name' => 'Lexi R.',        'tag' => 'Gen Z Creator & TikTok Native',   'avatar_img' => 'assets/images/thumbs/client-img1.png',     'gender' => 'female', 'voice_id' => 'en_female_genz',      'vibe' => 'Energetic, authentic, fast-paced, high energy'],
            ['id' => 'marcus_saas',    'name' => 'Marcus Vance',   'tag' => 'SaaS Founder & Tech Reviewer',    'avatar_img' => 'assets/images/thumbs/team-img1.png',       'gender' => 'male',   'voice_id' => 'en_male_deep',        'vibe' => 'Authoritative, sharp, trustworthy, corporate-modern'],
            ['id' => 'elena_fitness',  'name' => 'Elena Cruz',     'tag' => 'Fitness & Wellness Influencer',   'avatar_img' => 'assets/images/thumbs/team-img2.png',       'gender' => 'female', 'voice_id' => 'en_female_energetic', 'vibe' => 'Passionate, dynamic, motivating, lifestyle-focused'],
            ['id' => 'david_tech',     'name' => 'David Chen',     'tag' => 'Gadgets & Unboxing Specialist',   'avatar_img' => 'assets/images/thumbs/team-img3.png',       'gender' => 'male',   'voice_id' => 'en_male_analytical',  'vibe' => 'Analytical, enthusiastic, product teardown expert'],
            ['id' => 'sophia_luxury',  'name' => 'Sophia Laurent', 'tag' => 'Luxury Fashion & Beauty Host',    'avatar_img' => 'assets/images/thumbs/team-img4.png',       'gender' => 'female', 'voice_id' => 'en_female_luxury',    'vibe' => 'Elegant, polished, aesthetic, high-end commercial'],
            ['id' => 'chloe_lifestyle', 'name' => 'Chloe Bennett',  'tag' => 'Mom & Home Lifestyle Creator',    'avatar_img' => 'assets/images/thumbs/team-three-img2.png', 'gender' => 'female', 'voice_id' => 'en_female_warm',      'vibe' => 'Relatable, warm, problem-solving, everyday user'],
        ];
    }

    public static function getVoices(): array
    {
        return [
            ['id' => 'en_female_genz',      'name' => 'Lexi (Gen-Z Dynamic Female)',      'gender' => 'female', 'tone' => 'Fast, upbeat, highly relatable TikTok/Reels tone', 'pitch' => 1.15, 'rate' => 1.08, 'lang' => 'en-US'],
            ['id' => 'en_male_deep',        'name' => 'Marcus (Executive Tech Male)',     'gender' => 'male',   'tone' => 'Deep, authoritative, founder-style clarity',      'pitch' => 0.85, 'rate' => 0.95, 'lang' => 'en-US'],
            ['id' => 'en_female_energetic', 'name' => 'Elena (High-Energy Fitness)',      'gender' => 'female', 'tone' => 'Motivational, crisp, vibrant high-conversion',    'pitch' => 1.05, 'rate' => 1.12, 'lang' => 'en-US'],
            ['id' => 'en_male_analytical',  'name' => 'David (Clear Tech Reviewer)',      'gender' => 'male',   'tone' => 'Analytical, engaging, authentic unboxing pace',   'pitch' => 0.95, 'rate' => 1.00, 'lang' => 'en-US'],
            ['id' => 'en_female_luxury',    'name' => 'Sophia (Luxury Studio Elegance)',  'gender' => 'female', 'tone' => 'Smooth, polished, European fashion accent',       'pitch' => 1.00, 'rate' => 0.90, 'lang' => 'en-GB'],
            ['id' => 'en_female_warm',      'name' => 'Chloe (Warm & Relatable Storyteller)', 'gender' => 'female', 'tone' => 'Friendly, trustworthy, home lifestyle storytelling', 'pitch' => 1.10, 'rate' => 0.98, 'lang' => 'en-US'],
        ];
    }

    public static function getCommercialStyles(): array
    {
        return [
            ['id' => 'tech_3d_render',     'name' => 'High-Tech 3D Cyber Teaser',   'desc' => 'Futuristic lighting, neon grid reflections, holographic particles.',  'sample_thumb' => 'assets/images/thumbs/project-details-img1.png', 'prompt_suffix' => 'futuristic 3D product render, neon rim lighting, volumetric haze, holographic particles, dark obsidian studio, ultra detailed, cinematic ad'],
            ['id' => 'luxury_minimalist',  'name' => 'Luxury Studio Minimalist',    'desc' => 'Slow cinematic pans, architectural shadows, matte obsidian and gold.', 'sample_thumb' => 'assets/images/thumbs/project-details-img2.png', 'prompt_suffix' => 'luxury minimalist studio photography, matte black and gold palette, soft architectural shadows, editorial fashion ad, elegant negative space'],
            ['id' => 'cinematic_macro',    'name' => 'Slow-Motion Macro Teardown',  'desc' => '120fps ultra-closeups of raw materials, craftsmanship and detail.',    'sample_thumb' => 'assets/images/thumbs/service-details-img1.png', 'prompt_suffix' => 'extreme macro closeup, shallow depth of field, raw material texture, craftsmanship detail, cinematic bokeh, high speed photography'],
            ['id' => 'lifestyle_storyboard', 'name' => 'Modern Lifestyle In-Action', 'desc' => 'Natural sunlight, lens flare, handheld transitions, human emotion.',   'sample_thumb' => 'assets/images/thumbs/blog-details-img2.png',    'prompt_suffix' => 'modern lifestyle photography, natural sunlight, subtle lens flare, candid authentic moment, warm color grade, handheld documentary feel'],
        ];
    }

    public static function getCaptionStyles(): array
    {
        return [
            ['id' => 'tiktok_glow',    'name' => 'TikTok Neon Glow',       'bg' => '#000000', 'color' => '#c8ff00', 'stroke' => '#00ffff'],
            ['id' => 'beast_yellow',   'name' => 'Mr. Beast Punchy Yellow', 'bg' => '#111111', 'color' => '#ffe600', 'stroke' => '#000000'],
            ['id' => 'minimal_white',  'name' => 'Minimal Clean White',    'bg' => 'rgba(0,0,0,0.6)', 'color' => '#ffffff', 'stroke' => 'none'],
            ['id' => 'cyber_cyan',     'name' => 'Cyber Gradient Cyan',    'bg' => '#080c14', 'color' => '#00f2fe', 'stroke' => '#4facfe'],
        ];
    }

    public static function getAudioTracks(): array
    {
        return [
            ['id' => 'viral_lofi_beat',    'name' => 'Viral Lo-Fi Chill Hop',   'bpm' => 92,  'genre' => 'Lo-Fi / Chill',    'wave' => 'sine'],
            ['id' => 'upbeat_electronic',  'name' => 'High-Energy Drop Beat',   'bpm' => 124, 'genre' => 'Electronic',       'wave' => 'square'],
            ['id' => 'cinematic_hook',     'name' => 'Cinematic Suspense Build', 'bpm' => 100, 'genre' => 'Cinematic',        'wave' => 'sawtooth'],
            ['id' => 'corporate_ambient',  'name' => 'Modern Minimal Acoustic', 'bpm' => 110, 'genre' => 'Acoustic / Tech',  'wave' => 'triangle'],
        ];
    }

    public static function getVoiceById(string $id): array
    {
        foreach (self::getVoices() as $v) {
            if ($v['id'] === $id) return $v;
        }
        return self::getVoices()[0];
    }

    public static function getAvatarById(string $id): array
    {
        foreach (self::getAvatars() as $avatar) {
            if ($avatar['id'] === $id) return $avatar;
        }
        return self::getAvatars()[0];
    }

    public static function getAspectRatios(): array
    {
        return [
            '9:16' => ['w' => 720,  'h' => 1280, 'label' => 'Vertical — TikTok / Reels / Shorts'],
            '16:9' => ['w' => 1280, 'h' => 720,  'label' => 'Landscape — YouTube / Banner'],
            '1:1'  => ['w' => 960,  'h' => 960,  'label' => 'Square — Instagram / Facebook'],
            '4:5'  => ['w' => 864,  'h' => 1080, 'label' => 'Portrait — Instagram Feed'],
        ];
    }

    // ───────────────────────────────────────────────────────────
    //  Providers
    // ───────────────────────────────────────────────────────────

    /**
     * All supported rendering backends.
     */
    public static function getProviders(): array
    {
        return [
            'storyboard' => [
                'label'     => 'Storyboard Studio (built-in, free)',
                'kind'      => 'storyboard',
                'async'     => false,
                'configured' => true,
                'note'      => 'Renders photoreal scene stills and composites a real video in your browser.',
            ],
            'veo' => [
                'label'     => 'Google Veo',
                'kind'      => 'gemini',
                'async'     => true,
                'configured' => self::hasKey('GEMINI_API_KEY', 'gemini_api_key'),
                'endpoint'  => 'https://generativelanguage.googleapis.com/v1beta/models/veo-3.0-generate-preview:predictLongRunning',
            ],
            'replicate' => [
                'label'     => 'Replicate (Kling / Wan / Minimax)',
                'kind'      => 'replicate',
                'async'     => true,
                'configured' => self::hasKey('REPLICATE_API_TOKEN', 'replicate_api_token'),
                'endpoint'  => 'https://api.replicate.com/v1/predictions',
            ],
            'fal' => [
                'label'     => 'fal.ai (Kling 2.5 / Veo)',
                'kind'      => 'fal',
                'async'     => true,
                'configured' => self::hasKey('FAL_KEY', 'fal_key'),
                'endpoint'  => 'https://queue.fal.run/fal-ai/kling-video/v2.5-turbo/pro/text-to-video',
            ],
            'runway' => [
                'label'     => 'Runway Gen-3 Alpha Turbo',
                'kind'      => 'runway',
                'async'     => true,
                'configured' => self::hasKey('RUNWAY_API_KEY', 'runway_api_key'),
                'endpoint'  => 'https://api.dev.runwayml.com/v1/text_to_video',
            ],
            'pollinations' => [
                'label'     => 'Pollinations (Free)',
                'kind'      => 'pollinations',
                'async'     => false,
                'configured' => true, // keyless and free — no API key required, ever
                'note'      => 'Free, keyless rendering: photoreal scene stills via the Pollinations API, composited into a real video in your browser.',
            ],
        ];
    }

    private static function hasKey(string $constant, string $dbKey): bool
    {
        return self::key($constant, $dbKey) !== '';
    }

    private static function key(string $constant, string $dbKey): string
    {
        return function_exists('api_key') ? api_key($constant, $dbKey, '') : (defined($constant) ? (string)constant($constant) : '');
    }

    /**
     * Resolve the active provider.
     *
     * Priority: explicit `?provider=` → `ai_video_provider` site setting →
     * Google Veo when a Gemini key is configured (full Google pipeline) →
     * the built-in Storyboard Studio (no key required, always produces a
     * real video). Cloud submissions that fail (revoked key, quota…) still
     * fall back to Storyboard automatically inside createVideoCreative().
     */
    public static function resolveProvider(?string $requested = null): string
    {
        $providers = self::getProviders();

        if ($requested !== null && $requested !== '' && isset($providers[$requested])) {
            if (!empty($providers[$requested]['configured'])) {
                return $requested;
            }
        }

        if (function_exists('get_setting')) {
            try {
                $pref = get_setting('ai_video_provider', '');
                if ($pref !== '' && isset($providers[$pref]) && !empty($providers[$pref]['configured'])) {
                    return $pref;
                }
            } catch (\Throwable $e) { /* DB unavailable */
            }
        }

        // Default to the full Google pipeline whenever the key is present.
        if (!empty($providers['veo']['configured'])) {
            return 'veo';
        }

        return 'storyboard';
    }

    // ───────────────────────────────────────────────────────────
    //  Script + storyboard authoring
    // ───────────────────────────────────────────────────────────

    public static function generateScript(string $productName, string $benefits, string $avatarId, int $targetDuration = 30): array
    {
        $targetDuration = max(10, min(120, $targetDuration));

        $groqKey = self::key('GROQ_API_KEY', 'groq_api_key');
        $avatar  = self::getAvatarById($avatarId);

        if ($groqKey !== '') {
            try {
                $prompt = "You are a world-class direct-response UGC scriptwriter.\n"
                    . "Write a 3-part UGC ad script for the product \"{$productName}\".\n"
                    . "Key benefits/offer: " . ($benefits !== '' ? $benefits : 'fast results, easy to use, great value') . ".\n"
                    . "Creator persona: {$avatar['name']} — {$avatar['vibe']}.\n"
                    . "Target spoken length: about {$targetDuration} seconds.\n"
                    . "Return ONLY minified JSON: {\"hook\":\"...\",\"body\":\"...\",\"cta\":\"...\"}\n"
                    . "Rules: hook is <=14 words and pattern-interrupts. body is 2-4 spoken sentences. "
                    . "cta is one punchy sentence with an implied action. No emojis, no quotes, no markdown.";
                $resp = self::groqChat($prompt, $groqKey, 0.9, 700);
                $json = self::extractJson($resp);
                if ($json !== null && !empty($json['hook']) && !empty($json['body'])) {
                    $hook = trim((string)$json['hook']);
                    $body = trim((string)$json['body']);
                    $cta  = trim((string)($json['cta'] ?? 'Tap the link below to try it risk-free today.'));
                    return [
                        'hook' => $hook,
                        'body' => $body,
                        'cta' => $cta,
                        'full_script' => "{$hook} {$body} {$cta}",
                        'estimated_seconds' => $targetDuration,
                        'source' => 'groq',
                    ];
                }
            } catch (\Throwable $e) { /* fall through to templates */
            }
        }

        $hookOptions = [
            "Stop scrolling if you are tired of {$productName} alternatives that never work!",
            "I almost didn't post this, but this one thing completely changed how I use {$productName}.",
            "Okay, honest review time. Is {$productName} actually worth the hype?",
            "3 reasons why everyone is obsessing over {$productName} right now.",
        ];

        if ($targetDuration <= 15) {
            $body = "Here is the bottom line: {$benefits}. It takes two minutes and cuts your work in half.";
        } elseif ($targetDuration <= 45) {
            $body = "Here is the truth: {$benefits}. I tested it for two weeks straight and the results were ridiculous. It saves hours of frustration and delivers exactly what it promised without any guesswork.";
        } elseif ($targetDuration <= 90) {
            $body = "Let me break down why this is blowing up. Traditional methods take hours and cost a fortune, and they fail when you need them most. With {$productName}, {$benefits}. Setup takes under sixty seconds, the workflow runs in the background, and support actually answers.";
        } else {
            $body = "Here is my full two-minute transparent breakdown of {$productName}. Why I switched: {$benefits}. I put it against the top three competitors and in every test it was faster, easier, and delivered better return. Whether you are a beginner or running an agency, the automation removes the friction.";
        }

        $ctaOptions = [
            "Hit the link below right now to grab yours before the flash sale ends.",
            "Tap below to test it risk-free today and see the results yourself.",
            "Check the link in bio right now for an exclusive discount.",
        ];

        $hook = $hookOptions[array_rand($hookOptions)];
        $cta  = $ctaOptions[array_rand($ctaOptions)];

        return [
            'hook' => $hook,
            'body' => $body,
            'cta' => $cta,
            'full_script' => "{$hook} {$body} {$cta}",
            'estimated_seconds' => $targetDuration,
            'source' => 'template',
        ];
    }

    public static function generateCommercialStoryboard(string $brandName, string $productConcept, string $styleId, int $targetDuration = 30): array
    {
        $targetDuration = max(10, min(120, $targetDuration));
        $style = null;
        foreach (self::getCommercialStyles() as $s) {
            if ($s['id'] === $styleId) {
                $style = $s;
                break;
            }
        }
        $suffix = $style['prompt_suffix'] ?? 'cinematic commercial product photography, dramatic studio lighting';

        $sceneCount = $targetDuration <= 15 ? 3 : ($targetDuration <= 45 ? 4 : 5);
        $groqKey = self::key('GROQ_API_KEY', 'groq_api_key');

        if ($groqKey !== '') {
            try {
                $prompt = "You are a commercial film director. Build a {$sceneCount}-scene storyboard for a {$targetDuration}-second cinematic ad.\n"
                    . "Brand: {$brandName}. Product concept: {$productConcept}. Visual style: {$suffix}.\n"
                    . "Return ONLY minified JSON: {\"scenes\":[{\"scene_number\":1,\"timing\":\"0:00 - 0:05\",\"visual_prompt\":\"detailed photographic scene description, no brand logos, no text in image\",\"narration\":\"spoken line\"}, ...]}\n"
                    . "Exactly {$sceneCount} scenes, sequential timings covering the full runtime. visual_prompt must be a concrete camera+subject+lighting description (12-30 words). narration must be short spoken lines.";
                $resp = self::groqChat($prompt, $groqKey, 0.8, 1100);
                $json = self::extractJson($resp);
                if ($json !== null && !empty($json['scenes']) && is_array($json['scenes'])) {
                    $scenes = [];
                    $i = 1;
                    foreach ($json['scenes'] as $sc) {
                        $scenes[] = [
                            'scene_number'  => $i,
                            'timing'        => (string)($sc['timing'] ?? ''),
                            'visual_prompt' => trim((string)($sc['visual_prompt'] ?? '')),
                            'narration'     => trim((string)($sc['narration'] ?? '')),
                        ];
                        $i++;
                    }
                    return [
                        'scenes' => $scenes,
                        'estimated_duration' => $targetDuration,
                        'aspect_ratio' => '16:9',
                        'style_id' => $styleId,
                        'source' => 'groq',
                    ];
                }
            } catch (\Throwable $e) { /* fall through */
            }
        }

        $per = (int)floor($targetDuration / $sceneCount);
        $templates = [
            "Dramatic dark studio reveal of the product with volumetric light shafts and floating dust particles",
            "Seamless slow rotation showing engineered precision, materials and micro detail",
            "High energy montage of real people achieving results with the product",
            "Macro texture closeup with rim lighting and shallow depth of field",
            "Hero pedestal shot with the product centred, cinematic reflections and soft glow",
            "Warm lifestyle scene, natural window light, product in everyday use",
            "Bold final packshot composition with negative space and premium contrast",
        ];

        $scenes = [];
        for ($i = 0; $i < $sceneCount; $i++) {
            $start = $i * $per;
            $end   = $i === $sceneCount - 1 ? $targetDuration : ($i + 1) * $per;
            $desc  = $templates[$i % count($templates)];
            $scenes[] = [
                'scene_number'  => $i + 1,
                'timing'        => sprintf('%d:%02d - %d:%02d', intdiv($start, 60), $start % 60, intdiv($end, 60), $end % 60),
                'visual_prompt' => "{$brandName} {$productConcept}: {$desc}, {$suffix}, 35mm lens, hyper detailed, 8k, no text, no watermark",
                'narration'     => $i === 0
                    ? "Meet {$brandName}."
                    : ($i === $sceneCount - 1 ? "Experience {$brandName} today." : "Built to outperform every alternative."),
            ];
        }

        return [
            'scenes' => $scenes,
            'estimated_duration' => $targetDuration,
            'aspect_ratio' => '16:9',
            'style_id' => $styleId,
            'source' => 'template',
        ];
    }

    // ───────────────────────────────────────────────────────────
    //  Scene plan for UGC talking-head videos
    // ───────────────────────────────────────────────────────────

    /**
     * Build the 3-act scene plan used by the Storyboard renderer.
     */
    public static function buildScenePlan(array $params): array
    {
        $videoType   = $params['video_type'] ?? 'ugc_talking_head';
        $hook        = trim($params['hook_text'] ?? '');
        $body        = trim($params['script_body'] ?? '');
        $cta         = trim($params['cta_text'] ?? '');
        $broll       = trim($params['broll_keyword'] ?? '');
        $duration    = max(10, min(120, (int)($params['duration_seconds'] ?? 30)));
        $aspect      = $params['aspect_ratio'] ?? '9:16';
        $avatarId    = $params['avatar_id'] ?? 'lexi_genz';
        $styleId     = $params['style_id'] ?? 'lifestyle_storyboard';

        $avatar = $videoType === 'commercial_cinematic'
            ? ['name' => 'Cinematic AI Director', 'avatar_img' => 'assets/images/thumbs/project-details-img1.png', 'vibe' => 'High-Production Commercial']
            : self::getAvatarById($avatarId);

        $styleSuffix = 'lifestyle documentary photography, natural light, authentic candid feel';
        foreach (self::getCommercialStyles() as $s) {
            if ($s['id'] === $styleId) {
                $styleSuffix = $s['prompt_suffix'];
                break;
            }
        }

        // Split script into 3 acts weighted by screen time (25% / 55% / 20%).
        $segments = [
            ['act' => 'hook', 'label' => 'HOOK',   'text' => $hook !== '' ? $hook : 'Stop scrolling — this changes everything.', 'share' => 0.25],
            ['act' => 'body', 'label' => 'BODY',   'text' => $body !== '' ? $body : 'It saves hours every single week and just works.', 'share' => 0.55],
            ['act' => 'cta',  'label' => 'CTA',    'text' => $cta !== ''  ? $cta  : 'Tap the link below to get started today.',          'share' => 0.20],
        ];

        if ($videoType === 'commercial_cinematic') {
            $segments = [
                ['act' => 'hook', 'label' => 'SCENE 1', 'text' => $hook !== '' ? $hook : 'The future has arrived.', 'share' => 0.30],
                ['act' => 'body', 'label' => 'SCENE 2', 'text' => $body !== '' ? $body : 'Engineered from the ground up for uncompromising performance.', 'share' => 0.45],
                ['act' => 'cta',  'label' => 'SCENE 3', 'text' => $cta !== ''  ? $cta  : 'Order now.', 'share' => 0.25],
            ];
        }

        $scenes = [];
        $elapsed = 0;
        foreach ($segments as $idx => $seg) {
            $len = ($idx === count($segments) - 1)
                ? max(2, $duration - $elapsed)
                : max(2, (int)round($duration * $seg['share']));

            $visualPrompt = self::sceneVisualPrompt($seg['act'], $videoType, $avatar, $styleSuffix, $broll, $seg['text']);

            $scenes[] = [
                'scene_number'  => $idx + 1,
                'act'           => $seg['act'],
                'label'         => $seg['label'],
                'start'         => $elapsed,
                'length'        => $len,
                'timing'        => sprintf('%d:%02d - %d:%02d', intdiv($elapsed, 60), $elapsed % 60, intdiv($elapsed + $len, 60), ($elapsed + $len) % 60),
                'caption'       => $seg['text'],
                'visual_prompt' => $visualPrompt,
                'zoom_from'     => $idx % 2 === 0 ? 1.0 : 1.18,
                'zoom_to'       => $idx % 2 === 0 ? 1.18 : 1.0,
                'pan'           => $idx % 3 === 0 ? 'right' : ($idx % 3 === 1 ? 'left' : 'up'),
            ];
            $elapsed += $len;
        }

        return [
            'scenes'             => $scenes,
            'duration'           => $duration,
            'aspect_ratio'       => $aspect,
            'avatar'             => $avatar['name'] ?? 'Creator',
            'avatar_img'         => $avatar['avatar_img'] ?? 'assets/images/thumbs/client-img1.png',
            'resolution'         => self::getAspectRatios()[$aspect] ?? self::getAspectRatios()['9:16'],
            'full_script'        => trim($hook . ' ' . $body . ' ' . $cta),
        ];
    }

    private static function sceneVisualPrompt(string $act, string $videoType, array $avatar, string $styleSuffix, string $broll, string $caption): string
    {
        $brollBit = $broll !== '' ? ", featuring {$broll}" : '';
        $vibe     = (string)($avatar['vibe'] ?? 'genuine excitement');

        if ($videoType === 'commercial_cinematic') {
            return match ($act) {
                'hook' => "Hero product reveal on a dark obsidian pedestal, dramatic volumetric spotlight, floating dust particles, slow dolly-in, {$styleSuffix}{$brollBit}, no text, no logo, no watermark, ultra detailed, 8k",
                'body' => "Detailed product showcase, slow orbit around the object revealing craftsmanship and materials, soft reflections, cinematic colour grade, {$styleSuffix}{$brollBit}, no text, no logo, no watermark, ultra detailed, 8k",
                default => "Final hero packshot, product centred with generous negative space, premium softbox lighting, subtle lens flare, {$styleSuffix}{$brollBit}, no text, no logo, no watermark, ultra detailed, 8k",
            };
        }

        return match ($act) {
            'hook' => "Authentic UGC style shot of a person holding and reacting to a product, direct to camera, {$vibe}, {$styleSuffix}{$brollBit}, shot on 35mm, natural skin texture, no text, no logo, no watermark",
            'body' => "Lifestyle scene showing the product in real everyday use, hands and close detail, candid authentic moment, {$styleSuffix}{$brollBit}, natural light, no text, no logo, no watermark",
            default => "Warm closeup of a person happily finishing their review while holding the product toward camera, {$styleSuffix}{$brollBit}, inviting composition with clear space for a call to action, no text, no logo, no watermark",
        };
    }

    // ───────────────────────────────────────────────────────────
    //  Scene stills: Gemini first, curated local stills as fallback
    // ───────────────────────────────────────────────────────────

    /**
     * Curated same-origin stills used when no Gemini key/quota is available.
     * Always returns a real, web-accessible image path so the storyboard
     * browser compositor always has first-party pixels to work with.
     */
    public static function scenePlaceholderStill(int $seed): string
    {
        $pool = [
            'assets/images/thumbs/about-five-img1.png',
            'assets/images/thumbs/team-details-img2.png',
            'assets/images/thumbs/blog-details-img2.png',
            'assets/images/thumbs/team-details-img3.png',
            'assets/images/thumbs/blog-details-img1.png',
        ];
        $pick = $pool[abs($seed) % count($pool)];
        // Skip missing files (partial uploads) — fall back to the first present one.
        if (!is_readable(ROOT_PATH . '/' . $pick)) {
            foreach ($pool as $candidate) {
                if (is_readable(ROOT_PATH . '/' . $candidate)) {
                    return $candidate;
                }
            }
        }
        return $pick;
    }

    /** Absolute path to a usable CA bundle, or '' when verification is impossible. */
    private static function caBundlePath(): string
    {
        if (function_exists('ca_bundle_found')) {
            return ca_bundle_found();
        }
        foreach ([(string)ini_get('curl.cainfo'), (string)ini_get('openssl.cafile')] as $p) {
            if ($p !== '' && is_readable($p)) {
                return $p;
            }
        }
        return '';
    }

    private static function caBundleAvailable(): bool
    {
        return self::caBundlePath() !== '';
    }

    // ───────────────────────────────────────────────────────────
    //  Create a video creative
    // ───────────────────────────────────────────────────────────

    /**
     * Start a video render. Returns a normalised job descriptor:
     *   status: 'rendering_client' | 'generating'
     * Never fabricates a video URL and never marks a row completed without an artifact.
     */
    public static function createVideoCreative(int $userId, array $params): array
    {
        $videoType      = $params['video_type'] ?? 'ugc_talking_head';
        $avatarId       = $params['avatar_id'] ?? 'lexi_genz';
        $voiceId        = $params['voice_id'] ?? 'en_female_energetic';
        $hookText       = trim($params['hook_text'] ?? '');
        $scriptBody     = trim($params['script_body'] ?? '');
        $ctaText        = trim($params['cta_text'] ?? '');
        $captionStyle   = $params['caption_style'] ?? 'tiktok_glow';
        $brollKeyword   = trim($params['broll_keyword'] ?? '');
        $audioTrack     = $params['audio_track'] ?? 'viral_lofi_beat';
        $aspectRatio    = $params['aspect_ratio'] ?? ($videoType === 'commercial_cinematic' ? '16:9' : '9:16');
        $durationSeconds = max(10, min(120, (int)($params['duration_seconds'] ?? 30)));
        $styleId        = $params['style_id'] ?? 'lifestyle_storyboard';

        if (!isset(self::getAspectRatios()[$aspectRatio])) {
            $aspectRatio = '9:16';
        }

        if ($hookText === '' && $scriptBody === '') {
            return ['error' => 'Add a hook or script before rendering.'];
        }

        $providerId = self::resolveProvider($params['provider'] ?? null);
        $provider   = self::getProviders()[$providerId];

        $plan = self::buildScenePlan([
            'video_type'      => $videoType,
            'hook_text'       => $hookText,
            'script_body'     => $scriptBody,
            'cta_text'        => $ctaText,
            'broll_keyword'   => $brollKeyword,
            'duration_seconds' => $durationSeconds,
            'aspect_ratio'    => $aspectRatio,
            'avatar_id'       => $avatarId,
            'style_id'        => $styleId,
        ]);

        $creditsUsed = self::creditCost($durationSeconds);

        $user = db()->fetchOne("SELECT ai_credits FROM users WHERE id = ?", [$userId]);
        if ((int)($user['ai_credits'] ?? 0) < $creditsUsed) {
            return ['error' => "Not enough AI credits. This render costs {$creditsUsed} credits."];
        }

        $thumb = $plan['avatar_img'];

        $metadata = [
            'video_type'   => $videoType,
            'avatar_name'  => $plan['avatar'],
            'provider'     => $providerId,
            'provider_label' => $provider['label'],
            'style_id'     => $styleId,
            'scenes'       => $plan['scenes'],
            'resolution'   => $plan['resolution']['w'] . 'x' . $plan['resolution']['h'],
            'full_script'  => $plan['full_script'],
            'started_at'   => date('c'),
        ];

        // ── Built-in storyboard renderer: generate scene stills, hand off to browser ──
        //    (also used by the FREE keyless `pollinations` render route)
        if ($provider['kind'] === 'storyboard' || $provider['kind'] === 'pollinations') {
            return self::createStoryboardRender($userId, $params, $plan, $videoType, $avatarId, $voiceId, $hookText, $scriptBody, $ctaText, $captionStyle, $brollKeyword, $audioTrack, $aspectRatio, $durationSeconds, $creditsUsed, $thumb, $metadata, $provider['label'], $provider['kind']);
        }

        // ── External async provider ──
        $submission = self::submitToProvider($providerId, $provider, [
            'prompt'      => $plan['full_script'],
            'scenes'      => $plan['scenes'],
            'aspect_ratio' => $aspectRatio,
            'duration'    => $durationSeconds,
            'resolution'  => $plan['resolution'],
        ]);

        if (isset($submission['error'])) {
            // The configured cloud renderer rejected the request (bad/revoked key,
            // quota, region…). Fall back to the built-in Storyboard Studio so the
            // user still gets a real video instead of a dead end.
            error_log("AIVideoEngine: {$providerId} submit failed — " . $submission['error'] . ' — falling back to storyboard');
            return self::createStoryboardRender($userId, $params, $plan, $videoType, $avatarId, $voiceId, $hookText, $scriptBody, $ctaText, $captionStyle, $brollKeyword, $audioTrack, $aspectRatio, $durationSeconds, $creditsUsed, $thumb, $metadata, 'storyboard (fallback after ' . $providerId . ' failed)');
        }

        $creativeId = self::insertRow($userId, [
            'avatar_id'       => $videoType === 'commercial_cinematic' ? 'commercial_cinematic' : $avatarId,
            'voice_id'        => $voiceId,
            'hook_text'       => $hookText ?: 'Untitled video',
            'script_body'     => $scriptBody,
            'cta_text'        => $ctaText,
            'caption_style'   => $captionStyle,
            'broll_keyword'   => $brollKeyword,
            'audio_track'     => $audioTrack,
            'video_url'       => '',
            'thumbnail_url'   => $thumb,
            'aspect_ratio'    => $aspectRatio,
            'duration_seconds' => $durationSeconds,
            'status'          => 'generating',
            'credits_used'    => $creditsUsed,
            'metadata'        => array_merge($metadata, [
                'task_id'      => $submission['task_id'] ?? '',
                'task_url'     => $submission['task_url'] ?? '',
                'prediction'   => $submission['prediction'] ?? null,
            ]),
        ]);

        return [
            'id'              => $creativeId,
            'video_type'      => $videoType,
            'status'          => 'generating',
            'provider'        => $providerId,
            'provider_label'  => $provider['label'],
            'task_id'         => $submission['task_id'] ?? '',
            'video_url'       => '',
            'thumbnail_url'   => $thumb,
            'hook_text'       => $hookText,
            'script_body'     => $scriptBody,
            'cta_text'        => $ctaText,
            'duration_seconds' => $durationSeconds,
            'caption_style'   => $captionStyle,
            'audio_track'     => $audioTrack,
            'aspect_ratio'    => $aspectRatio,
            'credits_used'    => $creditsUsed,
            'next_action'     => 'poll',
            'message'         => "Queued on {$provider['label']}. This usually takes 1-3 minutes.",
        ];
    }

    public static function creditCost(int $durationSeconds): int
    {
        return match (true) {
            $durationSeconds > 60 => 8,
            $durationSeconds > 30 => 5,
            default => 3,
        };
    }

    /**
     * Shared path for the built-in Storyboard renderer.
     *
     * Generates a photoreal still for every scene, stores the job as
     * `rendering_client`, and hands the scene list to the browser which
     * composites a real MP4/WebM via canvas.captureStream() + MediaRecorder.
     */
    /**
     * Generate a photoreal still for every scene in the plan.
     *
     * 1st choice: Google Gemini image models (Nano Banana 2 first) generate
     * the still server-side and store it same-origin, so the canvas stays
     * untainted and there are no CDN rate limits. When no key / no quota is
     * available, curated first-party stills keep the storyboard compositing
     * a real video regardless.
     */
    private static function generateSceneStills(array $plan, int $seed, string $route = 'storyboard'): array
    {
        if (function_exists('set_time_limit')) {
            @set_time_limit(300);
        }

        $res    = $plan['resolution'];
        $scenes = $plan['scenes'];

        require_once __DIR__ . '/gemini_engine.php';

        foreach ($scenes as $i => &$sc) {
            $sc['local_path'] = null;

            $generated = null;

            // FREE keyless route: Pollinations first, then Gemini, then curated stills.
            if ($route === 'pollinations' && !self::$pollinationsExhausted) {
                try {
                    $generated = self::pollinationsStill($sc['visual_prompt'], $res['w'], $res['h'], 'poll_' . $seed . '_' . ($i + 1));
                } catch (\Throwable $e) {
                    error_log('Pollinations scene still failed: ' . $e->getMessage());
                }
            }

            // Unified engine fallback: Gemini (when a key exists) and the
            // NVIDIA NIM image route — so scene stills render photoreal on
            // every route configuration. Pollinations is skipped on the
            // `pollinations` route (already tried above — burning the
            // free-tier quota twice helps nobody).
            if ($generated === null) {
                try {
                    $generated = GeminiEngine::generateImage(
                        $sc['visual_prompt'],
                        $res['w'],
                        $res['h'],
                        'scene_' . $seed . '_' . ($i + 1),
                        $route !== 'pollinations'
                    );
                } catch (\Throwable $e) {
                    error_log('Gemini scene still failed: ' . $e->getMessage());
                }
            }

            if ($generated !== null) {
                $sc['local_path']   = $generated['path'];
                $sc['local_url']    = $generated['url'];
                $sc['image_url']    = $generated['url'];
                $sc['image_source'] = $generated['model'] ?? $generated['mime'] ?? 'generated';
            } else {
                $placeholder = self::scenePlaceholderStill($seed + $i);
                $sc['local_path']   = $placeholder;
                $sc['local_url']    = BASE_URL . '/' . $placeholder;
                $sc['image_url']    = $sc['local_url'];
                $sc['image_source'] = 'local-placeholder';
            }
        }
        unset($sc);

        return $scenes;
    }

    /**
     * FREE, keyless scene still via the Pollinations image API
     * (https://image.pollinations.ai). No API key, no account — any HTTP
     * client can fetch a generated still, which we store same-origin so the
     * browser compositor never depends on a third-party host at render time.
     *
     * The anonymous free tier is rate-limited (currently ~1 image per 45s
     * per IP). We retry once with a short capped wait; when the window is
     * exhausted the route is skipped for the remaining scenes of this
     * request and the Gemini/curated-still fallbacks take over.
     *
     * @return array{path:string,url:string,mime:string,model:string}|null
     */
    private static function pollinationsStill(string $prompt, int $width, int $height, string $saveAs): ?array
    {
        if (self::$pollinationsExhausted) {
            return null;
        }

        $prompt = trim($prompt);
        if ($prompt === '') {
            return null;
        }

        $width  = max(256, min(1536, $width));
        $height = max(256, min(1536, $height));

        $url = 'https://image.pollinations.ai/prompt/' . rawurlencode($prompt)
            . '?width=' . $width . '&height=' . $height . '&nologo=true&safe=true';

        $resp = self::http('GET', $url, '', ['Accept: image/*']);

        // Rate-limited free tier: wait out the advertised window (capped), retry once.
        if ($resp['code'] === 402 || $resp['code'] === 429) {
            $wait = 0;
            if (preg_match('/Retry after (\d+)s/i', (string)$resp['body'], $m)) {
                $wait = (int)$m[1];
            }
            if ($wait > 0 && $wait <= 20) {
                sleep(min($wait, 20) + 1);
                $resp = self::http('GET', $url, '', ['Accept: image/*']);
            }
            if ($resp['code'] === 402 || $resp['code'] === 429) {
                // Window exhausted — stop burning attempts for this request.
                self::$pollinationsExhausted = true;
                error_log('Pollinations free-tier limit reached; falling back to local scene stills for the rest of this request.');
                return null;
            }
        }

        if ($resp['code'] < 200 || $resp['code'] >= 300) {
            return null;
        }

        $bytes = (string)$resp['body'];
        if (strlen($bytes) < 128 || !function_exists('getimagesizefromstring')) {
            return null;
        }

        $info = @getimagesizefromstring($bytes);
        if ($info === false || empty($info['mime']) || !str_starts_with((string)$info['mime'], 'image/')) {
            return null;
        }
        $mime = (string)$info['mime'];

        $dirRel = 'assets/uploads/ai/pollinations';
        $dirAbs = ROOT_PATH . '/' . $dirRel;
        if (!is_dir($dirAbs) && !@mkdir($dirAbs, 0755, true) && !is_dir($dirAbs)) {
            return null;
        }

        $ext = match ($mime) {
            'image/jpeg', 'image/jpg' => 'jpg',
            'image/webp'              => 'webp',
            default                   => 'png',
        };
        $name = preg_replace('/[^a-zA-Z0-9._-]/', '_', $saveAs);
        $name = ltrim((string)$name, '._');
        if ($name === '' || !str_ends_with(strtolower($name), '.' . $ext)) {
            $name = ($name !== '' ? preg_replace('/\.[a-z0-9]+$/i', '', $name) : 'poll') . '_' . bin2hex(random_bytes(5)) . '.' . $ext;
        }

        $path = $dirRel . '/' . $name;
        if (@file_put_contents(ROOT_PATH . '/' . $path, $bytes) === false) {
            return null;
        }
        @chmod(ROOT_PATH . '/' . $path, 0644);

        return [
            'path'  => $path,
            'url'   => BASE_URL . '/' . $path,
            'mime'  => $mime,
            'model' => 'pollinations-flux',
        ];
    }

    /**
     * Honest provenance label for the storyboard/pollinations scene set.
     */
    private static function deriveSceneSource(array $scenes): string
    {
        $hasPollinations  = false;
        $hasGeneratedOther = false;
        foreach ($scenes as $s) {
            $src = (string)($s['image_source'] ?? '');
            if (str_starts_with($src, 'pollinations') || str_starts_with($src, 'poll_')) {
                $hasPollinations = true;
            } elseif ($src !== 'local-placeholder') {
                $hasGeneratedOther = true;
            }
        }
        if ($hasPollinations) {
            return $hasGeneratedOther ? 'mixed-generated' : 'pollinations';
        }
        return $hasGeneratedOther ? 'gemini' : 'local-placeholder';
    }

    private static function createStoryboardRender(
        int $userId,
        array $params,
        array $plan,
        string $videoType,
        string $avatarId,
        string $voiceId,
        string $hookText,
        string $scriptBody,
        string $ctaText,
        string $captionStyle,
        string $brollKeyword,
        string $audioTrack,
        string $aspectRatio,
        int $durationSeconds,
        int $creditsUsed,
        string $thumb,
        array $metadata,
        string $providerLabel,
        string $providerId = 'storyboard'
    ): array {
        // Scene stills are fetched sequentially with backoff; give the
        // request room before PHP's default execution cap kills it mid-render.
        if (function_exists('set_time_limit')) {
            @set_time_limit(300);
        }

        $res    = $plan['resolution'];
        $seed   = random_int(1, 999999);
        $scenes = self::generateSceneStills($plan, $seed, $providerId);

        $metadata['provider']       = $providerId;
        $metadata['provider_label'] = $providerLabel;
        $metadata['scenes']         = $scenes;
        $metadata['seed']           = $seed;
        $metadata['scene_source']   = self::deriveSceneSource($scenes);

        if (!empty($scenes[0]['local_path'])) {
            $thumb = $scenes[0]['local_path'];
        }

        $creativeId = self::insertRow($userId, [
            'avatar_id'       => $videoType === 'commercial_cinematic' ? 'commercial_cinematic' : $avatarId,
            'voice_id'        => $voiceId,
            'hook_text'       => $hookText !== '' ? $hookText : 'Untitled video',
            'script_body'     => $scriptBody,
            'cta_text'        => $ctaText,
            'caption_style'   => $captionStyle,
            'broll_keyword'   => $brollKeyword,
            'audio_track'     => $audioTrack,
            'video_url'       => '',
            'thumbnail_url'   => $thumb,
            'aspect_ratio'    => $aspectRatio,
            'duration_seconds' => $durationSeconds,
            'status'          => 'rendering_client',
            'credits_used'    => $creditsUsed,
            'metadata'        => $metadata,
        ]);

        return [
            'id'              => $creativeId,
            'video_type'      => $videoType,
            'status'          => 'rendering_client',
            'provider'        => $providerId,
            'provider_label'  => $providerLabel,
            'video_url'       => '',
            'thumbnail_url'   => $thumb,
            'hook_text'       => $hookText,
            'script_body'     => $scriptBody,
            'cta_text'        => $ctaText,
            'duration_seconds' => $durationSeconds,
            'caption_style'   => $captionStyle,
            'audio_track'     => $audioTrack,
            'aspect_ratio'    => $aspectRatio,
            'credits_used'    => $creditsUsed,
            'scenes'          => $scenes,
            'resolution'      => $res,
            'full_script'     => $plan['full_script'],
            'next_action'     => 'client_render',
            'message'         => 'Scene assets ready. Rendering your video now — keep this tab open.',
        ];
    }

    /**
     * Re-render an existing creative in place — the engine behind the
     * studio's Edit (new params, fresh scenes) and Regenerate (same params,
     * fresh scenes) actions. The row keeps its id; `video_url` is replaced
     * when the browser finishes the new render and save_render lands.
     *
     * Returns the same payload shape as createVideoCreative() so the client
     * can drive the identical render pipeline.
     */
    public static function rebuildCreative(int $userId, int $creativeId, array $overrides = []): array
    {
        $row = db()->fetchOne(
            "SELECT * FROM ai_video_creatives WHERE id = ? AND user_id = ?",
            [$creativeId, $userId]
        );
        if (!$row) {
            return ['error' => 'Creative not found.'];
        }

        $meta = json_decode($row['metadata'] ?? '{}', true) ?: [];

        $videoType       = (string)($overrides['video_type']       ?? $meta['video_type'] ?? ($row['avatar_id'] === 'commercial_cinematic' ? 'commercial_cinematic' : 'ugc_talking_head'));
        $avatarId        = (string)($overrides['avatar_id']        ?? $row['avatar_id']    ?? 'lexi_genz');
        $voiceId         = (string)($overrides['voice_id']         ?? $row['voice_id']     ?? 'en_female_energetic');
        $hookText        = trim((string)($overrides['hook_text']   ?? $row['hook_text']));
        $scriptBody      = trim((string)($overrides['script_body'] ?? $row['script_body']));
        $ctaText         = trim((string)($overrides['cta_text']    ?? $row['cta_text']));
        $captionStyle    = (string)($overrides['caption_style']    ?? $row['caption_style'] ?? 'tiktok_glow');
        $brollKeyword    = trim((string)($overrides['broll_keyword'] ?? $row['broll_keyword']));
        $audioTrack      = (string)($overrides['audio_track']      ?? $row['audio_track']   ?? 'viral_lofi_beat');
        $aspectRatio     = (string)($overrides['aspect_ratio']     ?? $row['aspect_ratio']  ?? '9:16');
        $durationSeconds = max(10, min(120, (int)($overrides['duration_seconds'] ?? $row['duration_seconds'] ?? 30)));
        $styleId         = (string)($overrides['style_id']         ?? $meta['style_id']     ?? 'lifestyle_storyboard');

        if (!isset(self::getAspectRatios()[$aspectRatio])) {
            $aspectRatio = '9:16';
        }

        if ($hookText === '' && $scriptBody === '') {
            return ['error' => 'Add a hook or script before rendering.'];
        }

        $providerId = self::resolveProvider($overrides['provider'] ?? null);
        $provider   = self::getProviders()[$providerId];

        $plan = self::buildScenePlan([
            'video_type'       => $videoType,
            'hook_text'        => $hookText,
            'script_body'      => $scriptBody,
            'cta_text'         => $ctaText,
            'broll_keyword'    => $brollKeyword,
            'duration_seconds' => $durationSeconds,
            'aspect_ratio'     => $aspectRatio,
            'avatar_id'        => $avatarId,
            'style_id'         => $styleId,
        ]);

        $creditsUsed = self::creditCost($durationSeconds);

        $user = db()->fetchOne("SELECT ai_credits FROM users WHERE id = ?", [$userId]);
        if ((int)($user['ai_credits'] ?? 0) < $creditsUsed) {
            return ['error' => "Not enough AI credits. This render costs {$creditsUsed} credits."];
        }

        $thumb = $plan['avatar_img'];

        $metadata = [
            'video_type'    => $videoType,
            'avatar_name'   => $plan['avatar'],
            'provider'      => $providerId,
            'provider_label' => $provider['label'],
            'style_id'      => $styleId,
            'scenes'        => $plan['scenes'],
            'resolution'    => $plan['resolution']['w'] . 'x' . $plan['resolution']['h'],
            'full_script'   => $plan['full_script'],
            'rebuilt_at'    => date('c'),
            'edit_count'    => (int)($meta['edit_count'] ?? 0) + 1,
        ];

        // ── Built-in storyboard renderer: fresh stills, back to the browser ──
        //    (also used by the FREE keyless `pollinations` render route)
        if ($provider['kind'] === 'storyboard' || $provider['kind'] === 'pollinations') {
            $seed   = random_int(1, 999999);
            $scenes = self::generateSceneStills($plan, $seed, $provider['kind']);

            $metadata['provider']       = $provider['kind'];
            $metadata['provider_label'] = $provider['label'];
            $metadata['scenes']         = $scenes;
            $metadata['seed']           = $seed;
            $metadata['scene_source']   = self::deriveSceneSource($scenes);

            if (!empty($scenes[0]['local_path'])) {
                $thumb = $scenes[0]['local_path'];
            }

            db()->update('ai_video_creatives', [
                'avatar_id'        => $videoType === 'commercial_cinematic' ? 'commercial_cinematic' : $avatarId,
                'voice_id'         => $voiceId,
                'hook_text'        => $hookText !== '' ? $hookText : 'Untitled video',
                'script_body'      => $scriptBody,
                'cta_text'         => $ctaText,
                'caption_style'    => $captionStyle,
                'broll_keyword'    => $brollKeyword,
                'audio_track'      => $audioTrack,
                'thumbnail_url'    => $thumb,
                'aspect_ratio'     => $aspectRatio,
                'duration_seconds' => $durationSeconds,
                'status'           => 'rendering_client',
                'error_message'    => null,
                'credits_used'     => $creditsUsed,
                'metadata'         => json_encode($metadata, JSON_UNESCAPED_SLASHES),
            ], 'id = ? AND user_id = ?', [$creativeId, $userId]);

            return [
                'id'              => $creativeId,
                'video_type'      => $videoType,
                'status'          => 'rendering_client',
                'provider'        => $provider['kind'],
                'provider_label'  => $provider['label'],
                'video_url'       => '',
                'thumbnail_url'   => $thumb,
                'hook_text'       => $hookText,
                'script_body'     => $scriptBody,
                'cta_text'        => $ctaText,
                'duration_seconds' => $durationSeconds,
                'caption_style'   => $captionStyle,
                'audio_track'     => $audioTrack,
                'aspect_ratio'    => $aspectRatio,
                'credits_used'    => $creditsUsed,
                'scenes'          => $scenes,
                'resolution'      => $plan['resolution'],
                'full_script'     => $plan['full_script'],
                'next_action'     => 'client_render',
                'message'         => 'Updated scene assets ready. Rendering your video now — keep this tab open.',
            ];
        }

        // ── External async provider: resubmit the (possibly edited) prompt ──
        $submission = self::submitToProvider($providerId, $provider, [
            'prompt'       => $plan['full_script'],
            'scenes'       => $plan['scenes'],
            'aspect_ratio' => $aspectRatio,
            'duration'     => $durationSeconds,
            'resolution'   => $plan['resolution'],
        ]);

        if (isset($submission['error'])) {
            error_log("AIVideoEngine: {$providerId} rebuild submit failed — " . $submission['error'] . ' — falling back to storyboard');
            return self::rebuildCreative($userId, $creativeId, array_merge($overrides, ['provider' => 'storyboard']));
        }

        $metadata['task_id']    = $submission['task_id'] ?? '';
        $metadata['task_url']   = $submission['task_url'] ?? '';
        $metadata['prediction'] = $submission['prediction'] ?? null;

        db()->update('ai_video_creatives', [
            'avatar_id'        => $videoType === 'commercial_cinematic' ? 'commercial_cinematic' : $avatarId,
            'voice_id'         => $voiceId,
            'hook_text'        => $hookText !== '' ? $hookText : 'Untitled video',
            'script_body'      => $scriptBody,
            'cta_text'         => $ctaText,
            'caption_style'    => $captionStyle,
            'broll_keyword'    => $brollKeyword,
            'audio_track'      => $audioTrack,
            'video_url'        => '',
            'thumbnail_url'    => $thumb,
            'aspect_ratio'     => $aspectRatio,
            'duration_seconds' => $durationSeconds,
            'status'           => 'generating',
            'error_message'    => null,
            'credits_used'     => $creditsUsed,
            'metadata'         => json_encode($metadata, JSON_UNESCAPED_SLASHES),
        ], 'id = ? AND user_id = ?', [$creativeId, $userId]);

        return [
            'id'              => $creativeId,
            'video_type'      => $videoType,
            'status'          => 'generating',
            'provider'        => $providerId,
            'provider_label'  => $provider['label'],
            'task_id'         => $submission['task_id'] ?? '',
            'video_url'       => '',
            'thumbnail_url'   => $thumb,
            'hook_text'       => $hookText,
            'script_body'     => $scriptBody,
            'cta_text'        => $ctaText,
            'duration_seconds' => $durationSeconds,
            'caption_style'   => $captionStyle,
            'audio_track'     => $audioTrack,
            'aspect_ratio'    => $aspectRatio,
            'credits_used'    => $creditsUsed,
            'next_action'     => 'poll',
            'message'         => "Queued on {$provider['label']}. This usually takes 1-3 minutes.",
        ];
    }

    private static function insertRow(int $userId, array $data): int
    {
        $data['user_id'] = $userId;
        $data['metadata'] = json_encode($data['metadata'] ?? [], JSON_UNESCAPED_SLASHES);
        try {
            return db()->insert('ai_video_creatives', $data);
        } catch (\Throwable $e) {
            self::ensureTable();
            return db()->insert('ai_video_creatives', $data);
        }
    }

    // ───────────────────────────────────────────────────────────
    //  Status polling for external providers
    // ───────────────────────────────────────────────────────────

    /**
     * Poll an in-flight job. Downloads + finalises the artifact when the
     * provider reports success.
     */
    public static function getJobStatus(int $userId, int $creativeId): array
    {
        $row = db()->fetchOne("SELECT * FROM ai_video_creatives WHERE id = ? AND user_id = ?", [$creativeId, $userId]);
        if (!$row) {
            return ['error' => 'Creative not found.'];
        }

        if ($row['status'] === 'completed') {
            return ['id' => (int)$row['id'], 'status' => 'completed', 'video_url' => self::absoluteUrl($row['video_url']), 'thumbnail_url' => self::absoluteUrl($row['thumbnail_url'] ?? '')];
        }
        if ($row['status'] === 'failed') {
            return ['id' => (int)$row['id'], 'status' => 'failed', 'error' => $row['error_message'] ?? 'Render failed.'];
        }
        if ($row['status'] === 'rendering_client') {
            return ['id' => (int)$row['id'], 'status' => 'rendering_client', 'message' => 'Waiting for browser render.'];
        }

        $meta = json_decode($row['metadata'] ?? '{}', true) ?: [];
        $taskId = (string)($meta['task_id'] ?? '');
        $providerId = (string)($meta['provider'] ?? '');
        if ($taskId === '' || !isset(self::getProviders()[$providerId])) {
            db()->update('ai_video_creatives', ['status' => 'failed', 'error_message' => 'Job reference lost.'], 'id = ?', [$creativeId]);
            return ['id' => (int)$row['id'], 'status' => 'failed', 'error' => 'Job reference lost.'];
        }

        $provider = self::getProviders()[$providerId];
        $poll = self::pollProvider($providerId, $provider, $taskId, $meta);

        if (isset($poll['error'])) {
            db()->update('ai_video_creatives', ['status' => 'failed', 'error_message' => $poll['error']], 'id = ?', [$creativeId]);
            return ['id' => (int)$row['id'], 'status' => 'failed', 'error' => $poll['error']];
        }

        $status = $poll['status'] ?? 'processing';
        if ($status === 'succeeded' && !empty($poll['output_url'])) {
            $saved = self::storeRemoteVideo($poll['output_url'], (int)$row['user_id'], (int)$row['id']);
            if ($saved === null) {
                // Fall back to the remote URL so the user still gets a playable file.
                db()->update('ai_video_creatives', [
                    'status'     => 'completed',
                    'video_url'  => $poll['output_url'],
                    'completed_at' => date('Y-m-d H:i:s'),
                ], 'id = ?', [$creativeId]);
                self::debitCredits((int)$row['user_id'], (int)$row['credits_used'], (int)$row['id']);
                return ['id' => (int)$row['id'], 'status' => 'completed', 'video_url' => $poll['output_url']];
            }
            db()->update('ai_video_creatives', [
                'status'       => 'completed',
                'video_url'    => $saved,
                'thumbnail_url' => $row['thumbnail_url'] ?: $saved,
                'completed_at' => date('Y-m-d H:i:s'),
            ], 'id = ?', [$creativeId]);
            self::debitCredits((int)$row['user_id'], (int)$row['credits_used'], (int)$row['id']);
            return ['id' => (int)$row['id'], 'status' => 'completed', 'video_url' => self::absoluteUrl($saved)];
        }

        if ($status === 'failed') {
            db()->update('ai_video_creatives', ['status' => 'failed', 'error_message' => $poll['error'] ?? 'Provider reported failure.'], 'id = ?', [$creativeId]);
            return ['id' => (int)$row['id'], 'status' => 'failed', 'error' => $poll['error'] ?? 'Provider reported failure.'];
        }

        return ['id' => (int)$row['id'], 'status' => 'generating', 'progress' => $poll['progress'] ?? null, 'message' => $poll['message'] ?? 'Rendering…'];
    }

    // ───────────────────────────────────────────────────────────
    //  Save a browser-rendered video (Storyboard path)
    // ───────────────────────────────────────────────────────────

    /**
     * Persist the WebM/MP4 produced by the browser renderer.
     */
    public static function attachRenderedVideo(int $userId, int $creativeId, string $tmpPath, string $ext, ?string $thumbDataUri = null): array
    {
        $row = db()->fetchOne("SELECT * FROM ai_video_creatives WHERE id = ? AND user_id = ?", [$creativeId, $userId]);
        if (!$row) {
            return ['error' => 'Creative not found.'];
        }
        if ($row['status'] === 'completed' && $row['video_url'] !== '') {
            return ['id' => (int)$row['id'], 'status' => 'completed', 'video_url' => self::absoluteUrl($row['video_url'])];
        }

        $ext = strtolower($ext);
        $allowed = ['webm', 'mp4', 'mov'];
        if (!in_array($ext, $allowed, true)) {
            $ext = 'webm';
        }
        if (!is_file($tmpPath)) {
            return ['error' => 'Rendered file missing.'];
        }
        $size = filesize($tmpPath);
        if ($size === false || $size < 1024) {
            return ['error' => 'Rendered file was empty. Please try again.'];
        }
        if ($size > 120 * 1024 * 1024) {
            return ['error' => 'Rendered file is too large (120 MB limit).'];
        }

        $dir = ROOT_PATH . '/' . self::UPLOAD_DIR;
        if (!is_dir($dir) && !@mkdir($dir, 0755, true) && !is_dir($dir)) {
            return ['error' => 'Upload directory unavailable.'];
        }

        $name   = 'ugc_' . $userId . '_' . $creativeId . '_' . bin2hex(random_bytes(6)) . '.' . $ext;
        $dest   = $dir . '/' . $name;
        $relUrl = self::UPLOAD_DIR . '/' . $name;

        $moved = @move_uploaded_file($tmpPath, $dest);
        if (!$moved) {
            $moved = @rename($tmpPath, $dest) || @copy($tmpPath, $dest);
        }
        if (!$moved) {
            return ['error' => 'Could not store the rendered video.'];
        }
        @chmod($dest, 0644);

        $thumbUrl = $row['thumbnail_url'] ?? '';
        if ($thumbDataUri !== null && preg_match('#^data:image/(png|jpeg);base64,#i', $thumbDataUri, $m)) {
            $tExt  = strtolower($m[1]) === 'png' ? 'png' : 'jpg';
            $tData = base64_decode(substr($thumbDataUri, strpos($thumbDataUri, ',') + 1), true);
            if ($tData !== false && strlen($tData) < 2 * 1024 * 1024) {
                $tName = 'thumb_' . $userId . '_' . $creativeId . '_' . bin2hex(random_bytes(4)) . '.' . $tExt;
                if (@file_put_contents($dir . '/' . $tName, $tData) !== false) {
                    $thumbUrl = self::UPLOAD_DIR . '/' . $tName;
                }
            }
        }

        db()->update('ai_video_creatives', [
            'status'        => 'completed',
            'video_url'     => $relUrl,
            'thumbnail_url' => $thumbUrl !== '' ? $thumbUrl : $relUrl,
            'completed_at'  => date('Y-m-d H:i:s'),
        ], 'id = ?', [$creativeId]);

        self::debitCredits($userId, (int)$row['credits_used'], $creativeId);

        return [
            'id'            => (int)$row['id'],
            'status'        => 'completed',
            'video_url'     => self::absoluteUrl($relUrl),
            'thumbnail_url' => self::absoluteUrl($thumbUrl),
            'credits_used'  => (int)$row['credits_used'],
        ];
    }

    private static function debitCredits(int $userId, int $credits, int $creativeId): void
    {
        if ($credits <= 0) {
            return;
        }
        try {
            // Only debit once — guard against double-finalisation.
            $meta = json_decode((string)db()->fetchOne("SELECT metadata FROM ai_video_creatives WHERE id = ?", [$creativeId])['metadata'], true) ?: [];
            if (!empty($meta['credits_debited'])) {
                return;
            }
            $meta['credits_debited'] = date('c');
            db()->query("UPDATE users SET ai_credits = GREATEST(0, ai_credits - ?) WHERE id = ?", [$credits, $userId]);
            db()->update('ai_video_creatives', ['metadata' => json_encode($meta, JSON_UNESCAPED_SLASHES)], 'id = ?', [$creativeId]);
        } catch (\Throwable $e) {
            error_log('debitCredits: ' . $e->getMessage());
        }
    }

    // ───────────────────────────────────────────────────────────
    //  External provider adapters
    // ───────────────────────────────────────────────────────────

    private static function submitToProvider(string $providerId, array $provider, array $input): array
    {
        $aspect = $input['aspect_ratio'];
        $res    = $input['resolution'];
        $prompt = $input['prompt'];
        $dur    = $input['duration'];

        switch ($provider['kind']) {
            case 'gemini':
                $key = self::key('GEMINI_API_KEY', 'gemini_api_key');
                $body = json_encode([
                    'instances' => [['prompt' => $prompt]],
                    'parameters' => [
                        'aspectRatio'    => str_replace(':', 'x', $aspect),
                        'sampleCount'    => 1,
                        'durationSeconds' => min(8, max(4, (int)ceil($dur / 4))),
                    ],
                ]);
                // Walk the Veo model list newest-first; stop at the first
                // endpoint that accepts the request (404 = model not yet
                // enabled for this key/project → try the next one).
                $lastError = '';
                $taskName  = '';
                foreach (GeminiEngine::VIDEO_MODELS as $veoModel) {
                    $endpoint = 'https://generativelanguage.googleapis.com/v1beta/models/' . $veoModel . ':predictLongRunning';
                    $resp = self::http('POST', $endpoint . '?key=' . urlencode($key), $body, [
                        'Content-Type: application/json',
                    ]);
                    if ($resp['code'] === 404) {
                        $lastError = "{$veoModel} not available for this API key";
                        continue;
                    }
                    if ($resp['code'] < 200 || $resp['code'] >= 300) {
                        $lastError = self::providerError('Google Veo (' . $veoModel . ')', $resp);
                        // Auth/param errors apply to every model — do not retry them.
                        if ($resp['code'] !== 404 && ($resp['code'] < 500)) {
                            break;
                        }
                        continue;
                    }
                    $json = json_decode($resp['body'], true) ?: [];
                    $taskName = (string)($json['name'] ?? '');
                    if ($taskName !== '') {
                        break;
                    }
                    $lastError = 'Google Veo did not return an operation name.';
                }
                if ($taskName === '') {
                    return ['error' => $lastError !== '' ? $lastError : 'Google Veo did not return an operation name.'];
                }
                return ['task_id' => $taskName, 'task_url' => ''];

            case 'replicate':
                $token = self::key('REPLICATE_API_TOKEN', 'replicate_api_token');
                $body = json_encode([
                    'model' => 'kwaivgi/kling-v2.5-turbo-pro',
                    'input' => [
                        'prompt'          => $prompt,
                        'aspect_ratio'    => $aspect,
                        'duration'        => $dur <= 10 ? '5' : '10',
                        'std_resolution'  => $res['h'] >= 1080 ? '1080p' : '720p',
                    ],
                ]);
                $resp = self::http('POST', $provider['endpoint'], $body, [
                    'Authorization: Bearer ' . $token,
                    'Content-Type: application/json',
                    'Prefer: wait=0',
                ]);
                if ($resp['code'] < 200 || $resp['code'] >= 300) {
                    return ['error' => self::providerError('Replicate', $resp)];
                }
                $json = json_decode($resp['body'], true) ?: [];
                return ['task_id' => (string)($json['id'] ?? ''), 'task_url' => (string)($json['urls']['get'] ?? '')];

            case 'fal':
                $key = self::key('FAL_KEY', 'fal_key');
                $body = json_encode([
                    'prompt'       => $prompt,
                    'aspect_ratio' => $aspect,
                    'duration'     => $dur <= 10 ? '5' : '10',
                ]);
                $resp = self::http('POST', $provider['endpoint'], $body, [
                    'Authorization: Key ' . $key,
                    'Content-Type: application/json',
                ]);
                if ($resp['code'] < 200 || $resp['code'] >= 300) {
                    return ['error' => self::providerError('fal.ai', $resp)];
                }
                $json = json_decode($resp['body'], true) ?: [];
                return ['task_id' => (string)($json['request_id'] ?? $json['response_id'] ?? ''), 'task_url' => (string)($json['urls']['get'] ?? '')];

            case 'runway':
                $key = self::key('RUNWAY_API_KEY', 'runway_api_key');
                $body = json_encode([
                    'model'    => 'gen3a_turbo',
                    'promptText' => $prompt,
                    'ratio'    => $res['w'] > $res['h'] ? '1280:768' : ($res['w'] === $res['h'] ? '768:768' : '768:1280'),
                    'duration' => min(10, max(5, (int)$dur)),
                ]);
                $resp = self::http('POST', $provider['endpoint'], $body, [
                    'Authorization: Bearer ' . $key,
                    'X-Runway-Version: 2024-11-06',
                    'Content-Type: application/json',
                ]);
                if ($resp['code'] < 200 || $resp['code'] >= 300) {
                    return ['error' => self::providerError('Runway', $resp)];
                }
                $json = json_decode($resp['body'], true) ?: [];
                return ['task_id' => (string)($json['id'] ?? ''), 'task_url' => ''];
        }

        return ['error' => "Unsupported provider: {$providerId}"];
    }

    private static function pollProvider(string $providerId, array $provider, string $taskId, array $meta): array
    {
        $statusUrl = match ($provider['kind']) {
            'gemini'    => 'https://generativelanguage.googleapis.com/v1beta/' . $taskId . '?key=' . urlencode(self::key('GEMINI_API_KEY', 'gemini_api_key')),
            'replicate' => !empty($meta['task_url']) ? $meta['task_url'] : 'https://api.replicate.com/v1/predictions/' . rawurlencode($taskId),
            'fal'       => !empty($meta['task_url']) ? $meta['task_url'] : 'https://queue.fal.run/fal-ai/kling-video/v2.5-turbo/pro/text-to-video/requests/' . rawurlencode($taskId) . '/status',
            'runway'    => 'https://api.dev.runwayml.com/v1/tasks/' . rawurlencode($taskId),
            default     => '',
        };
        if ($statusUrl === '') {
            return ['error' => 'Unknown provider for polling.'];
        }

        $headers = match ($provider['kind']) {
            'replicate' => ['Authorization: Bearer ' . self::key('REPLICATE_API_TOKEN', 'replicate_api_token')],
            'fal'       => ['Authorization: Key ' . self::key('FAL_KEY', 'fal_key')],
            'runway'    => ['Authorization: Bearer ' . self::key('RUNWAY_API_KEY', 'runway_api_key'), 'X-Runway-Version: 2024-11-06'],
            default     => [],
        };

        $resp = self::http('GET', $statusUrl, '', $headers);
        if ($resp['code'] < 200 || $resp['code'] >= 300) {
            return ['error' => self::providerError($provider['label'], $resp)];
        }
        $json = json_decode($resp['body'], true) ?: [];

        switch ($provider['kind']) {
            case 'gemini':
                if (!empty($json['done'])) {
                    $uri = $json['response']['generateVideoResponse']['generatedSamples'][0]['video']['uri']
                        ?? $json['response']['generatedSamples'][0]['video']['uri'] ?? '';
                    return $uri !== '' ? ['status' => 'succeeded', 'output_url' => $uri] : ['status' => 'failed', 'error' => 'Veo returned no sample.'];
                }
                return ['status' => 'processing', 'message' => 'Veo is rendering…'];

            case 'replicate':
                $st = (string)($json['status'] ?? 'processing');
                if ($st === 'succeeded') {
                    $out = $json['output'] ?? null;
                    $url = is_array($out) ? (string)($out[0] ?? '') : (string)$out;
                    return $url !== '' ? ['status' => 'succeeded', 'output_url' => $url] : ['status' => 'failed', 'error' => 'No output returned.'];
                }
                if ($st === 'failed' || $st === 'canceled') {
                    return ['status' => 'failed', 'error' => (string)($json['error'] ?? 'Render failed.')];
                }
                return ['status' => 'processing'];

            case 'fal':
                $st = (string)($json['status'] ?? '');
                if ($st === 'COMPLETED' || !empty($json['video']['url'])) {
                    $url = (string)($json['video']['url'] ?? ($json['response']['video']['url'] ?? ''));
                    return $url !== '' ? ['status' => 'succeeded', 'output_url' => $url] : ['status' => 'failed', 'error' => 'No video URL returned.'];
                }
                if ($st === 'FAILED' || $st === 'ERROR') {
                    return ['status' => 'failed', 'error' => (string)($json['error'] ?? 'Render failed.')];
                }
                return ['status' => 'processing'];

            case 'runway':
                $st = (string)($json['status'] ?? 'PENDING');
                if ($st === 'SUCCEEDED') {
                    $url = (string)($json['output'][0] ?? '');
                    return $url !== '' ? ['status' => 'succeeded', 'output_url' => $url] : ['status' => 'failed', 'error' => 'No output returned.'];
                }
                if (in_array($st, ['FAILED', 'CANCELLED'], true)) {
                    return ['status' => 'failed', 'error' => (string)($json['failure'] ?? 'Render failed.')];
                }
                return ['status' => 'processing'];
        }

        return ['error' => 'Unknown provider for polling.'];
    }

    // ───────────────────────────────────────────────────────────
    //  HTTP + storage helpers
    // ───────────────────────────────────────────────────────────

    private static function http(string $method, string $url, string $body = '', array $headers = []): array
    {
        $sslVerify = self::caBundleAvailable();
        $c = curl_init($url);
        $opts = [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_TIMEOUT        => 120,
            CURLOPT_CONNECTTIMEOUT => 20,
            CURLOPT_USERAGENT      => 'AdsboosterVideoEngine/2.0',
            CURLOPT_SSL_VERIFYPEER => $sslVerify,
            CURLOPT_SSL_VERIFYHOST => $sslVerify ? 2 : 0,
            CURLOPT_HTTPHEADER     => $headers,
        ];
        if ($method === 'POST') {
            $opts[CURLOPT_POST] = true;
            $opts[CURLOPT_POSTFIELDS] = $body;
        }
        if ($sslVerify) {
            $opts[CURLOPT_CAINFO] = self::caBundlePath();
        }
        curl_setopt_array($c, $opts);
        $respBody = curl_exec($c);
        $code     = (int)curl_getinfo($c, CURLINFO_HTTP_CODE);
        $err      = curl_error($c);
        curl_close($c);

        return ['code' => $code, 'body' => (string)$respBody, 'error' => $err];
    }

    private static function providerError(string $label, array $resp): string
    {
        $msg = "The {$label} provider returned HTTP {$resp['code']}.";
        $json = json_decode($resp['body'], true);
        if (is_array($json)) {
            $detail = $json['error']['message'] ?? $json['error'] ?? $json['message'] ?? ($json['detail'] ?? null);
            if (is_array($detail)) {
                $detail = json_encode($detail);
            }
            if (is_string($detail) && $detail !== '') {
                $msg .= ' ' . mb_substr($detail, 0, 300);
            }
        } elseif (!empty($resp['error'])) {
            $msg .= ' ' . $resp['error'];
        }
        return $msg;
    }

    private static function storeRemoteVideo(string $url, int $userId, int $creativeId): ?string
    {
        $dir = ROOT_PATH . '/' . self::UPLOAD_DIR;
        if (!is_dir($dir) && !@mkdir($dir, 0755, true) && !is_dir($dir)) {
            return null;
        }

        $sslVerify = self::caBundleAvailable();
        $c = curl_init($url);
        curl_setopt_array($c, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_TIMEOUT        => 300,
            CURLOPT_CONNECTTIMEOUT => 20,
            CURLOPT_USERAGENT      => 'AdsboosterVideoEngine/2.0',
            CURLOPT_SSL_VERIFYPEER => $sslVerify,
            CURLOPT_SSL_VERIFYHOST => $sslVerify ? 2 : 0,
        ]);
        if ($sslVerify) {
            curl_setopt($c, CURLOPT_CAINFO, self::caBundlePath());
        }
        $bin  = curl_exec($c);
        $code = (int)curl_getinfo($c, CURLINFO_HTTP_CODE);
        curl_close($c);

        if ($bin === false || $code < 200 || $code >= 300 || strlen($bin) < 2048) {
            return null;
        }

        $ext = 'mp4';
        if (str_starts_with($bin, "\x1A\x45\xDF\xA3")) {
            $ext = 'webm';
        }
        $name = 'ugc_' . $userId . '_' . $creativeId . '_' . bin2hex(random_bytes(6)) . '.' . $ext;
        if (@file_put_contents($dir . '/' . $name, $bin) === false) {
            return null;
        }
        @chmod($dir . '/' . $name, 0644);
        return self::UPLOAD_DIR . '/' . $name;
    }

    private static function absoluteUrl(string $path): string
    {
        // Single source of truth for media URL resolution.
        return function_exists('media_url') ? media_url($path) : $path;
    }

    private static function groqChat(string $prompt, string $key, float $temperature = 0.7, int $maxTokens = 800): string
    {
        $resp = self::http('POST', 'https://api.groq.com/openai/v1/chat/completions', json_encode([
            'model'    => 'openai/gpt-oss-120b',
            'messages' => [['role' => 'user', 'content' => $prompt]],
            'temperature' => $temperature,
            'max_tokens'  => $maxTokens,
        ]), ['Authorization: Bearer ' . $key, 'Content-Type: application/json']);

        if ($resp['code'] < 200 || $resp['code'] >= 300) {
            return '';
        }
        $json = json_decode($resp['body'], true) ?: [];
        return (string)($json['choices'][0]['message']['content'] ?? '');
    }

    private static function extractJson(string $text): ?array
    {
        $text = trim($text);
        if ($text === '') {
            return null;
        }
        $decoded = json_decode($text, true);
        if (is_array($decoded)) {
            return $decoded;
        }
        if (preg_match('/\{[\s\S]*\}/', $text, $m)) {
            $decoded = json_decode($m[0], true);
            if (is_array($decoded)) {
                return $decoded;
            }
        }
        return null;
    }

    // ───────────────────────────────────────────────────────────
    //  Schema bootstrap
    // ───────────────────────────────────────────────────────────

    public static function ensureTable(): void
    {
        db()->query("CREATE TABLE IF NOT EXISTS `ai_video_creatives` (
            `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
            `user_id` INT UNSIGNED NOT NULL,
            `avatar_id` VARCHAR(64) NOT NULL DEFAULT 'lexi_genz',
            `voice_id` VARCHAR(64) NOT NULL DEFAULT 'en_female_energetic',
            `hook_text` TEXT NULL,
            `script_body` TEXT NULL,
            `cta_text` TEXT NULL,
            `caption_style` VARCHAR(48) NOT NULL DEFAULT 'tiktok_glow',
            `broll_keyword` VARCHAR(255) NULL,
            `audio_track` VARCHAR(48) NOT NULL DEFAULT 'viral_lofi_beat',
            `video_url` VARCHAR(512) NOT NULL DEFAULT '',
            `thumbnail_url` VARCHAR(512) NULL,
            `aspect_ratio` VARCHAR(8) NOT NULL DEFAULT '9:16',
            `duration_seconds` INT UNSIGNED NOT NULL DEFAULT 30,
            `status` ENUM('queued','generating','rendering_client','completed','failed') NOT NULL DEFAULT 'queued',
            `credits_used` INT UNSIGNED NOT NULL DEFAULT 0,
            `error_message` TEXT NULL,
            `metadata` LONGTEXT NULL,
            `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            `completed_at` TIMESTAMP NULL DEFAULT NULL,
            PRIMARY KEY (`id`),
            KEY `idx_user` (`user_id`,`created_at`),
            KEY `idx_status` (`status`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci");

        // Older installs shipped a narrower `status` enum — widen it in place so
        // the new `rendering_client` state is accepted.
        try {
            $col = db()->fetchOne("SHOW COLUMNS FROM `ai_video_creatives` LIKE 'status'");
            if ($col !== null && !str_contains((string)$col['Type'], 'rendering_client')) {
                db()->query("ALTER TABLE `ai_video_creatives`
                    MODIFY `status` ENUM('queued','generating','rendering_client','completed','failed')
                    NOT NULL DEFAULT 'queued'");
            }
        } catch (\Throwable $e) {
            error_log('AIVideoEngine::ensureTable status widen: ' . $e->getMessage());
        }

        // Add any columns introduced by newer releases.
        self::ensureColumns('ai_video_creatives', [
            'error_message' => "ADD COLUMN `error_message` TEXT NULL AFTER `credits_used`",
            'completed_at'  => "ADD COLUMN `completed_at` TIMESTAMP NULL DEFAULT NULL AFTER `created_at`",
        ]);
    }

    /**
     * Add missing columns to an existing table (idempotent).
     */
    private static function ensureColumns(string $table, array $ddl): void
    {
        try {
            $existing = array_map(
                fn($r) => (string)$r['Field'],
                db()->fetchAll("SHOW COLUMNS FROM `{$table}`")
            );
        } catch (\Throwable $e) {
            return;
        }
        foreach ($ddl as $column => $alter) {
            if (in_array($column, $existing, true)) {
                continue;
            }
            try {
                db()->query("ALTER TABLE `{$table}` {$alter}");
            } catch (\Throwable $e) {
                error_log("ensureColumns({$table}.{$column}): " . $e->getMessage());
            }
        }
    }
}
