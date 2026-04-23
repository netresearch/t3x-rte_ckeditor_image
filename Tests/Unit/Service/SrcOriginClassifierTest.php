<?php

/*
 * Copyright (c) 2025-2026 Netresearch DTT GmbH
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

declare(strict_types=1);

namespace Netresearch\RteCKEditorImage\Tests\Unit\Service;

use Netresearch\RteCKEditorImage\Dto\SrcOrigin;
use Netresearch\RteCKEditorImage\Service\SrcOriginClassifier;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(SrcOriginClassifier::class)]
#[CoversClass(SrcOrigin::class)]
final class SrcOriginClassifierTest extends TestCase
{
    private SrcOriginClassifier $subject;

    protected function setUp(): void
    {
        parent::setUp();
        $this->subject = new SrcOriginClassifier();
    }

    /**
     * @return iterable<string, array{0: ?string, 1: SrcOrigin}>
     */
    public static function classificationProvider(): iterable
    {
        yield 'null src' => [null, SrcOrigin::Unknown];
        yield 'empty string' => ['', SrcOrigin::Unknown];
        yield 'whitespace only' => ['   ', SrcOrigin::Unknown];
        yield 'https URL' => ['https://typo3.github.io/TYPO3.Icons/images/a.svg', SrcOrigin::ExternalUrl];
        yield 'http URL' => ['http://example.com/foo.jpg', SrcOrigin::ExternalUrl];
        yield 'protocol-relative URL' => ['//cdn.example.com/foo.jpg', SrcOrigin::ExternalUrl];
        yield 'data URI jpeg' => ['data:image/jpeg;base64,/9j/4AAQSkZ', SrcOrigin::DataUri];
        yield 'data URI svg' => ['data:image/svg+xml;utf8,<svg/>', SrcOrigin::DataUri];
        yield 'legacy ext path absolute' => ['/typo3conf/ext/foo/bar.png', SrcOrigin::LegacyExtensionPath];
        yield 'legacy ext path relative' => ['typo3conf/ext/foo/bar.png', SrcOrigin::LegacyExtensionPath];
        yield 'secure download leading' => ['/securedl/sdl-eyJ0eXAi', SrcOrigin::SecureDownload];
        yield 'secure download nested' => ['/fileadmin/securedl/sdl-X', SrcOrigin::SecureDownload];
        yield 'processed variant' => ['/fileadmin/_processed_/a/b/csm_image_1234.jpg', SrcOrigin::ProcessedVariant];
        yield 'local fal absolute' => ['/fileadmin/user_upload/image.jpg', SrcOrigin::LocalFal];
        yield 'local fal relative' => ['fileadmin/user_upload/image.jpg', SrcOrigin::LocalFal];
        yield 'unrecognised form' => ['something-weird-no-slash.png', SrcOrigin::Unknown];
    }

    #[Test]
    #[DataProvider('classificationProvider')]
    public function classifyMapsSrcToOrigin(?string $src, SrcOrigin $expected): void
    {
        self::assertSame($expected, $this->subject->classify($src));
    }

    #[Test]
    public function defaultSkipSetContainsTheOutOfScopeCategories(): void
    {
        self::assertSame(
            [
                SrcOrigin::ExternalUrl,
                SrcOrigin::DataUri,
                SrcOrigin::LegacyExtensionPath,
                SrcOrigin::SecureDownload,
            ],
            SrcOrigin::defaultSkipSet(),
        );
    }
}
