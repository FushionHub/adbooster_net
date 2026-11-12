<?php
declare(strict_types=1);

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../../modules/gemini_engine.php';

/**
 * Unit tests for the image engine's routing contract and circuit breaker.
 *
 * These never call an image provider — they pin the decision logic that
 * decides WHICH route is used and WHEN a broken provider is paused:
 *
 *   • a configured premium key (Gemini/NVIDIA) must win over the free route;
 *   • the free route is the fallback, never the competitor;
 *   • a provider that fails hard is paused site-wide for 30 minutes;
 *   • a provider that answers but produces no image is a "miss";
 *   • a provider that succeeds clears the pause again.
 */
#[CoversClass(GeminiEngine::class)]
final class ImageEngineTest extends TestCase
{
    private string $breakerFile;

    /** @var array<string,mixed> */
    private array $originalState = [];

    protected function setUp(): void
    {
        $this->breakerFile = ROOT_PATH . '/cache/nvidia_image_circuit.json';
        if (is_file($this->breakerFile)) {
            $this->originalState = (array)json_decode((string)file_get_contents($this->breakerFile), true);
        }
        @unlink($this->breakerFile);
    }

    protected function tearDown(): void
    {
        @unlink($this->breakerFile);
        if ($this->originalState !== []) {
            @file_put_contents($this->breakerFile, json_encode($this->originalState));
        }
    }

    private function call(string $method, array $args = []): mixed
    {
        $ref = new ReflectionMethod(GeminiEngine::class, $method);
        $ref->setAccessible(true);
        return $ref->invokeArgs(null, $args);
    }

    public function testReadyIsTrueEvenWithoutAnyPremiumKey(): void
    {
        // The free keyless route always exists, so the engine is usable.
        $this->assertTrue(GeminiEngine::ready());
    }

    public function testEmptyPromptReturnsNullWithoutCallingProviders(): void
    {
        $this->assertNull(GeminiEngine::generateImage('   '));
    }

    public function testNvidiaModelNameReflectsConfiguredEndpoint(): void
    {
        $name = (string)$this->call('nvidiaModelName');
        $this->assertStringStartsWith('nvidia-', $name);
        $this->assertStringNotContainsString('://', $name);
    }

    public function testHardFailureOpensTheCircuit(): void
    {
        $this->assertFalse((bool)$this->call('nvidiaBreakerOpen'));

        $this->call('nvidiaTripBreaker');

        $this->assertTrue((bool)$this->call('nvidiaBreakerOpen'));
        $state = (array)$this->call('nvidiaCircuitState');
        $this->assertGreaterThan(time(), (int)$state['open_until']);
    }

    public function testMissesAccumulateAndOpenTheCircuitOnTheThird(): void
    {
        $this->call('nvidiaNoteMiss');
        $this->assertFalse((bool)$this->call('nvidiaBreakerOpen'), 'one miss must not open the circuit');

        $this->call('nvidiaNoteMiss');
        $this->assertFalse((bool)$this->call('nvidiaBreakerOpen'), 'two misses must not open the circuit');

        $this->call('nvidiaNoteMiss');
        $this->assertTrue((bool)$this->call('nvidiaBreakerOpen'), 'three misses must open the circuit');
    }

    public function testSuccessClearsTheCircuit(): void
    {
        $this->call('nvidiaTripBreaker');
        $this->assertTrue((bool)$this->call('nvidiaBreakerOpen'));

        $this->call('nvidiaClearBreaker');

        $this->assertFalse((bool)$this->call('nvidiaBreakerOpen'));
    }

    public function testExpiredCircuitIsTreatedAsClosed(): void
    {
        // Expired pause → the route is retried again (self-healing).
        @file_put_contents($this->breakerFile, json_encode(['open_until' => time() - 10, 'misses' => 0]));
        $this->assertFalse((bool)$this->call('nvidiaBreakerOpen'));
    }

    public function testCorruptedCircuitFileFailsSafe(): void
    {
        @file_put_contents($this->breakerFile, '{not-json');
        $this->assertFalse((bool)$this->call('nvidiaBreakerOpen'));
        $state = (array)$this->call('nvidiaCircuitState');
        $this->assertSame(0, (int)$state['open_until']);
        $this->assertSame(0, (int)$state['misses']);
    }

    public function testCircuitFileIsWrittenAtomically(): void
    {
        $this->call('nvidiaTripBreaker');
        $this->assertFileExists($this->breakerFile);
        // No stray temp files left behind.
        $leftovers = glob(ROOT_PATH . '/cache/' . basename($this->breakerFile) . '.*.tmp') ?: [];
        $this->assertSame([], $leftovers);
    }
}
