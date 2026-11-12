<?php
declare(strict_types=1);

use PHPUnit\Framework\Attributes\CoversFunction;
use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../../config/site_setting_and_config.php';

/**
 * Unit tests for media_url() — the helper that turns stored media paths
 * into usable URLs (relative paths → BASE_URL, remote URLs untouched).
 */
#[CoversFunction('media_url')]
final class MediaUrlTest extends TestCase
{
    public function testEmptyAndNullReturnEmptyString(): void
    {
        $this->assertSame('', media_url(''));
        $this->assertSame('', media_url(null));
        $this->assertSame('', media_url('   '));
    }

    public function testRelativePathIsPrefixedWithBaseUrl(): void
    {
        $this->assertSame(
            rtrim(BASE_URL, '/') . '/uploads/videos/clip.mp4',
            media_url('uploads/videos/clip.mp4')
        );
        $this->assertSame(
            rtrim(BASE_URL, '/') . '/uploads/videos/clip.mp4',
            media_url('/uploads/videos/clip.mp4')
        );
    }

    public function testAbsoluteRemoteUrlIsPreserved(): void
    {
        $this->assertSame(
            'https://replicate.delivery/x/video.mp4',
            media_url('https://replicate.delivery/x/video.mp4')
        );
        $this->assertSame(
            'http://cdn.example.org/x.png',
            media_url('http://cdn.example.org/x.png')
        );
    }

    public function testProtocolRelativeUrlUsesTheSiteScheme(): void
    {
        $scheme = parse_url(rtrim(BASE_URL, '/'), PHP_URL_SCHEME) ?: 'https';
        $this->assertSame($scheme . '://cdn.example.org/x.png', media_url('//cdn.example.org/x.png'));
    }

    public function testNonStringValuesDoNotThrowUnderStrictTypes(): void
    {
        // The app fatals on TypeErrors from NULL columns — the helper must
        // never be the cause.
        $this->assertSame('', media_url(null));
        $this->assertStringEndsWith('/123', media_url(123));
    }

    public function testJavascriptSchemeIsNeutralised(): void
    {
        $out = media_url('javascript:alert(1)');
        $this->assertStringStartsNotWith('javascript:', $out);
        $this->assertStringContainsString(rtrim(BASE_URL, '/'), $out);
    }
}
