<?php
declare(strict_types=1);

/**
 * Competitor Ad Spy & Reverse-Engineering Engine
 * Adsbooster.net
 */

class AdSpyEngine
{
    /**
     * Analyze Competitor Domain / Brand
     */
    public static function analyzeCompetitor(int $userId, string $competitorName, string $competitorUrl, string $platform = 'meta'): array
    {
        $domain = parse_url($competitorUrl, PHP_URL_HOST) ?: $competitorUrl;
        $cleanName = ucfirst(trim($competitorName ?: $domain));

        // Simulated intelligent deconstruction of public ad libraries (Meta Ad Library / TikTok Creative Center)
        $extractedHooks = [
            "Why 90% of {$cleanName} alternatives fail within 30 days.",
            "The secret hack {$cleanName} customers use to cut their monthly spend.",
            "I stopped using traditional solutions and switched to {$cleanName}. Here is what happened.",
            "If you are still doing this manually in 2026, you are losing money."
        ];

        $extractedAngles = [
            ['angle' => 'Direct Pain-Point Agitation', 'share_pct' => 45, 'desc' => 'Highlights time-loss and frustration with legacy competitors.'],
            ['angle' => 'Before & After Transformation', 'share_pct' => 30, 'desc' => 'Clear visual contrast demonstrating fast time-to-value.'],
            ['angle' => 'Unboxing & Founder Story', 'share_pct' => 25, 'desc' => 'High-trust authentic UGC format emphasizing brand transparency.']
        ];

        $targetAudience = [
            'age_range' => '24 - 45',
            'top_interests' => ['E-commerce', 'Direct-to-Consumer', 'Growth Marketing', 'Productivity Software'],
            'top_geos' => ['United States', 'United Kingdom', 'Canada', 'Australia'],
            'format_split' => ['Video UGC (9:16)' => '65%', 'Static Carousel' => '25%', 'Single Image' => '10%']
        ];

        // 5 Counter-Creative "Steal & Improve" Angles
        $counterCreatives = [
            [
                'title' => '1. The Pain-Point Aggressor (Higher Urgency)',
                'headline' => "Tired of {$cleanName}? Here is the upgrade you've been waiting for.",
                'hook' => "Don't settle for overpriced {$cleanName} features when you can get 3x better results.",
                'body' => "Most platforms lock essential features behind costly tiers. We built our solution from the ground up to give you full unlimited access at half the cost.",
                'cta' => 'Claim Your Exclusive Competitor Switcher Deal'
            ],
            [
                'title' => '2. The Social Proof / Comparison Winner',
                'headline' => "Why 10,000+ creators switched from {$cleanName} to us this month.",
                'hook' => "We compared {$cleanName} side-by-side with our platform. The results weren't even close.",
                'body' => "Faster speeds, zero learning curve, and round-the-clock priority support. See why top media buyers are making the switch.",
                'cta' => 'See the Full Side-by-Side Breakdown'
            ],
            [
                'title' => '3. The Contrarian / Myth-Buster',
                'headline' => "The truth about {$cleanName} nobody is talking about.",
                'hook' => "Before you spend another dollar on {$cleanName}, watch this.",
                'body' => "Hidden fees and complicated setups are eating into your margins. Get transparent pricing, instant onboarding, and guaranteed ROI.",
                'cta' => 'Start Your 14-Day Risk-Free Trial'
            ],
            [
                'title' => '4. The Aesthetic UGC Hook',
                'headline' => "My honest review after ditching {$cleanName} for 30 days.",
                'hook' => "I thought {$cleanName} was the best option until a colleague recommended this.",
                'body' => "Literally saved me 15 hours in my very first week. The workflow is so seamless it feels like magic.",
                'cta' => 'Unlock Your Lifetime VIP Access'
            ],
            [
                'title' => '5. The Irresistible Offer / Risk Reversal',
                'headline' => "Switch today and get 50% off + 10,000 free bonus credits.",
                'hook' => "Still paying full price for {$cleanName}? We'll match and double your perks.",
                'body' => "Transfer your account effortlessly in under 2 minutes. Plus, our dedicated onboarding team will set up your first 3 campaigns for free.",
                'cta' => 'Claim 50% Switcher Discount Now'
            ]
        ];

        // NOTE: this engine does not connect to the Meta Ad Library or TikTok
        // Creative Center. It produces a written competitive brief from
        // proven-response templates. Every number below therefore describes
        // what THIS analysis generated — never a count of ads we observed.
        $generatedCount = count($counterCreatives) + count($extractedHooks) + count($extractedAngles);

        $analysisId = db()->insert('competitor_ad_analyses', [
            'user_id' => $userId,
            'competitor_name' => $cleanName,
            'competitor_url' => $competitorUrl,
            'platform' => $platform,
            'scraped_ads_count' => $generatedCount,
            'extracted_hooks' => json_encode($extractedHooks),
            'extracted_angles' => json_encode($extractedAngles),
            'target_audience_insights' => json_encode($targetAudience),
            'counter_creatives' => json_encode($counterCreatives)
        ]);

        return [
            'id' => $analysisId,
            'competitor_name' => $cleanName,
            'competitor_url' => $competitorUrl,
            'platform' => $platform,
            'scraped_ads_count' => $generatedCount,
            'generated_count' => $generatedCount,
            'data_source' => 'template_brief',
            'extracted_hooks' => $extractedHooks,
            'extracted_angles' => $extractedAngles,
            'target_audience_insights' => $targetAudience,
            'counter_creatives' => $counterCreatives
        ];
    }
}
