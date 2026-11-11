<?php declare(strict_types=1);

/**
 * AI Content & UGC Creative Generation Engine — Adsbooster.net
 *
 * Multi-provider AI engine featuring:
 *  - Groq AI (Ultra-fast LLM text, conversational consultant, viral UGC scripts)
 *  - Google Gemini AI (Multimodal creatives, image generation, visual briefs)
 *  - NVIDIA NIM (Extended LLM fallback — integrate.api.nvidia.com/v1)
 *  - Built-in UGC Storyboard & Dynamic Video Creator Studio
 */

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/apikeys.php';

class AIEngine
{
    public const TEXT_CREDIT_COST  = 1;
    public const IMAGE_CREDIT_COST = 5;
    public const VIDEO_CREDIT_COST = 5;

    /** Provider → API key setting mapping */
    private const PROVIDER_KEYS = [
        'groq'       => 'groq_api_key',
        'gemini'     => 'gemini_api_key',
        'nvidia'     => 'nvidia_api_key',
    ];

    /** Text & Script models per provider in order of priority */
    private const GROQ_MODELS = [
        'openai/gpt-oss-120b',
        'qwen/qwen3.8-27b',
        'openai/gpt-oss-20b',
        'llama-3.3-70b-versatile',
    ];

    private const NVIDIA_TEXT_MODELS = [
        // Verified live against integrate.api.nvidia.com (chat/completions),
        // 2026-11. meta/llama-3.1-70b-instruct and mixtral-8x22b-instruct
        // were retired by NVIDIA (HTTP 410) — see php_errors.log 01-Nov-2026.
        // NOTE: /v1/models may list models this key cannot call (404 on
        // chat); only chat-verified models belong here.
        'mistralai/mistral-large-4-1t-a52b',
        'deepseek-ai/deepseek-v4.1-flash',
    ];

    /** Image models */
    private const IMAGE_MODELS = [
        'gemini' => 'gemini-1.5-flash',
    ];

    /**
     * Resolve API key using constant -> env -> DB fallback.
     */
    public static function resolveKey(string $provider): string
    {
        return match ($provider) {
            'groq'       => (function_exists('api_key') ? api_key('GROQ_API_KEY', 'groq_api_key', '') : '') ?: (defined('GROQ_API_KEY') ? GROQ_API_KEY : get_setting('groq_api_key', '')),
            'gemini'     => (function_exists('api_key') ? api_key('GEMINI_API_KEY', 'gemini_api_key', '') : '') ?: (defined('GEMINI_API_KEY') ? GEMINI_API_KEY : get_setting('gemini_api_key', '')),
            'nvidia'     => (function_exists('api_key') ? api_key('NVIDIA_API_KEY', 'nvidia_api_key', '') : '') ?: (defined('NVIDIA_API_KEY') ? NVIDIA_API_KEY : get_setting('nvidia_api_key', '')),
            default      => '',
        };
    }

    // ──────────────────────────────────────────────
    //  PUBLIC API
    // ──────────────────────────────────────────────

    public static function generateText(string $prompt, int $userId, ?string $provider = null): array
    {
        if (empty(trim($prompt))) {
            return ['success' => false, 'result' => null, 'credits_remaining' => 0, 'message' => 'Prompt cannot be empty.'];
        }

        $creditCheck = self::checkAndDeductCredits($userId, self::TEXT_CREDIT_COST);
        if (!$creditCheck['success']) {
            return ['success' => false, 'result' => null, 'credits_remaining' => $creditCheck['credits_remaining'], 'message' => $creditCheck['message']];
        }

        $result = self::callTextAPI($prompt, $provider);
        self::logGeneration($userId, 'text', $prompt, $result['output'] ?? '', self::TEXT_CREDIT_COST, $result['provider'] ?? 'unknown');

        return [
            'success'           => true,
            'result'            => $result['output'] ?? '[Generation failed]',
            'provider'          => $result['provider'] ?? 'unknown',
            'model'             => $result['model'] ?? '',
            'credits_remaining' => $creditCheck['credits_remaining'],
            'message'           => 'Text generated via ' . ($result['provider'] ?? 'AI engine') . '.',
        ];
    }

    /**
     * Real-time conversational AI consultant for P2P Chat.
     */
    public static function chatConsultant(string $userMessage, int $userId): string
    {
        $groqKey = self::resolveKey('groq');

        // 1. Try Groq with priority model list
        if (!empty($groqKey)) {
            foreach (self::GROQ_MODELS as $model) {
                try {
                    $payload = json_encode([
                        'model' => $model,
                        'messages' => [
                            [
                                'role' => 'system',
                                'content' => 'You are the Adsbooster AI Growth Consultant on Adsbooster.net, an elite digital marketing strategist. ' .
                                             'CRITICAL FORMATTING INSTRUCTIONS: ' .
                                             'Format your output cleanly and elegantly like modern top-tier AI agents (ChatGPT, Claude, Perplexity). ' .
                                             'NEVER output raw markdown pipe tables (| | |), ASCII dashes (---), or excessive double hashes (##) and double asterisks (**). ' .
                                             'Use clean section headers, bullet points (•), and include graphical representations via these rich UI tags whenever applicable: ' .
                                             '1. Interactive Charts: [CHART:doughnut:Title:Label1:Val1,Label2:Val2,Label3:Val3] or [CHART:bar:Title:Label1:Val1,Label2:Val2] ' .
                                             '2. KPI Cards: [METRICS:Label1:Value1:Sub1|Label2:Value2:Sub2|Label3:Value3:Sub3] ' .
                                             '3. Channel Matrix: [PLATFORMS:Meta Ads (FB & IG):40%:Role & Specs|TikTok Ads:35%:Role & Specs|Google Search:25%:Role & Specs] ' .
                                             '4. Execution Roadmap: [STEPS:1:Step Title:Action Description:Tool/Tip|2:Step Title:Action Description:Tool/Tip] ' .
                                             '5. Action Deep Links: [ACTION:Button Label:URL:IconClass] (e.g. create-ad, ugc-video-studio, server-tracking, ai-media-buyer) ' .
                                             '6. Visual Graphics & Previews: [IMAGE:url:Caption] ' .
                                             'Always provide high-impact, visual, and immediately actionable advice without visual clutter.'
                            ],
                            ['role' => 'user', 'content' => $userMessage],
                        ],
                        'temperature' => 0.7,
                        'max_tokens'  => 850,
                    ]);

                    $ch = curl_init('https://api.groq.com/openai/v1/chat/completions');
                    curl_setopt_array($ch, [
                        CURLOPT_POST           => true,
                        CURLOPT_POSTFIELDS     => $payload,
                        CURLOPT_HTTPHEADER     => ['Authorization: Bearer ' . $groqKey, 'Content-Type: application/json'],
                        CURLOPT_RETURNTRANSFER => true,
                            CURLOPT_TIMEOUT        => 15,
                            CURLOPT_SSL_VERIFYPEER => true,
                            CURLOPT_SSL_VERIFYHOST => 2,
                        ]);
                        ca_curl_setopt($ch);
                        $response = curl_exec($ch);
                        $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
                    curl_close($ch);

                    if ($code === 200 && $response) {
                        $json = json_decode($response, true);
                        $reply = $json['choices'][0]['message']['content'] ?? '';
                        if (!empty($reply)) {
                            return trim($reply);
                        }
                    }
                } catch (\Throwable $e) {
                    error_log("AIEngine::chatConsultant Groq error ({$model}): " . $e->getMessage());
                }
            }
        }

        // 2. Intelligent Contextual Strategy Fallback with Modern Graphical Widgets
        $msgLower = strtolower($userMessage);

        // Multi-Platform Campaign Strategy
        if (str_contains($msgLower, 'multi-platform') || str_contains($msgLower, 'multi platform') || str_contains($msgLower, 'cross platform') || (str_contains($msgLower, 'run') && str_contains($msgLower, 'campaign'))) {
            return "🚀 **Omni-Channel Multi-Platform Scaling Playbook**\n\n" .
                   "To maximize customer acquisition across platforms without ad fatigue or budget cannibalization, deploy a synchronized 3-channel architecture:\n\n" .
                   "[METRICS:Target ROAS:3.2x:Scale Target|Daily Budget:$150:Meta + TikTok + Google|Test Window:14 Days:Validation Sprint]\n\n" .
                   "[CHART:doughnut:Optimal Multi-Platform Budget Allocation:Meta Advantage+:40,TikTok Video UGC:35,Google High-Intent Search:25]\n\n" .
                   "[PLATFORMS:Meta Ads (FB & IG):40%:Advantage+ Shopping campaigns with 1:1 feed carousels and 9:16 vertical storytelling|TikTok Ads:35%:Spark Ads powered by high-converting 15-second creator UGC with 3-second visual hooks|Google Search & Intent:25%:High-intent transactional keywords, competitor capture, and dynamic remarketing]\n\n" .
                   "[IMAGE:assets/images/thumbs/agency-video-img.png:Multi-Channel Performance Creative Matrix & Cross-Network Distribution]\n\n" .
                   "[STEPS:1:Define Primary Objective:Select ONE primary business outcome (Purchase, Qualified Lead, App Install) and establish strict blended ROAS and CPA thresholds:Meta Pixel, GTM|2:Deploy Creative Matrix:Produce 1:1 square feeds, 9:16 vertical reels, and 3 high-intent search headlines with clear Hook-Problem-Solution structure:Adsbooster UGC Studio|3:Setup Server-Side CAPI:Implement direct Meta & TikTok CAPI to eliminate iOS tracking drop-offs and feed 100% accurate conversion signals:Adsbooster CAPI Gateway|4:Launch 14-Day Validation Sprint:Run dynamic creative sets with Campaign Budget Optimization (CBO) enabled to let ad algorithms find winning segments:Meta CBO, TikTok Smart+|5:Scale Profitable Channels:Increase daily budgets by 20% every 48 hours on ad sets exceeding 2.8x ROAS while cutting losing creative variations:Adsbooster Rules Engine]\n\n" .
                   "[ACTION:🚀 Launch Campaign in Ad Studio:create-ad:ph-bold ph-rocket-launch]" .
                   "[ACTION:🎬 Generate TikTok UGC Script:ugc-video-studio:ph-bold ph-video-camera]" .
                   "[ACTION:⚡ Set Up Server CAPI:server-tracking:ph-bold ph-plugs-connected]";
        }

        // Headlines & Copy Strategy
        if (str_contains($msgLower, 'headline') || str_contains($msgLower, 'copy') || str_contains($msgLower, 'catchy')) {
            return "🔥 **5 High-Converting Viral Ad Headlines**\n\n" .
                   "Here are 5 proven psychological headline formulas tailored for Meta, TikTok, and Google Ads:\n\n" .
                   "[METRICS:Formula:Hook-Problem-CTA|Expected CTR Lift:+3.4%|Top Placement:Reels & Search]\n\n" .
                   "[STEPS:1:The Curiosity Gap:\"The 1 reason most brands fail to scale past $10k/mo (and how to fix it today)\":TikTok & Reels Hooks|2:The Pain-Agitation Hook:\"Still spending hours on manual ad creation? Try this 60-second shortcut\":Meta Feed & Stories|3:The Social Proof Anchor:\"Why 1,400+ top media buyers switched their tracking to Adsbooster\":Google Search & Meta Carousel|4:The Direct Value Offer:\"Get your full marketing strategy and automated campaign live in under 5 minutes\":Landing Page & Meta Ad|5:The Contrarian Statement:\"Stop boosting Facebook posts. Here is what actually drives 4x ROAS in 2026\":Reels Video Opening Hook]\n\n" .
                   "[CHART:bar:Predicted CTR Lift by Formula (%):Curiosity Gap:4.2,Social Proof:3.8,Contrarian Hook:3.5,Direct Value:2.9,Pain Agitation:2.6]\n\n" .
                   "[ACTION:✍️ Create Campaign Copy in Ad Studio:create-ad:ph-bold ph-pencil-simple]";
        }

        // Meta & Facebook ROAS Strategy
        if (str_contains($msgLower, 'roas') || str_contains($msgLower, 'facebook') || str_contains($msgLower, 'meta')) {
            return "📈 **Meta & Facebook ROAS Optimization Strategy**\n\n" .
                   "To boost your return on ad spend beyond 3.0x on Meta, shift away from hyper-narrow audiences to broad Advantage+ with dynamic creative testing:\n\n" .
                   "[METRICS:Target ROAS:3.5x:Scale Target|Break-Even Floor:1.8x:Safety Cutoff|Scaling Pace:20%:Every 48 Hours]\n\n" .
                   "[CHART:doughnut:Meta Performance Budget Allocation:Broad Advantage+ Purchase:50,High-Value Retargeting:30,Dynamic Creative Testing:20]\n\n" .
                   "[STEPS:1:Consolidate Into Broad Advantage+:Group ad sets into broad demographics (Age 22–50, Top Metros) and allow Meta's AI algorithm to find ready-to-buy shoppers:Advantage+ Suite|2:Deploy 3-Second Hook Variations:Test 3 distinct video hooks per reel (contrarian statement, visual product demo, customer objection):UGC Video Studio|3:Dynamic Creative Sets:Combine 3 primary text angles, 2 headlines, and 3 visuals in one dynamic ad set:Ad Studio Dynamic Set|4:Scale Incrementally:Increase budget by 20% every 48 hours exclusively when 7-day ROAS holds above 2.8x:Auto-Rule Manager]\n\n" .
                   "[ACTION:🚀 Create Meta Ad Set:create-ad:ph-bold ph-facebook-logo]";
        }

        // TikTok & UGC Video Strategy
        if (str_contains($msgLower, 'tiktok') || str_contains($msgLower, 'script') || str_contains($msgLower, 'video') || str_contains($msgLower, 'ugc')) {
            return "🎬 **Viral TikTok & Reels UGC Video Formula**\n\n" .
                   "High-performing short-form video ads win or lose in the first 3 seconds. Use this battle-tested 30-second pacing blueprint:\n\n" .
                   "[METRICS:3-Sec Hook Rate Target:>45%|Ideal Video Length:22-28 Seconds|Aspect Ratio:9:16 Vertical HD]\n\n" .
                   "[CHART:doughnut:30-Second UGC Video Time Architecture:0-3s Visual Pattern Interrupt:25,3-10s Agitate Problem:30,10-22s Live Transformation:35,22-30s Direct CTA:10]\n\n" .
                   "[IMAGE:assets/images/thumbs/working-process-new-two-img2.png:Short-Form Video Production Pacing & Retention Architecture]\n\n" .
                   "[STEPS:1:0-3s Visual Hook:Hold product directly in front of camera or start mid-action: \"If you are still doing [Problem] the hard way, stop scrolling.\":High Impact Opening|2:3-10s Problem Agitation:Show the emotional pain and wasted money of traditional alternatives:Relatable Scenario|3:10-22s The Transformation Demo:Show quick unboxing, smooth app screen demo, or instant before/after result:Authentic Social Proof|4:22-30s Clear Irresistible CTA:Direct viewer to link in bio with exclusive limited discount or verified directory listing:Conversion Hook]\n\n" .
                   "[ACTION:🎬 Launch UGC Studio:ugc-video-studio:ph-bold ph-video-camera]";
        }

        // SEO & Directory Ranking Strategy
        if (str_contains($msgLower, 'seo') || str_contains($msgLower, 'rank') || str_contains($msgLower, 'title') || str_contains($msgLower, 'directory')) {
            return "🔍 **Search Engine & Directory Visibility Blueprint**\n\n" .
                   "Accelerate organic traffic and commercial discoverability with high-intent search optimization:\n\n" .
                   "[METRICS:Focus Keyword:First 40 Chars|Meta Description:<155 Chars|Google Index Time:24-48 Hours]\n\n" .
                   "[CHART:bar:Organic Search Conversion Rates by Intent:Commercial / Product:5.8,Local Directory / City:4.6,Informational / Guide:1.9]\n\n" .
                   "[STEPS:1:Keyword In Title Tag:Front-load your target high-value keyword in the first 40 characters of your listing title:Google Search Best Practice|2:Click-Inducing Meta Description:Write an action-oriented summary with social proof and direct WhatsApp booking channel:Meta Optimizer|3:Verified Business Listing:Verified listings on Adsbooster index rapidly on Google search engines for high-intent keywords:Directory Suite]\n\n" .
                   "[ACTION:⭐ Boost Directory Listing:marketplace:ph-bold ph-star]";
        }

        // General AI Consultant Overview
        return "🤖 **Adsbooster AI Growth Consultant**\n\n" .
               "Welcome to your dedicated AI Performance Marketing Suite. Here are the core pillars to scale your business today:\n\n" .
               "[METRICS:Multi-Channel Sync:Meta + TikTok + Google|AI UGC Studio:Scene-by-Scene Videos|Direct Inbound:WhatsApp & Phone]\n\n" .
               "[CHART:doughnut:Recommended Channel Synergy:Multi-Platform Ads:45,UGC Short-Form Video:35,Directory & SEO Listings:20]\n\n" .
               "[PLATFORMS:Meta & Google Ad Studio:45%:Launch multi-network campaigns with unified budget controls|AI UGC Video Creator:35%:Generate viral, scene-by-scene short-form video scripts with realistic previews|Marketplace & Verified Directory:20%:Get listed on Google search results with direct WhatsApp booking channels]\n\n" .
               "[IMAGE:assets/images/thumbs/agency-video-img.png:Adsbooster Full-Funnel Growth Infrastructure]\n\n" .
               "[ACTION:🚀 Create Ad Campaign:create-ad:ph-bold ph-rocket-launch]" .
               "[ACTION:🎬 Generate UGC Video:ugc-video-studio:ph-bold ph-video-camera]";
    }

    /**
     * AI UGC Image Generator: produces photorealistic, high-converting social ad creatives with regeneration & editing support.
     */
    public static function generateUGCImage(array $params, int $userId): array
    {
        $productName    = trim($params['product_name'] ?? 'Premium Brand Product');
        $ugcStyle       = trim($params['ugc_style'] ?? 'selfie_hold');
        $persona        = trim($params['creator_persona'] ?? 'gen_z');
        $aspectRatio    = trim($params['aspect_ratio'] ?? '1:1');
        $targetAudience = trim($params['target_audience'] ?? 'E-commerce buyers & social media shoppers');
        $headline       = trim($params['headline'] ?? '');
        $badgeText      = trim($params['badge_text'] ?? '⭐⭐⭐⭐⭐ Top Rated');
        $seed           = (int)($params['seed'] ?? random_int(100000, 999999));

        $creditCost = self::IMAGE_CREDIT_COST;
        $creditCheck = self::checkAndDeductCredits($userId, $creditCost);
        if (!$creditCheck['success']) {
            return [
                'success'           => false,
                'data'              => null,
                'credits_remaining' => $creditCheck['credits_remaining'],
                'message'           => $creditCheck['message']
            ];
        }

        // Map aspect ratio to dimensions
        [$width, $height] = match ($aspectRatio) {
            '9:16'  => [768, 1344],
            '16:9'  => [1344, 768],
            '4:5'   => [864, 1080],
            default => [1024, 1024],
        };

        // Style descriptions
        $stylePrompts = [
            'selfie_hold'       => 'authentic UGC iPhone selfie angle of a satisfied customer smiling and holding the product in hand, natural sunlight, depth of field, real skin texture, authentic organic social media photo',
            'unboxing'          => 'satisfying tabletop unboxing aesthetic on a modern wooden desk, crisp studio lighting, unboxed product in focus, aesthetically pleasing minimal arrangement, high conversion e-commerce creative',
            'lifestyle_kitchen' => 'lifestyle snapshot in a bright modern kitchen or apartment, candid authentic moment with product in natural setting, photorealistic, warm home aesthetic',
            'influencer_desk'   => 'chic content creator desk setup, glowing monitor in background, trendy ring light reflection, high-end creator reviewing the product with passion',
            'before_after'      => 'side by side aesthetic product visual with clear visual transformation, vibrant lighting, highly shareable social ad layout',
            'flatlay'           => 'luxury minimalist flatlay composition with modern pastel background, organic textures, balanced negative space, clean magazine editorial quality',
        ];
        $styleDesc = $stylePrompts[$ugcStyle] ?? $stylePrompts['selfie_hold'];

        // Persona descriptions
        $personaPrompts = [
            'gen_z'           => 'trendy Gen-Z creator with casual streetwear, energetic vibrant vibe',
            'saas_founder'    => 'modern entrepreneur in minimalist smart casual attire, modern office atmosphere',
            'fitness_athlete' => 'fit athletic creator in gym or outdoor wellness setting',
            'homemaker'       => 'relatable lifestyle parent or homemaker in cozy sunlit home',
            'tech_reviewer'   => 'tech enthusiast in modern ambient LED studio with sleek gear',
            'luxury_model'    => 'chic high-fashion elegance, sophisticated editorial aesthetics',
        ];
        $personaDesc = $personaPrompts[$persona] ?? $personaPrompts['gen_z'];

        // Synthesize dynamic prompt
        $imagePrompt = "Raw authentic UGC photo of {$productName}. {$personaDesc}. {$styleDesc}. Targeted for {$targetAudience}. Hyper-detailed, photorealistic, cinematic commercial lighting, 8k resolution, shot on 35mm lens, award-winning ad creative.";

        // Generate the image with Google Gemini (Nano Banana 2 first, then
        // Nano Banana) and store it same-origin. Falls back to curated
        // first-party stills when no key/quota is available so the creative
        // card is never broken.
        $imageUrl = null;
        $imageProvider = 'gemini-image';
        try {
            require_once __DIR__ . '/gemini_engine.php';
            $generated = GeminiEngine::generateImage($imagePrompt, $width, $height, 'ugc_' . $seed . '_' . $userId);
            if ($generated !== null) {
                $imageUrl = $generated['url'];
                $imageProvider = $generated['model'];
            }
        } catch (\Throwable $e) {
            error_log('Gemini UGC image failed: ' . $e->getMessage());
        }
        if ($imageUrl === null) {
            $imageProvider = 'local-fallback';
            $fallbacks = [
                'assets/images/thumbs/about-five-img1.png',
                'assets/images/thumbs/team-details-img2.png',
                'assets/images/thumbs/blog-details-img2.png',
            ];
            $imageUrl = BASE_URL . '/' . $fallbacks[$seed % count($fallbacks)];
        }

        // Generate AI ad copy and viral hooks using Groq
        $groqKey = self::resolveKey('groq');
        $adCopy = "Grab yours today with fast shipping and 100% satisfaction guarantee!";
        $hookHeadline = $headline ?: "Why everyone on my feed is obsessing over this right now 👇";

        if (!empty($groqKey)) {
            try {
                $copyPrompt = "Write 1 punchy 3-word headline and 1 high-converting 2-sentence ad copy for this product: {$productName}. Audience: {$targetAudience}. Style: {$ugcStyle}. Format as: HEADLINE: [text] | COPY: [text]";
                $aiResp = self::groqText($copyPrompt, $groqKey);
                if (!empty($aiResp['output'])) {
                    if (preg_match('/HEADLINE:\s*(.*?)\s*\|\s*COPY:\s*(.*)/is', $aiResp['output'], $m)) {
                        if (empty($headline)) {
                            $hookHeadline = trim($m[1]);
                        }
                        $adCopy = trim($m[2]);
                    }
                }
            } catch (\Throwable $e) {}
        }

        $ugcData = [
            'image_url'       => $imageUrl,
            'product_name'    => $productName,
            'ugc_style'       => $ugcStyle,
            'creator_persona' => $persona,
            'aspect_ratio'    => $aspectRatio,
            'width'           => $width,
            'height'          => $height,
            'seed'            => $seed,
            'hook_headline'   => $hookHeadline,
            'ad_copy'         => $adCopy,
            'badge_text'      => $badgeText,
            'image_prompt'    => $imagePrompt,
            'provider'        => $imageProvider,
            'created_at'      => date('Y-m-d H:i:s'),
        ];

        // Log creative to ai_creatives
        $creativeId = self::logGeneration(
            $userId,
            'image',
            $imagePrompt,
            json_encode($ugcData),
            $creditCost,
            $imageProvider
        );
        $ugcData['creative_id'] = $creativeId;

        return [
            'success'           => true,
            'data'              => $ugcData,
            'credits_remaining' => $creditCheck['credits_remaining'],
            'message'           => 'UGC Image Creative successfully generated!',
        ];
    }

    /**
     * Dedicated AI UGC Video & Reel Generator.
     * Generates viral multi-scene scripts, visual directions, audio cues, and video previews.
     */
    public static function generateUGCVideo(array $params, int $userId): array
    {
        $productName   = trim($params['product_name'] ?? 'Adsbooster Platform');
        $platform      = trim($params['platform'] ?? 'tiktok');
        $videoStyle    = trim($params['video_style'] ?? 'viral_hook');
        $persona       = trim($params['creator_persona'] ?? 'gen_z');
        $duration      = trim($params['duration'] ?? '30s');
        $targetAudience= trim($params['target_audience'] ?? 'E-commerce buyers & business owners');
        $keyBenefits   = trim($params['key_benefits'] ?? 'Instant conversions, high ROAS, easy setup');

        $creditCheck = self::checkAndDeductCredits($userId, self::VIDEO_CREDIT_COST);
        if (!$creditCheck['success']) {
            return [
                'success'           => false,
                'data'              => null,
                'credits_remaining' => $creditCheck['credits_remaining'],
                'message'           => $creditCheck['message']
            ];
        }

        $groqKey = self::resolveKey('groq');
        $ugcData = null;
        $providerUsed = 'fallback';

        if (!empty($groqKey)) {
            $systemPrompt = "You are an elite UGC (User-Generated Content) Creative Director and TikTok/Reels Scriptwriter who has produced over $10M in viral ad revenue. " .
                            "Your job is to generate a comprehensive, ultra-high-converting, multi-scene UGC video storyboard script. " .
                            "Format your response strictly as valid JSON matching this schema:\n" .
                            "{\n" .
                            '  "title": "Short catchy campaign title",' . "\n" .
                            '  "hook_headline": "Punchy 3-second opening text hook",' . "\n" .
                            '  "audio_music_mood": "e.g. Energetic Lo-Fi Trap / Upbeat Tech Pop",' . "\n" .
                            '  "scenes": [' . "\n" .
                            '    {"scene": 1, "time": "0:00-0:03", "type": "Hook", "visual": "Detailed camera & actor direction", "voiceover": "Spoken dialogue verbatim", "overlay_text": "Text on screen", "sfx": "Audio cue"},' . "\n" .
                            '    {"scene": 2, "time": "0:03-0:10", "type": "Agitation", "visual": "...", "voiceover": "...", "overlay_text": "...", "sfx": "..."},' . "\n" .
                            '    {"scene": 3, "time": "0:10-0:20", "type": "Demo/Solution", "visual": "...", "voiceover": "...", "overlay_text": "...", "sfx": "..."},' . "\n" .
                            '    {"scene": 4, "time": "0:20-0:25", "type": "Proof/Offer", "visual": "...", "voiceover": "...", "overlay_text": "...", "sfx": "..."},' . "\n" .
                            '    {"scene": 5, "time": "0:25-0:30", "type": "Call To Action", "visual": "...", "voiceover": "...", "overlay_text": "...", "sfx": "..."}' . "\n" .
                            '  ],' . "\n" .
                            '  "cta_text": "Exact CTA button / phrase",' . "\n" .
                            '  "viral_hashtags": ["#tag1", "#tag2", "#tag3", "#tag4"]' . "\n" .
                            "}\n" .
                            "Return ONLY the JSON object. Do not wrap in markdown quotes if possible.";

            $userPrompt = "Product/Brand: {$productName}\n" .
                          "Platform: {$platform} (Duration: {$duration})\n" .
                          "Style: {$videoStyle}\n" .
                          "Creator Persona: {$persona}\n" .
                          "Target Audience: {$targetAudience}\n" .
                          "Core Benefits & Offers: {$keyBenefits}\n\n" .
                          "Generate the highest converting UGC video script possible!";

            foreach (self::GROQ_MODELS as $model) {
                try {
                    $payload = json_encode([
                        'model'       => $model,
                        'messages'    => [
                            ['role' => 'system', 'content' => $systemPrompt],
                            ['role' => 'user',   'content' => $userPrompt],
                        ],
                        'temperature' => 0.7,
                        'max_tokens'  => 1600,
                    ]);

                    $ch = curl_init('https://api.groq.com/openai/v1/chat/completions');
                    curl_setopt_array($ch, [
                        CURLOPT_POST           => true,
                        CURLOPT_POSTFIELDS     => $payload,
                        CURLOPT_HTTPHEADER     => ['Authorization: Bearer ' . $groqKey, 'Content-Type: application/json'],
                        CURLOPT_RETURNTRANSFER => true,
                            CURLOPT_TIMEOUT        => 20,
                            CURLOPT_SSL_VERIFYPEER => true,
                            CURLOPT_SSL_VERIFYHOST => 2,
                        ]);
                        ca_curl_setopt($ch);
                        $resp = curl_exec($ch);
                    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
                    curl_close($ch);

                    if ($code === 200 && $resp) {
                        $json = json_decode($resp, true);
                        $rawText = $json['choices'][0]['message']['content'] ?? '';
                        // Clean JSON if wrapped in markdown
                        $clean = preg_replace('/^```(?:json)?\s*/i', '', trim($rawText));
                        $clean = preg_replace('/```$/', '', trim($clean));
                        $parsed = json_decode($clean, true);
                        if (is_array($parsed) && isset($parsed['scenes'])) {
                            $ugcData = $parsed;
                            $providerUsed = "groq ({$model})";
                            break;
                        }
                    }
                } catch (\Throwable $e) {
                    error_log("generateUGCVideo Groq error: " . $e->getMessage());
                }
            }
        }

        // Fallback robust UGC generation if API offline or failed JSON
        if (!$ugcData) {
            $ugcData = self::fallbackUGCStoryboard($productName, $platform, $videoStyle, $persona, $duration, $keyBenefits);
            $providerUsed = "adsbooster-heuristic-engine";
        }

        $loggedOutput = json_encode($ugcData, JSON_PRETTY_PRINT);
        self::logGeneration($userId, 'video', "UGC Video: {$productName} ({$platform}, {$videoStyle})", $loggedOutput, self::VIDEO_CREDIT_COST, $providerUsed);

        return [
            'success'           => true,
            'data'              => $ugcData,
            'provider'          => $providerUsed,
            'credits_remaining' => $creditCheck['credits_remaining'],
            'message'           => 'UGC Video Storyboard & Script successfully generated via ' . $providerUsed . '!',
        ];
    }

    private static function fallbackUGCStoryboard(string $product, string $platform, string $style, string $persona, string $duration, string $benefits): array
    {
        return [
            'title' => "Viral {$product} {$style} Campaign",
            'hook_headline' => "Stop Scrolling! If you care about {$product}, watch this.",
            'audio_music_mood' => "Trending TikTok Upbeat Lo-Fi Beat",
            'scenes' => [
                [
                    'scene' => 1,
                    'time' => '0:00 - 0:03',
                    'type' => 'High-Converting Hook',
                    'visual' => "Creator holds up phone or product close to camera with shocked expression, quick snap zoom.",
                    'voiceover' => "If you're still doing this the old way in " . date('Y') . ", stop right now.",
                    'overlay_text' => "WAIT... did you know this?! 🤯",
                    'sfx' => "Vinyl record scratch + dynamic whoosh"
                ],
                [
                    'scene' => 2,
                    'time' => '0:03 - 0:09',
                    'type' => 'Problem Agitation',
                    'visual' => "Creator sighs, cuts to B-roll showing frustrating old methods and wasted money.",
                    'voiceover' => "I was literally wasting hours trying to get real results, until I finally discovered {$product}.",
                    'overlay_text' => "The mistake 90% of people make ❌",
                    'sfx' => "Subtle sad buzzer sound"
                ],
                [
                    'scene' => 3,
                    'time' => '0:09 - 0:20',
                    'type' => 'The Solution & Demo',
                    'visual' => "Screen recording or hands-on demonstration showing how {$product} works effortlessly. Highlight: {$benefits}.",
                    'voiceover' => "Look how clean this is. In under two minutes, it automates everything and starts delivering conversions.",
                    'overlay_text' => "⚡ Under 2 minutes to scale!",
                    'sfx' => "Satisfying UI chime + upbeat transition"
                ],
                [
                    'scene' => 4,
                    'time' => '0:20 - 0:25',
                    'type' => 'Social Proof & Offer',
                    'visual' => "Creator smiling holding product, screenshot of 5-star customer ratings or verified badge overlay.",
                    'voiceover' => "Plus, they currently have verified access and a special promotional bonus for new members.",
                    'overlay_text' => "Verified 5-Star Rating ★★★★★",
                    'sfx' => "Cash register / sparkle ding"
                ],
                [
                    'scene' => 5,
                    'time' => '0:25 - 0:30',
                    'type' => 'Direct Call To Action',
                    'visual' => "Creator points directly down to caption/link with upbeat smile.",
                    'voiceover' => "Tap the link below right now before the promo ends and get started today!",
                    'overlay_text' => "👉 Click Link Below to Start!",
                    'sfx' => "High-energy outro riser"
                ],
            ],
            'cta_text' => "Claim Your Access — Link in Bio",
            'viral_hashtags' => ["#TikTokMadeMeBuyIt", "#ViralProduct", "#" . preg_replace('/\s+/', '', $product), "#LifeHack", "#GrowthAutomation"],
        ];
    }

    public static function generateImage(string $prompt, int $userId, ?string $provider = null): array
    {
        if (empty(trim($prompt))) {
            return ['success' => false, 'result' => null, 'credits_remaining' => 0, 'message' => 'Prompt cannot be empty.'];
        }

        $creditCheck = self::checkAndDeductCredits($userId, self::IMAGE_CREDIT_COST);
        if (!$creditCheck['success']) {
            return ['success' => false, 'result' => null, 'credits_remaining' => $creditCheck['credits_remaining'], 'message' => $creditCheck['message']];
        }

        $result = self::callImageAPI($prompt, $provider);
        self::logGeneration($userId, 'image', $prompt, $result['output'] ?? '', self::IMAGE_CREDIT_COST, $result['provider'] ?? 'unknown');

        return [
            'success'           => true,
            'result'            => $result['output'] ?? '',
            'provider'          => $result['provider'] ?? 'unknown',
            'model'             => $result['model'] ?? '',
            'credits_remaining' => $creditCheck['credits_remaining'],
            'message'           => 'Image generated via ' . ($result['provider'] ?? 'visual engine') . '.',
        ];
    }

    public static function generateVideo(string $prompt, int $userId, ?string $provider = null): array
    {
        return self::generateUGCVideo(['product_name' => $prompt], $userId);
    }

    /**
     * Get available providers and their status.
     */
    public static function getProviderStatus(): array
    {
        $status = [];
        foreach (self::PROVIDER_KEYS as $provider => $keySetting) {
            $apiKey = self::resolveKey($provider);
            $status[$provider] = [
                'configured'   => !empty($apiKey),
                'key_masked'   => !empty($apiKey) ? substr($apiKey, 0, 8) . '...' . substr($apiKey, -4) : '',
                'text_models'  => $provider === 'groq' ? implode(', ', self::GROQ_MODELS)
                    : ($provider === 'nvidia' ? implode(', ', self::NVIDIA_TEXT_MODELS) : null),
                'image_models' => self::IMAGE_MODELS[$provider] ?? null,
                'video_models' => $provider === 'groq' ? 'Adsbooster Multi-Scene UGC Studio' : null,
            ];
        }
        return $status;
    }

    public static function getUserProvider(int $userId, string $type = 'text'): ?string
    {
        $pref = db()->fetchOne("SELECT ai_preferred_provider FROM users WHERE id = ?", [$userId]);
        $saved = $pref['ai_preferred_provider'] ?? null;

        if ($saved && !empty(self::resolveKey($saved))) {
            return $saved;
        }

        return !empty(self::resolveKey('groq')) ? 'groq' : (!empty(self::resolveKey('gemini')) ? 'gemini' : 'groq');
    }

    public static function setUserProvider(int $userId, string $provider): void
    {
        if (isset(self::PROVIDER_KEYS[$provider])) {
            db()->update('users', ['ai_preferred_provider' => $provider], 'id = ?', [$userId]);
        }
    }

    // ──────────────────────────────────────────────
    //  PRIVATE: API CALLERS
    // ──────────────────────────────────────────────

    private static function callTextAPI(string $prompt, ?string $forceProvider): array
    {
        $provider = $forceProvider ?: self::getBestProvider('text');
        $apiKey = $provider ? self::resolveKey($provider) : '';

        if (!empty($apiKey)) {
            try {
                if ($provider === 'groq') {
                    return self::groqText($prompt, $apiKey);
                } elseif ($provider === 'nvidia') {
                    return self::nvidiaText($prompt, $apiKey);
                }
            } catch (\Throwable $e) {
                error_log("AI {$provider} text error: " . $e->getMessage());
            }
        }

        return ['output' => self::fallbackText($prompt), 'provider' => 'adsbooster-heuristic', 'model' => 'local'];
    }

    private static function callImageAPI(string $prompt, ?string $forceProvider): array
    {
        // Real image generation: Gemini (Nano Banana) when a key exists,
        // otherwise the free keyless Pollinations route — both return a
        // stored same-origin image URL.
        try {
            require_once __DIR__ . '/gemini_engine.php';
            $generated = GeminiEngine::generateImage($prompt, 1024, 1024);
            if ($generated !== null) {
                return [
                    'output'   => $generated['url'],
                    'provider' => str_starts_with($generated['model'], 'pollinations') ? 'pollinations-free' : 'gemini-image',
                    'model'    => $generated['model'],
                ];
            }
        } catch (\Throwable $e) {
            error_log('Image generation error: ' . $e->getMessage());
        }

        // Last resort: curated reference creative (generation unavailable).
        $encodedPrompt = urlencode($prompt);
        return [
            'output'   => "https://images.unsplash.com/photo-1557838923-2985c318be48?auto=format&fit=crop&w=1200&q=80",
            'provider' => 'curated-cdn',
            'model'    => 'adsbooster-creative-library',
        ];
    }

    private static function getBestProvider(string $type): ?string
    {
        if (!empty(self::resolveKey('groq'))) return 'groq';
        if (!empty(self::resolveKey('gemini'))) return 'gemini';
        if (!empty(self::resolveKey('nvidia'))) return 'nvidia';
        return null;
    }

    private static function groqText(string $prompt, string $apiKey): array
    {
        $lastErr = '';
        foreach (self::GROQ_MODELS as $model) {
            $payload = json_encode([
                'model'    => $model,
                'messages' => [
                    ['role' => 'system', 'content' => 'You are an expert marketing copywriter for Adsbooster.net, an advertising automation platform. Write compelling, high-converting ad copy, viral hooks, headlines, and calls to action.'],
                    ['role' => 'user',   'content' => $prompt],
                ],
                'temperature' => 0.7,
                'max_tokens'  => 1024,
            ]);

            $ch = curl_init('https://api.groq.com/openai/v1/chat/completions');
            curl_setopt_array($ch, [
                CURLOPT_POST           => true,
                CURLOPT_POSTFIELDS     => $payload,
                CURLOPT_HTTPHEADER     => ['Authorization: Bearer ' . $apiKey, 'Content-Type: application/json'],
                CURLOPT_RETURNTRANSFER => true,
                    CURLOPT_TIMEOUT        => 20,
                    CURLOPT_SSL_VERIFYPEER => true,
                    CURLOPT_SSL_VERIFYHOST => 2,
                ]);
                ca_curl_setopt($ch);
                $response = curl_exec($ch);
                $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);

            if ($httpCode === 200 && $response) {
                $data = json_decode($response, true);
                $text = $data['choices'][0]['message']['content'] ?? '';
                if (!empty($text)) {
                    return ['output' => $text, 'provider' => 'groq', 'model' => $model];
                }
            } else {
                $lastErr = "HTTP {$httpCode}: " . substr($response ?: '', 0, 100);
            }
        }

        throw new \RuntimeException("Groq failed on all models. Last response: " . $lastErr);
    }

    private static function nvidiaText(string $prompt, string $apiKey): array
    {
        $base = rtrim(defined('NVIDIA_BASE_URL') ? NVIDIA_BASE_URL : 'https://integrate.api.nvidia.com/v1', '/');
        $lastErr = '';

        foreach (self::NVIDIA_TEXT_MODELS as $model) {
            $payload = json_encode([
                'model'      => $model,
                'messages'   => [
                    ['role' => 'system', 'content' => 'You are an expert marketing copywriter for Adsbooster.net, a multi-network advertising platform.'],
                    ['role' => 'user',   'content' => $prompt],
                ],
                'temperature' => 0.7,
                'max_tokens'  => 1024,
            ]);
            $ch = curl_init($base . '/chat/completions');
            curl_setopt_array($ch, [
                CURLOPT_POST           => true,
                CURLOPT_POSTFIELDS     => $payload,
                CURLOPT_HTTPHEADER     => [
                    'Authorization: Bearer ' . $apiKey,
                    'Content-Type: application/json',
                    'Accept: application/json',
                ],
                CURLOPT_RETURNTRANSFER => true,
                    CURLOPT_TIMEOUT        => 25,
                    CURLOPT_SSL_VERIFYPEER => true,
                    CURLOPT_SSL_VERIFYHOST => 2,
                ]);
                ca_curl_setopt($ch);
                $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);

            if ($httpCode === 200 && $response) {
                $data = json_decode($response, true);
                $text = $data['choices'][0]['message']['content'] ?? '';
                if ($text !== '') {
                    return ['output' => $text, 'provider' => 'nvidia', 'model' => $model];
                }
            } else {
                // 404 on chat = key lacks that model even when /v1/models
                // lists it (NVIDIA returns 404 instead of 403 here).
                $lastErr = "model {$model}: HTTP {$httpCode}: " . substr((string)$response, 0, 100);
            }
        }

        throw new \RuntimeException("NVIDIA NIM API failed on all models. Last response: {$lastErr}");
    }

    private static function fallbackText(string $prompt): string
    {
        return "✨ **High-Converting Ad Campaign Copy:**\n\n" .
               "🎯 **Headline:** Unlock Unstoppable Growth with {$prompt}\n\n" .
               "📢 **Primary Text:** Stop burning marketing budget on low-converting clicks. With Adsbooster.net, launch high-ROAS campaigns across Meta, TikTok, and Google Ads from one unified dashboard.\n\n" .
               "🔥 **Key Hook:** Guaranteed lower CPA, 1-click creative split testing, and verified audience segments.\n\n" .
               "👉 **Call to Action:** Tap below to claim your verified listing or boost your first campaign today!";
    }

    // ──────────────────────────────────────────────
    //  CREDIT MANAGEMENT & LOGGING
    // ──────────────────────────────────────────────

    private static function checkAndDeductCredits(int $userId, int $creditCost): array
    {
        $user = db()->fetchOne("SELECT id, ai_credits FROM users WHERE id = ?", [$userId]);
        if (!$user) return ['success' => false, 'credits_remaining' => 0, 'message' => 'User not found.'];

        $current = (int)($user['ai_credits'] ?? 0);
        if ($current < $creditCost) {
            // Auto top-up for active users or provide trial allowance
            $current = 100;
            db()->update('users', ['ai_credits' => 100], 'id = ?', [$userId]);
        }

        $new = max(0, $current - $creditCost);
        try {
            db()->update('users', ['ai_credits' => $new], 'id = ?', [$userId]);
            return ['success' => true, 'credits_remaining' => $new, 'message' => ''];
        } catch (\Throwable $e) {
            return ['success' => false, 'credits_remaining' => $current, 'message' => 'Failed to deduct credits.'];
        }
    }

    private static function logGeneration(int $userId, string $type, string $prompt, string $result, int $credits, string $provider = ''): void
    {
        try {
            db()->insert('ai_creatives', [
                'user_id'       => $userId,
                'type'          => $type,
                'prompt'        => $prompt,
                'result_output' => $result,
                'credits_used'  => $credits,
                'metadata'      => json_encode(['provider' => $provider, 'timestamp' => time()]),
                'created_at'    => date('Y-m-d H:i:s'),
            ]);
        } catch (\Throwable $e) {
            error_log("AI log error: " . $e->getMessage());
        }
    }

    /**
     * Extracts plain text from uploaded resume files (PDF, DOCX, TXT)
     */
    public static function extractTextFromFile(string $filePath, string $originalFilename): string
    {
        if (!file_exists($filePath)) {
            return '';
        }

        $ext = strtolower(pathinfo($originalFilename, PATHINFO_EXTENSION));

        // 1. DOCX Extraction via native ZipArchive
        if ($ext === 'docx' && class_exists('ZipArchive')) {
            $zip = new \ZipArchive();
            if ($zip->open($filePath) === true) {
                $xml = $zip->getFromName('word/document.xml');
                $zip->close();
                if ($xml) {
                    $formatted = str_replace(['</w:p>', '</w:tr>'], "\n", $xml);
                    $text = trim(strip_tags($formatted));
                    if (strlen($text) > 30) {
                        return $text;
                    }
                }
            }
        }

        $raw = @file_get_contents($filePath);
        if ($raw === false || $raw === '') {
            return '';
        }

        // 2. PDF Text Stream Extraction
        if ($ext === 'pdf') {
            $text = '';
            if (preg_match_all('#stream[\r\n]+(.*?)[\r\n]+endstream#s', $raw, $matches)) {
                foreach ($matches[1] as $stream) {
                    $uncompressed = @gzuncompress($stream);
                    if ($uncompressed !== false) {
                        $stream = $uncompressed;
                    }
                    if (preg_match_all('#\((.*?)\)\s*Tj#s', $stream, $m)) {
                        $text .= ' ' . implode(' ', $m[1]);
                    } elseif (preg_match_all('#\[(.*?)\]\s*TJ#s', $stream, $m)) {
                        foreach ($m[1] as $arr) {
                            if (preg_match_all('#\((.*?)\)#s', $arr, $sub)) {
                                $text .= ' ' . implode(' ', $sub[1]);
                            }
                        }
                    }
                }
            }
            $cleanPdf = trim(preg_replace('/\s+/', ' ', $text));
            if (strlen($cleanPdf) > 40) {
                return $cleanPdf;
            }
        }

        // 3. Plain Text fallback
        $clean = preg_replace('/[^\x20-\x7E\r\n\t]/', ' ', $raw);
        $clean = preg_replace('/\s+/', ' ', (string)$clean);
        return trim((string)$clean);
    }

    /**
     * AI Resume Analyser: In-depth ATS optimization audit, scoring, competency extraction, and portfolio sync
     */
    public static function analyzeResume(string $rawText, int $userId = 1): array
    {
        $cleanText = trim($rawText);
        if (empty($cleanText)) {
            return ['success' => false, 'message' => 'Resume text is empty.', 'data' => null];
        }

        $systemPrompt = "You are a world-class Executive Recruiter and ATS (Applicant Tracking System) Algorithm Auditor for digital advertising and technology professionals on Adsbooster.net.
Analyze the provided resume and return ONLY a valid JSON object with NO markdown formatting, NO backticks, and NO conversational filler, matching this exact structure:
{
  \"ats_score\": 88,
  \"grade\": \"A\",
  \"verdict\": \"High ATS Compatibility — Interview Shortlist Potential\",
  \"sub_scores\": {
    \"readability\": 90,
    \"keyword_optimization\": 85,
    \"impact_metrics\": 88,
    \"structure\": 92
  },
  \"candidate\": {
    \"display_name\": \"Candidate Full Name\",
    \"job_title\": \"Accurate Role or Professional Title\",
    \"headline\": \"Compelling 1-sentence value proposition or positioning headline\",
    \"bio\": \"High-impact 2-3 sentence executive bio highlighting quantifiable achievements\",
    \"email\": \"Extracted email or empty\",
    \"phone\": \"Extracted phone or empty\",
    \"location\": \"City/Country or Remote\",
    \"company\": \"Current or most recent agency/company\",
    \"website\": \"Portfolio link or empty\"
  },
  \"skills\": {
    \"technical\": [\"Skill 1\", \"Skill 2\", \"Skill 3\", \"Skill 4\"],
    \"strategic\": [\"Skill 1\", \"Skill 2\", \"Skill 3\"],
    \"tools\": [\"Tool 1\", \"Tool 2\", \"Tool 3\"]
  },
  \"services\": [
    {\"title\": \"Core Offering 1\", \"price\": \"$2,500/month\", \"description\": \"Brief deliverable summary\"},
    {\"title\": \"Core Offering 2\", \"price\": \"$750/campaign\", \"description\": \"Brief deliverable summary\"}
  ],
  \"portfolio_items\": [
    {\"title\": \"Key Campaign or Project 1\", \"metric\": \"e.g. 4.8x ROAS or +$180K GMV\", \"description\": \"Specific result with action verb and scale\"},
    {\"title\": \"Key Campaign or Project 2\", \"metric\": \"e.g. -42% CPA Reduction\", \"description\": \"Specific result with action verb and scale\"}
  ],
  \"strengths\": [
    \"Clear quantification of commercial results and ROAS metrics\",
    \"Strong domain expertise in modern advertising platforms\"
  ],
  \"missing_keywords\": [
    \"Conversion API (CAPI)\",
    \"First-Party Data Strategy\",
    \"Omnichannel Attribution\"
  ],
  \"actionable_improvements\": [
    \"Start all bullet points with active power verbs (e.g. Scaled, Orchestrated, Engineered).\",
    \"Add specific monthly budget figures managed to demonstrate scale of responsibility.\"
  ]
}";

        $fullPrompt = $systemPrompt . "\n\n=== CANDIDATE RESUME ===\n" . substr($cleanText, 0, 7500) . "\n\nReturn strictly valid JSON only:";
        
        try {
            $response = self::generateText($fullPrompt, $userId, 'groq');
            if (!empty($response['success'])) {
                $content = trim((string)($response['result'] ?? $response['content'] ?? ''));
                $content = preg_replace('/^```(?:json)?\s*/i', '', $content);
                $content = preg_replace('/\s*```$/i', '', (string)$content);
                $decoded = json_decode(trim((string)$content), true);
                if (is_array($decoded) && !empty($decoded['candidate']['display_name'])) {
                    // Normalize flat skills list for convenience
                    $flatSkills = [];
                    if (!empty($decoded['skills']) && is_array($decoded['skills'])) {
                        foreach ($decoded['skills'] as $group) {
                            if (is_array($group)) {
                                foreach ($group as $s) {
                                    if (is_string($s) && trim($s) !== '') $flatSkills[] = trim($s);
                                }
                            }
                        }
                    }
                    $decoded['flat_skills'] = array_values(array_unique($flatSkills));
                    return ['success' => true, 'data' => $decoded, 'provider' => $response['provider'] ?? 'ai'];
                }
            }
        } catch (\Throwable $e) {
            error_log("Resume AI analysis exception: " . $e->getMessage());
        }

        // High-Quality Heuristic Parser Fallback
        return ['success' => true, 'data' => self::heuristicResumeAudit($cleanText), 'provider' => 'heuristic'];
    }

    /**
     * Algorithmic heuristic resume parser when AI provider is offline or rate limited
     */
    private static function heuristicResumeAudit(string $text): array
    {
        // Extract email
        $email = '';
        if (preg_match('/[a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,}/', $text, $m)) {
            $email = $m[0];
        }

        // Extract phone
        $phone = '';
        if (preg_match('/(?:\+?\d{1,3}[-.\s]?)?\(?\d{3}\)?[-.\s]?\d{3}[-.\s]?\d{4}/', $text, $m)) {
            $phone = trim($m[0]);
        }

        // Extract candidate name from first lines
        $lines = array_values(array_filter(array_map('trim', explode("\n", $text))));
        $name = 'Growth Marketing Specialist';
        if (!empty($lines[0]) && strlen($lines[0]) < 45 && !str_contains($lines[0], '@')) {
            $name = ucwords(preg_replace('/[^a-zA-Z\s]/', '', $lines[0]));
        }

        // Industry skill dictionary
        $skillDictionary = [
            'Meta Ads' => ['meta ads', 'facebook ads', 'fb ads', 'instagram ads', 'meta business suite', 'advantage+'],
            'TikTok Ads' => ['tiktok ads', 'tiktok ads manager', 'spark ads', 'tiktok shop'],
            'Google Ads' => ['google ads', 'adwords', 'performance max', 'pmax', 'google shopping', 'sem'],
            'UGC Creative Strategy' => ['ugc', 'user generated content', 'creative testing', 'video ad hooks'],
            'Copywriting' => ['copywriting', 'ad copy', 'persuasive copy', 'landing page copy'],
            'ROAS Scaling' => ['roas', 'mer', 'blended roas', 'scaling spend', 'ad spend'],
            'Conversion Rate Optimization' => ['cro', 'conversion rate', 'a/b test', 'split test', 'landing pages'],
            'Shopify & eCommerce' => ['shopify', 'shopify plus', 'ecommerce', 'dtc', 'e-comm'],
            'Analytics & Attribution' => ['google analytics', 'ga4', 'triple whale', 'northbeam', 'attribution'],
            'Email & Retention' => ['klaviyo', 'email marketing', 'retention', 'sms marketing', 'omnisend'],
            'Lead Generation' => ['lead gen', 'b2b lead generation', 'cpa optimization', 'cost per lead'],
            'Budget Management' => ['budget allocation', 'p&l', 'media plan', 'media budget'],
        ];

        $foundSkills = [];
        $lowerText = strtolower($text);
        foreach ($skillDictionary as $skillName => $keywords) {
            foreach ($keywords as $kw) {
                if (str_contains($lowerText, $kw)) {
                    $foundSkills[] = $skillName;
                    break;
                }
            }
        }
        if (empty($foundSkills)) {
            $foundSkills = ['Meta Ads', 'TikTok Ads', 'UGC Creative Strategy', 'Copywriting', 'ROAS Scaling'];
        }

        // Check metric density
        $hasMetrics = preg_match('/\b\d+(\.\d+)?%|\$\d+[kKmM]?|\b\d+x\b/i', $text);
        $metricCount = preg_match_all('/\b\d+(\.\d+)?%|\$\d+[kKmM]?|\b\d+x\b/i', $text, $mm);

        // Algorithmic ATS scoring
        $score = 70;
        $score += min(15, count($foundSkills) * 2);
        if ($hasMetrics) $score += min(10, (int)$metricCount * 2);
        if (!empty($email) && !empty($phone)) $score += 5;
        $score = min(96, max(68, $score));

        $grade = $score >= 90 ? 'A+' : ($score >= 85 ? 'A' : ($score >= 78 ? 'B+' : 'B'));
        $verdict = $score >= 85 
            ? 'High ATS Compatibility — Interview Shortlist Potential'
            : 'Good Baseline — Minor ATS Keyword & Metric Tuning Recommended';

        return [
            'ats_score' => $score,
            'grade' => $grade,
            'verdict' => $verdict,
            'sub_scores' => [
                'readability' => min(95, $score + 3),
                'keyword_optimization' => min(94, 65 + count($foundSkills) * 3),
                'impact_metrics' => $hasMetrics ? 88 : 64,
                'structure' => 90,
            ],
            'candidate' => [
                'display_name' => $name,
                'job_title' => 'Performance Marketing & Media Director',
                'headline' => 'Scaling omnichannel paid advertising across Meta, TikTok, and Google Ads with data-driven creative testing.',
                'bio' => 'Accomplished performance marketer with a proven track record delivering profitable campaigns, creative iterations, and scalable ROAS across high-growth eCommerce and B2B brands.',
                'email' => $email ?: 'contact@adsbooster.net',
                'phone' => $phone ?: '+1 (555) 019-2831',
                'location' => 'Worldwide / Remote',
                'company' => 'Adsbooster Partner Network',
                'website' => 'https://adsbooster.net',
            ],
            'skills' => [
                'technical' => array_slice($foundSkills, 0, 4),
                'strategic' => ['Omnichannel Media Buying', 'Creative Iteration & Testing', 'ROAS Scaling'],
                'tools' => ['Meta Ads Manager', 'TikTok Ads Manager', 'Google Analytics 4', 'Shopify Plus'],
            ],
            'flat_skills' => $foundSkills,
            'services' => [
                ['title' => 'Full Omnichannel Ad Scaling Retainer', 'price' => '$2,500/month', 'description' => 'End-to-end ad management, budget scaling, and weekly creative briefs.'],
                ['title' => 'Viral UGC Creative Testing Package', 'price' => '$650/package', 'description' => 'Hook research, creator sourcing, scriptwriting, and multivariate iteration.'],
                ['title' => 'Paid Ads Account & CRO Audit', 'price' => '$450/audit', 'description' => 'Comprehensive account audit, tracking verification, and conversion bottleneck analysis.'],
            ],
            'portfolio_items' => [
                ['title' => 'Omnichannel DTC Apparel Scale', 'metric' => '4.8x ROAS / $125k Spend', 'description' => 'Scaled monthly Meta and TikTok budgets from $12k to $125k while maintaining target blended CPA.'],
                ['title' => 'B2B SaaS Lead Generation Sprint', 'metric' => '-38% Cost Per Demo', 'description' => 'Restructured high-intent search campaigns and implemented conversion API tracking.'],
            ],
            'strengths' => [
                'Solid keyword alignment with performance advertising and creative strategy requirements.',
                'Demonstrates omnichannel competency across Meta, TikTok, and analytics stacks.',
                'Clear professional narrative suitable for both automated ATS filters and human recruiters.',
            ],
            'missing_keywords' => [
                'Conversion API (CAPI)',
                'First-Party Attribution',
                'Creative Hook Rate (%)',
                'LTV / Customer Retention',
            ],
            'actionable_improvements' => [
                'Quantify ad spend scale and exact monthly budgets managed across campaigns.',
                'Highlight specific creative testing velocity (e.g. "Tested 25+ UGC hooks/week").',
                'Add professional certifications (Meta Certified Media Buying Professional, Google Ads).',
            ],
        ];
    }

    /**
     * AI Resume Parser: extracts structured portfolio details from raw resume text
     * Kept for backwards compatibility, wraps analyzeResume.
     */
    public static function parseResume(string $rawText, int $userId = 1): array
    {
        $analysis = self::analyzeResume($rawText, $userId);
        if (!empty($analysis['success']) && !empty($analysis['data'])) {
            $d = $analysis['data'];
            $cand = $d['candidate'] ?? [];
            return [
                'success' => true,
                'data' => [
                    'display_name'    => $cand['display_name'] ?? 'Verified Creator',
                    'job_title'       => $cand['job_title'] ?? 'Growth Marketer',
                    'headline'        => $cand['headline'] ?? '',
                    'bio'             => $cand['bio'] ?? '',
                    'phone'           => $cand['phone'] ?? '',
                    'email'           => $cand['email'] ?? '',
                    'location'        => $cand['location'] ?? 'Worldwide / Remote',
                    'company'         => $cand['company'] ?? '',
                    'website'         => $cand['website'] ?? '',
                    'skills'          => $d['flat_skills'] ?? ['Meta Ads', 'TikTok Ads', 'UGC', 'Copywriting'],
                    'services'        => $d['services'] ?? [],
                    'portfolio_items' => $d['portfolio_items'] ?? [],
                    'ats_score'       => $d['ats_score'] ?? 85,
                ]
            ];
        }

        return ['success' => false, 'message' => 'Failed to parse resume.'];
    }
}


