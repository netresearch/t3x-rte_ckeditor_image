<?php

/*
 * This file is part of the package netresearch/rte-ckeditor-image.
 *
 * For the full copyright and license information, please read the
 * LICENSE file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Netresearch\RteCKEditorImage\Tests\Unit\Database;

use Netresearch\RteCKEditorImage\Database\RteImagesDbHook;
use PHPUnit\Framework\MockObject\MockObject;
use Psr\Http\Message\ServerRequestInterface;
use ReflectionClass;
use RuntimeException;
use stdClass;
use TYPO3\CMS\Core\Configuration\ExtensionConfiguration;
use TYPO3\CMS\Core\Log\Logger;
use TYPO3\CMS\Core\Log\LogManager;
use TYPO3\TestingFramework\Core\Unit\UnitTestCase;

/**
 * Unit tests for {@see RteImagesDbHook} CLI / missing-request behaviour (#815).
 */
final class RteImagesDbHookTest extends UnitTestCase
{
    protected bool $resetSingletonInstances = true;

    private ?RteImagesDbHook $subject = null;

    protected function setUp(): void
    {
        parent::setUp();

        /** @var ExtensionConfiguration&MockObject $extensionConfiguration */
        $extensionConfiguration = $this->createMock(ExtensionConfiguration::class);
        $extensionConfiguration->method('get')->willReturnMap([
            ['rte_ckeditor_image', 'fetchExternalImages', false],
            ['rte_ckeditor_image', 'allowSvgImages', false],
        ]);

        $logger = $this->createMock(Logger::class);

        /** @var LogManager&MockObject $logManager */
        $logManager = $this->createMock(LogManager::class);
        $logManager->method('getLogger')->willReturn($logger);

        $this->subject = new RteImagesDbHook($extensionConfiguration, $logManager);
    }

    private function getSubject(): RteImagesDbHook
    {
        $subject = $this->subject;
        if ($subject === null) {
            self::fail('Test subject was not initialized in setUp()');
        }

        return $subject;
    }

    protected function tearDown(): void
    {
        unset($GLOBALS['TYPO3_REQUEST']);
        parent::tearDown();
    }

    private function invokeModifyRteField(string $html): string
    {
        $reflection = new ReflectionClass(RteImagesDbHook::class);
        $method     = $reflection->getMethod('modifyRteField');
        $method->setAccessible(true);

        $result = $method->invoke($this->getSubject(), $html);
        if (!is_string($result)) {
            throw new RuntimeException('modifyRteField must return string');
        }

        return $result;
    }

    public function testModifyRteFieldLeavesHtmlUnchangedWhenTypo3RequestIsUnset(): void
    {
        unset($GLOBALS['TYPO3_REQUEST']);

        $html = '<p>x</p><img src="https://example.org/image.jpg" width="100" height="50" />';

        $result = $this->invokeModifyRteField($html);

        self::assertSame($html, $result);
    }

    public function testModifyRteFieldLeavesHtmlUnchangedWhenTypo3RequestIsNotPsrServerRequest(): void
    {
        $GLOBALS['TYPO3_REQUEST'] = new stdClass();

        $html = '<img src="https://example.org/image.jpg" data-htmlarea-file-uid="1" />';

        $result = $this->invokeModifyRteField($html);

        self::assertSame($html, $result);
    }

    public function testModifyRteFieldLeavesHtmlUnchangedWhenApplicationTypeAttributeIsMissing(): void
    {
        $request = $this->createMock(ServerRequestInterface::class);
        $request->method('getAttribute')
            ->willReturnCallback(static function (string $name, mixed $default = null): mixed {
                if ($name === 'applicationType') {
                    return null;
                }

                return $default;
            });

        $GLOBALS['TYPO3_REQUEST'] = $request;

        $html = '<img src="https://example.org/image.jpg" alt="" />';

        $result = $this->invokeModifyRteField($html);

        self::assertSame($html, $result);
    }
}
