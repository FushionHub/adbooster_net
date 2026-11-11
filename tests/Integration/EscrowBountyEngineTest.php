<?php
declare(strict_types=1);

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\RequiresPhpExtension;

require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../modules/escrow_bounty_engine.php';

/**
 * Integration tests: EscrowBountyEngine against an isolated test DB.
 *
 * Financial logic: target coverage 100%. This suite covers the full
 * escrow lifecycle and the payout math (10% platform fee / 90% payout).
 *
 * Recreate the test DB with: php -d extension=php_pdo_mysql.dll tests/make-test-db.php
 */
#[CoversClass(EscrowBountyEngine::class)]
#[RequiresPhpExtension('pdo_mysql')]
final class EscrowBountyEngineTest extends TestCase
{
    private int $brandUserId;
    private int $creatorUserId;

    protected function setUp(): void
    {
        // Sandbox a fresh brand + creator pair for every test
        $this->brandUserId = db()->insert('users', [
            'email'    => 'brand_' . bin2hex(random_bytes(6)) . '@test.local',
            'full_name' => 'Test Brand',
            'status'   => 'active',
        ]);
        $this->creatorUserId = db()->insert('users', [
            'email'    => 'creator_' . bin2hex(random_bytes(6)) . '@test.local',
            'full_name' => 'Test Creator',
            'status'   => 'active',
            'ad_credits' => 0,
        ]);
    }

    protected function tearDown(): void
    {
        db()->delete('creator_bounty_submissions', 'bounty_id IS NOT NULL AND bounty_id IN (SELECT id FROM creator_bounties WHERE brand_user_id = ?)', [$this->brandUserId]);
        db()->delete('creator_bounties', 'brand_user_id = ?', [$this->brandUserId]);
        db()->delete('users', 'id IN (?, ?)', [$this->brandUserId, $this->creatorUserId]);
    }

    private function createFundedBounty(): int
    {
        return EscrowBountyEngine::createBounty($this->brandUserId, [
            'title'           => 'UGC talking head for skincare launch',
            'description'     => '30s vertical video',
            'bounty_amount'   => 200.00,
        ]);
    }

    // ---------------------------------------------------------------
    // createBounty()
    // ---------------------------------------------------------------

    public function testCreateBountyLocksFullAmountInEscrow(): void
    {
        $bountyId = $this->createFundedBounty();
        $this->assertGreaterThan(0, $bountyId);

        $bounty = db()->fetchOne("SELECT * FROM creator_bounties WHERE id = ?", [$bountyId]);

        $this->assertSame(200.00, (float)$bounty['bounty_amount']);
        $this->assertSame(200.00, (float)$bounty['total_escrow_held'], 'Escrow must hold the full bounty amount');
        $this->assertSame(10.00, (float)$bounty['platform_fee_pct']);
        $this->assertSame('funded', $bounty['escrow_status']);
        $this->assertSame('open', $bounty['status']);
        $this->assertSame('tiktok', $bounty['platform'], 'Platform default');
        $this->assertSame('ugc_talking_head', $bounty['content_type'], 'Content type default');
        $this->assertNotSame('', $bounty['submission_deadline'], 'A 7-day deadline must be set');
    }

    public function testCreateBountyAppliesDefaultsForMissingParams(): void
    {
        $bountyId = EscrowBountyEngine::createBounty($this->brandUserId, []);
        $bounty = db()->fetchOne("SELECT bounty_amount, status FROM creator_bounties WHERE id = ?", [$bountyId]);

        $this->assertSame(100.00, (float)$bounty['bounty_amount'], 'Documented default bounty amount');
        $this->assertSame('open', $bounty['status']);
    }

    // ---------------------------------------------------------------
    // claimBounty()
    // ---------------------------------------------------------------

    public function testClaimBountyMovesOpenBountyToInProgress(): void
    {
        $bountyId = $this->createFundedBounty();

        $this->assertTrue(EscrowBountyEngine::claimBounty($this->creatorUserId, $bountyId));

        $bounty = db()->fetchOne("SELECT status, claimed_creator_id FROM creator_bounties WHERE id = ?", [$bountyId]);
        $this->assertSame('in_progress', $bounty['status']);
        $this->assertSame((string)$this->creatorUserId, (string)$bounty['claimed_creator_id']);
    }

    public function testClaimBountyFailsForUnknownBounty(): void
    {
        $this->assertFalse(EscrowBountyEngine::claimBounty($this->creatorUserId, 999999999));
    }

    public function testClaimBountyCannotClaimAlreadyClaimedBounty(): void
    {
        $bountyId = $this->createFundedBounty();
        EscrowBountyEngine::claimBounty($this->creatorUserId, $bountyId);

        // Second creator cannot claim: status filter is 'open'
        $this->assertFalse(EscrowBountyEngine::claimBounty($this->brandUserId + 1, $bountyId));
    }

    // ---------------------------------------------------------------
    // submitDraft()
    // ---------------------------------------------------------------

    public function testSubmitDraftRecordsComplianceReportAndUpdatesStatus(): void
    {
        $bountyId = $this->createFundedBounty();
        EscrowBountyEngine::claimBounty($this->creatorUserId, $bountyId);

        $result = EscrowBountyEngine::submitDraft($this->creatorUserId, $bountyId, 'assets/videos/draft.mp4', 'first cut');

        $this->assertGreaterThan(0, $result['submission_id']);
        $this->assertSame(98, $result['score']);
        $this->assertArrayHasKey('aspect_ratio_9_16', $result['report']);
        $this->assertArrayHasKey('overall_status', $result['report']);

        $sub = db()->fetchOne("SELECT * FROM creator_bounty_submissions WHERE id = ?", [$result['submission_id']]);
        $this->assertSame((string)$this->creatorUserId, (string)$sub['creator_user_id']);
        $this->assertSame('pending_review', $sub['review_status']);
        $this->assertJson($sub['ai_compliance_report']);

        $bounty = db()->fetchOne("SELECT status FROM creator_bounties WHERE id = ?", [$bountyId]);
        $this->assertSame('draft_submitted', $bounty['status']);
    }

    public function testSubmitDraftSubstitutesSampleVideoWhenEmpty(): void
    {
        $bountyId = $this->createFundedBounty();
        $result = EscrowBountyEngine::submitDraft($this->creatorUserId, $bountyId, '', '');

        $sub = db()->fetchOne("SELECT video_url FROM creator_bounty_submissions WHERE id = ?", [$result['submission_id']]);
        $this->assertSame('assets/videos/ugc_sample.mp4', $sub['video_url']);
    }

    // ---------------------------------------------------------------
    // approveAndReleaseEscrow() — the payout math
    // ---------------------------------------------------------------

    public function testApproveReleasesNinetyPercentToCreator(): void
    {
        $bountyId = $this->createFundedBounty();          // 200.00
        $result = EscrowBountyEngine::submitDraft($this->creatorUserId, $bountyId, 'assets/videos/draft.mp4', 'note');
        $submissionId = $result['submission_id'];

        $payout = EscrowBountyEngine::approveAndReleaseEscrow($this->brandUserId, $bountyId, $submissionId);

        $this->assertTrue($payout['success']);
        $this->assertSame(200.00, $payout['bounty_amount']);
        $this->assertSame(20.00, $payout['platform_fee'], '10% platform fee on 200');
        $this->assertSame(180.00, $payout['payout_to_creator'], '90% creator payout on 200');

        // Wallet credit must match the payout exactly
        $creator = db()->fetchOne("SELECT ad_credits FROM users WHERE id = ?", [$this->creatorUserId]);
        $this->assertSame(180.00, (float)$creator['ad_credits']);

        // Statuses must be finalised
        $bounty = db()->fetchOne("SELECT status, escrow_status FROM creator_bounties WHERE id = ?", [$bountyId]);
        $this->assertSame('completed', $bounty['status']);
        $this->assertSame('released_to_creator', $bounty['escrow_status']);

        $sub = db()->fetchOne("SELECT review_status FROM creator_bounty_submissions WHERE id = ?", [$submissionId]);
        $this->assertSame('approved', $sub['review_status']);
    }

    public function testApproveRejectsBrandThatDoesNotOwnBounty(): void
    {
        $bountyId = $this->createFundedBounty();
        $result = EscrowBountyEngine::submitDraft($this->creatorUserId, $bountyId, '', '');
        $submissionId = $result['submission_id'];

        $this->expectException(Exception::class);
        $this->expectExceptionMessage('unauthorized');
        EscrowBountyEngine::approveAndReleaseEscrow($this->brandUserId + 1, $bountyId, $submissionId);
    }

    public function testApproveRejectsUnknownSubmission(): void
    {
        $bountyId = $this->createFundedBounty();

        $this->expectException(Exception::class);
        $this->expectExceptionMessage('Submission not found');
        EscrowBountyEngine::approveAndReleaseEscrow($this->brandUserId, $bountyId, 999999999);
    }

    public function testApproveCannotReleaseEscrowTwice(): void
    {
        $bountyId = $this->createFundedBounty();          // 200.00
        $result = EscrowBountyEngine::submitDraft($this->creatorUserId, $bountyId, '', '');
        $submissionId = $result['submission_id'];

        EscrowBountyEngine::approveAndReleaseEscrow($this->brandUserId, $bountyId, $submissionId);

        // Double release would credit the creator AGAIN — financial bug guard
        $this->expectException(Exception::class);
        EscrowBountyEngine::approveAndReleaseEscrow($this->brandUserId, $bountyId, $submissionId);
    }
}
