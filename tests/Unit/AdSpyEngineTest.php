<?php
declare(strict_types=1);

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../../modules/ad_spy_engine.php';

/**
 * Unit tests for the competitor brief's honesty contract.
 *
 * The brief used to invent its own evidence: a competitor angle split
 * (45% / 30% / 25%), an audience profile (age 24-45, top geos), and
 * counter-campaign headlines such as "Why 10,000+ creators switched" or
 * "Switch today and get 50% off" that a user could publish verbatim.
 *
 * These tests pin the rules that replace it:
 *
 *   • "observed" content is quoted verbatim from the fetched page and every
 *     messaging signal carries the line that proves it;
 *   • nothing is observed when nothing was fetched — no invented hooks and
 *     no invented signals;
 *   • counter-campaigns never contain unverifiable proof claims, and always
 *     demand the user's own evidence through explicit placeholders.
 */
#[CoversClass(AdSpyEngine::class)]
final class AdSpyEngineTest extends TestCase
{
    /**
     * A page we actually fetched: every line below was really on it.
     *
     * @return array<string,mixed>
     */
    private function observation(): array
    {
        return [
            'status' => 'observed',
            'source_url' => 'https://competitor.test',
            'scraped_at' => '2026-11-19 12:00:00 UTC',
            'page_title' => 'Acme — Growth in 30 days',
            'meta_description' => 'Acme helps direct-to-consumer brands grow revenue.',
            'copy_lines' => [
                'Join 2,000 teams that grew revenue with Acme',
                'Save 40% on your first campaign',
                'Start your free trial today',
                'Acme helps direct-to-consumer brands grow revenue.',
                'Acme — Growth in 30 days',
            ],
        ];
    }

    public function testObservedBriefQuotesOnlyCopyThatWasActuallyFetched(): void
    {
        $observation = $this->observation();
        $brief = AdSpyEngine::buildBrief('Acme', 'https://competitor.test', 'meta', $observation);

        $this->assertSame('observed_page', $brief['data_source']);
        $this->assertSame(count($observation['copy_lines']), $brief['observed_line_count']);

        $hookTexts = array_column($brief['extracted_hooks'], 'text');
        $this->assertNotEmpty($hookTexts);
        foreach ($hookTexts as $text) {
            $this->assertContains(
                $text,
                $observation['copy_lines'],
                'A hook must be a verbatim quote from the fetched page.'
            );
            $this->assertSame('observed', $brief['extracted_hooks'][array_search($text, $hookTexts, true)]['source']);
        }
    }

    public function testEveryMessagingSignalCarriesTheLineThatProvesIt(): void
    {
        $observation = $this->observation();
        $brief = AdSpyEngine::buildBrief('Acme', 'https://competitor.test', 'meta', $observation);

        $this->assertNotEmpty($brief['extracted_angles']);
        foreach ($brief['extracted_angles'] as $angle) {
            $this->assertNotEmpty($angle['evidence'], 'A signal without evidence is a guess.');
            $this->assertSame('observed', $angle['basis']);
            foreach ($angle['evidence'] as $line) {
                $this->assertContains($line, $observation['copy_lines']);
            }
        }
    }

    public function testBriefWithoutAFetchedPageMakesNoObservedClaims(): void
    {
        $brief = AdSpyEngine::buildBrief('Acme', '', 'meta', [
            'status' => 'not_requested',
            'message' => 'No public website URL was supplied, so nothing was fetched.',
        ]);

        $this->assertSame('template_only', $brief['data_source']);
        $this->assertSame([], $brief['extracted_hooks']);
        $this->assertSame([], $brief['extracted_angles']);
        $this->assertSame(0, $brief['observed_line_count']);
        $this->assertNotEmpty($brief['counter_creatives'], 'Draft templates are still useful.');
    }

    public function testFailedFetchIsReportedAndProducesNoObservedClaims(): void
    {
        $brief = AdSpyEngine::buildBrief('Acme', 'https://competitor.test', 'meta', [
            'status' => 'failed',
            'source_url' => 'https://competitor.test',
            'message' => 'Failed to fetch URL (HTTP 404).',
        ]);

        $this->assertSame('template_only', $brief['data_source']);
        $this->assertSame([], $brief['extracted_hooks']);
        $this->assertSame([], $brief['extracted_angles']);
        $this->assertSame('Failed to fetch URL (HTTP 404).', $brief['target_audience_insights']['observed']['message']);
    }

    public function testCounterCampaignsCarryNoUnverifiableProofClaims(): void
    {
        $brief = AdSpyEngine::buildBrief('Acme', 'https://competitor.test', 'meta', $this->observation());
        $copy = json_encode($brief['counter_creatives']);

        $forbidden = [
            '10,000+ creators switched',
            '50% off',
            'guaranteed ROI',
            '3x better results',
            '14-Day Risk-Free Trial',
            '10,000 free bonus credits',
            'saved me 15 hours',
        ];
        foreach ($forbidden as $claim) {
            $this->assertStringNotContainsStringIgnoringCase(
                $claim,
                (string)$copy,
                'Generated ad copy must not contain proof we cannot verify.'
            );
        }
    }

    public function testCounterCampaignsDemandTheUsersOwnProof(): void
    {
        $brief = AdSpyEngine::buildBrief('Acme', 'https://competitor.test', 'meta', $this->observation());

        $this->assertNotEmpty($brief['counter_creatives']);
        foreach ($brief['counter_creatives'] as $creative) {
            $this->assertNotEmpty($creative['requires'], 'A draft must say what has to be filled in.');
            $this->assertMatchesRegularExpression(
                '/\[[^\]]+\]/',
                $creative['headline'] . ' ' . $creative['body'],
                'A draft must contain a visible placeholder for the missing proof.'
            );
        }
    }

    public function testUnverifiableClaimDetectorCatchesTheOldFabricatedCopy(): void
    {
        $this->assertTrue(AdSpyEngine::containsUnverifiableClaim('Why 10,000+ creators switched to us'));
        $this->assertTrue(AdSpyEngine::containsUnverifiableClaim('Switch today and get 50% off'));
        $this->assertTrue(AdSpyEngine::containsUnverifiableClaim('Get 3x better results'));
        $this->assertTrue(AdSpyEngine::containsUnverifiableClaim('We guarantee ROI'));
        $this->assertTrue(AdSpyEngine::containsUnverifiableClaim('Claim 50% Switcher Discount Now'));
        $this->assertFalse(AdSpyEngine::containsUnverifiableClaim('A simpler way to grow revenue than Acme.'));
        $this->assertFalse(AdSpyEngine::containsUnverifiableClaim('Replace this with a measured result you can evidence.'));
    }
}
