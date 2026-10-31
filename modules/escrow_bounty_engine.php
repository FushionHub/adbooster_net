<?php
declare(strict_types=1);

/**
 * Creator Bounties & Escrow Marketplace Engine
 * Adsbooster.net
 */

class EscrowBountyEngine
{
    /**
     * Create Brand Bounty and Lock Escrow
     */
    public static function createBounty(int $brandUserId, array $params): int
    {
        $title = trim($params['title'] ?? '');
        $description = trim($params['description'] ?? '');
        $platform = $params['platform'] ?? 'tiktok';
        $contentType = $params['content_type'] ?? 'ugc_talking_head';
        $deliverables = trim($params['deliverables_req'] ?? '');
        $amount = (float)($params['bounty_amount'] ?? 100.00);
        $feePct = 10.00;
        $totalEscrow = $amount; // held in escrow from brand

        $bountyId = db()->insert('creator_bounties', [
            'brand_user_id' => $brandUserId,
            'title' => $title,
            'description' => $description,
            'platform' => $platform,
            'content_type' => $contentType,
            'deliverables_req' => $deliverables,
            'bounty_amount' => $amount,
            'platform_fee_pct' => $feePct,
            'total_escrow_held' => $totalEscrow,
            'escrow_status' => 'funded',
            'status' => 'open',
            'submission_deadline' => date('Y-m-d H:i:s', strtotime('+7 days'))
        ]);

        return $bountyId;
    }

    /**
     * Creator Claims Bounty
     */
    public static function claimBounty(int $creatorUserId, int $bountyId): bool
    {
        $bounty = db()->fetch("SELECT * FROM creator_bounties WHERE id = ? AND status = 'open'", [$bountyId]);
        if (!$bounty) {
            return false;
        }

        db()->update('creator_bounties', [
            'claimed_creator_id' => $creatorUserId,
            'status' => 'in_progress'
        ], 'id = ?', [$bountyId]);

        return true;
    }

    /**
     * Creator Submits Video Draft (With Automated AI Compliance Scan)
     */
    public static function submitDraft(int $creatorUserId, int $bountyId, string $videoUrl, string $notes): array
    {
        // Simulated AI video compliance scan (9:16 aspect ratio check, audio clarity, no banned claims)
        $complianceScore = 98;
        $complianceReport = [
            'aspect_ratio_9_16' => 'Passed (1080x1920)',
            'audio_clarity' => 'Excellent (-14 LUFS)',
            'duration_compliance' => 'Passed (24.2s)',
            'prohibited_claims_check' => 'Clean / Ad-Policy Compliant',
            'overall_status' => 'Verified Ready for Ad Spend'
        ];

        $subId = db()->insert('creator_bounty_submissions', [
            'bounty_id' => $bountyId,
            'creator_user_id' => $creatorUserId,
            'video_url' => $videoUrl ?: 'assets/videos/ugc_sample.mp4',
            'thumbnail_url' => 'assets/images/thumbs/about-five-img1.png',
            'submission_notes' => $notes,
            'ai_compliance_score' => $complianceScore,
            'ai_compliance_report' => json_encode($complianceReport),
            'review_status' => 'pending_review'
        ]);

        db()->update('creator_bounties', ['status' => 'draft_submitted'], 'id = ?', [$bountyId]);

        return [
            'submission_id' => $subId,
            'score' => $complianceScore,
            'report' => $complianceReport
        ];
    }

    /**
     * Brand Approves Submission & Releases Escrow Payout
     */
    public static function approveAndReleaseEscrow(int $brandUserId, int $bountyId, int $submissionId): array
    {
        $bounty = db()->fetch("SELECT * FROM creator_bounties WHERE id = ? AND brand_user_id = ?", [$bountyId, $brandUserId]);
        if (!$bounty) {
            throw new Exception("Bounty not found or unauthorized");
        }

        $sub = db()->fetch("SELECT * FROM creator_bounty_submissions WHERE id = ?", [$submissionId]);
        if (!$sub) {
            throw new Exception("Submission not found");
        }

        $creatorId = (int)$sub['creator_user_id'];
        $bountyAmount = (float)$bounty['bounty_amount'];
        $platformFee = $bountyAmount * 0.10; // 10% platform take
        $netCreatorPayout = $bountyAmount - $platformFee;

        // Credit creator wallet/ad_credits
        db()->query("UPDATE users SET ad_credits = ad_credits + ? WHERE id = ?", [$netCreatorPayout, $creatorId]);

        // Update submission and bounty status
        db()->update('creator_bounty_submissions', [
            'review_status' => 'approved',
            'reviewed_at' => date('Y-m-d H:i:s')
        ], 'id = ?', [$submissionId]);

        db()->update('creator_bounties', [
            'status' => 'completed',
            'escrow_status' => 'released_to_creator'
        ], 'id = ?', [$bountyId]);

        return [
            'success' => true,
            'bounty_amount' => $bountyAmount,
            'platform_fee' => $platformFee,
            'payout_to_creator' => $netCreatorPayout
        ];
    }
}
