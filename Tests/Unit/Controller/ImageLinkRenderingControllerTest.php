<?php

/*
 * This file is part of the package netresearch/rte-ckeditor-image.
 *
 * For the full copyright and license information, please read the
 * LICENSE file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Netresearch\RteCKEditorImage\Tests\Unit\Controller;

use Netresearch\RteCKEditorImage\Controller\ImageLinkRenderingController;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use ReflectionMethod;
use TYPO3\CMS\Core\Log\LogManager;
use TYPO3\CMS\Core\Resource\ResourceFactory;
use TYPO3\TestingFramework\Core\Unit\UnitTestCase;

/**
 * Unit tests for ImageLinkRenderingController.
 */
#[CoversClass(ImageLinkRenderingController::class)]
final class ImageLinkRenderingControllerTest extends UnitTestCase
{
    private ImageLinkRenderingController $subject;

    protected function setUp(): void
    {
        parent::setUp();

        // Create mocks for dependencies
        $resourceFactoryMock = $this->createMock(ResourceFactory::class);
        $logManagerMock      = $this->createMock(LogManager::class);

        $this->subject = new ImageLinkRenderingController($resourceFactoryMock, $logManagerMock);
    }

    /**
     * Helper method to access protected methods.
     *
     * @param array<int, mixed> $args
     */
    private function callProtectedMethod(string $methodName, array $args): mixed
    {
        $reflection = new ReflectionMethod($this->subject, $methodName);
        $reflection->setAccessible(true);

        return $reflection->invokeArgs($this->subject, $args);
    }

    #[Test]
    public function getImageAttributesReturnsEmptyArrayForEmptyString(): void
    {
        $result = $this->callProtectedMethod('getImageAttributes', ['']);

        self::assertSame([], $result);
    }

    #[Test]
    public function getImageAttributesParsesDoubleQuotedAttributes(): void
    {
        $imageHtml = '<img src="/path/to/image.jpg" alt="Test image" width="800" />';
        /** @var array<string, string> $result */
        $result = $this->callProtectedMethod('getImageAttributes', [$imageHtml]);

        self::assertIsArray($result);
        self::assertArrayHasKey('src', $result);
        self::assertArrayHasKey('alt', $result);
        self::assertArrayHasKey('width', $result);
        self::assertSame('/path/to/image.jpg', $result['src']);
        self::assertSame('Test image', $result['alt']);
        self::assertSame('800', $result['width']);
    }

    #[Test]
    public function getImageAttributesParseSingleQuotedAttributes(): void
    {
        $imageHtml = "<img src='/path/to/image.jpg' alt='Test image' width='800' />";
        $result    = $this->callProtectedMethod('getImageAttributes', [$imageHtml]);

        self::assertSame('/path/to/image.jpg', $result['src']);
        self::assertSame('Test image', $result['alt']);
        self::assertSame('800', $result['width']);
    }

    #[Test]
    public function getImageAttributesParsesDataAttributes(): void
    {
        $imageHtml = '<img src="/test.jpg" data-htmlarea-file-uid="123" data-htmlarea-zoom="1" />';
        $result    = $this->callProtectedMethod('getImageAttributes', [$imageHtml]);

        self::assertArrayHasKey('data-htmlarea-file-uid', $result);
        self::assertArrayHasKey('data-htmlarea-zoom', $result);
        self::assertSame('123', $result['data-htmlarea-file-uid']);
        self::assertSame('1', $result['data-htmlarea-zoom']);
    }

    #[Test]
    public function getImageAttributesHandlesMixedQuoteStyles(): void
    {
        $imageHtml = '<img src="/test.jpg" alt=\'Alt text\' title="Title text" />';
        $result    = $this->callProtectedMethod('getImageAttributes', [$imageHtml]);

        self::assertSame('/test.jpg', $result['src']);
        self::assertSame('Alt text', $result['alt']);
        self::assertSame('Title text', $result['title']);
    }

    #[Test]
    public function getImageAttributesHandlesEmptyAttributes(): void
    {
        $imageHtml = '<img src="/test.jpg" alt="" class="" />';
        $result    = $this->callProtectedMethod('getImageAttributes', [$imageHtml]);

        self::assertArrayHasKey('src', $result);
        self::assertArrayHasKey('alt', $result);
        self::assertArrayHasKey('class', $result);
        self::assertSame('', $result['alt']);
        self::assertSame('', $result['class']);
    }

    #[Test]
    public function getImageAttributesParsesComplexAttributes(): void
    {
        $imageHtml = '<img src="/test.jpg" data-htmlarea-file-uid="456" '
            . 'class="image-class" alt="Alt text" title="Title" width="1920" height="1080" '
            . 'data-custom="value" loading="lazy" />';

        /** @var array<string, string> $result */
        $result = $this->callProtectedMethod('getImageAttributes', [$imageHtml]);

        self::assertIsArray($result);
        self::assertCount(9, $result);
        self::assertSame('/test.jpg', $result['src']);
        self::assertSame('456', $result['data-htmlarea-file-uid']);
        self::assertSame('image-class', $result['class']);
        self::assertSame('Alt text', $result['alt']);
        self::assertSame('Title', $result['title']);
        self::assertSame('1920', $result['width']);
        self::assertSame('1080', $result['height']);
        self::assertSame('value', $result['data-custom']);
        self::assertSame('lazy', $result['loading']);
    }

    #[Test]
    public function getImageAttributesHandlesAttributesWithSpecialCharacters(): void
    {
        $imageHtml = '<img src="/test.jpg" alt="Image with &quot;quotes&quot;" '
            . 'title="Title with \'apostrophes\'" />';

        /** @var array<string, string> $result */
        $result = $this->callProtectedMethod('getImageAttributes', [$imageHtml]);

        self::assertIsArray($result);
        self::assertStringContainsString('quotes', $result['alt']);
        self::assertStringContainsString('apostrophes', $result['title']);
    }

    #[Test]
    public function getImageAttributesParsesNumericAttributeValues(): void
    {
        $imageHtml = '<img src="/test.jpg" width="1920" height="1080" data-id="12345" />';
        $result    = $this->callProtectedMethod('getImageAttributes', [$imageHtml]);

        self::assertSame('1920', $result['width']);
        self::assertSame('1080', $result['height']);
        self::assertSame('12345', $result['data-id']);
    }

    #[Test]
    public function getImageAttributesHandlesHyphenatedAttributeNames(): void
    {
        $imageHtml = '<img src="/test.jpg" data-file-uid="123" data-custom-attribute="value" />';
        $result    = $this->callProtectedMethod('getImageAttributes', [$imageHtml]);

        self::assertArrayHasKey('data-file-uid', $result);
        self::assertArrayHasKey('data-custom-attribute', $result);
        self::assertSame('123', $result['data-file-uid']);
        self::assertSame('value', $result['data-custom-attribute']);
    }

    #[Test]
    public function getImageAttributesIgnoresInvalidAttributes(): void
    {
        // Attributes without quotes should not be parsed by this implementation
        $imageHtml = '<img src="/test.jpg" alt="Valid" invalid=notquoted />';
        $result    = $this->callProtectedMethod('getImageAttributes', [$imageHtml]);

        self::assertArrayHasKey('src', $result);
        self::assertArrayHasKey('alt', $result);
        self::assertArrayNotHasKey('invalid', $result);
    }

    #[Test]
    public function getImageAttributesHandlesLongAttributeValues(): void
    {
        $longValue = str_repeat('a', 500);
        $imageHtml = '<img src="/test.jpg" data-long="' . $longValue . '" />';

        $result = $this->callProtectedMethod('getImageAttributes', [$imageHtml]);

        self::assertArrayHasKey('data-long', $result);
        self::assertSame($longValue, $result['data-long']);
    }

    #[Test]
    public function getImageAttributesHandlesAttributesWithSpaces(): void
    {
        $imageHtml = '<img src="/test.jpg" alt="Text with spaces" class="class one two" />';
        $result    = $this->callProtectedMethod('getImageAttributes', [$imageHtml]);

        self::assertSame('Text with spaces', $result['alt']);
        self::assertSame('class one two', $result['class']);
    }

    #[Test]
    public function getImageAttributesSecurityPreventsReDoSAttack(): void
    {
        // Create a pattern that could cause ReDoS with naive regex
        // The method uses atomic groups to prevent catastrophic backtracking
        $maliciousPattern = str_repeat('a="', 1000) . str_repeat('b', 1000);
        $imageHtml        = '<img src="/test.jpg" ' . $maliciousPattern . '" />';

        $startTime = microtime(true);
        $result    = $this->callProtectedMethod('getImageAttributes', [$imageHtml]);
        $duration  = microtime(true) - $startTime;

        // Should complete very quickly (< 1 second) even with malicious input
        self::assertLessThan(1.0, $duration, 'ReDoS protection failed - parsing took too long');
        self::assertIsArray($result);
    }
}
