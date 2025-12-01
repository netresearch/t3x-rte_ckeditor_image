<?php

/*
 * This file is part of the package netresearch/rte-ckeditor-image.
 *
 * For the full copyright and license information, please read the
 * LICENSE file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Netresearch\RteCKEditorImage\Tests\Unit\EventListener;

use Netresearch\RteCKEditorImage\EventListener\AddTypoScriptAfterTemplatesListener;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Psr\Http\Message\ServerRequestInterface;
use TYPO3\CMS\Core\TypoScript\IncludeTree\Event\AfterTemplatesHaveBeenDeterminedEvent;
use TYPO3\TestingFramework\Core\Unit\UnitTestCase;

/**
 * Unit tests for AddTypoScriptAfterTemplatesListener event listener.
 *
 * This listener provides zero-configuration TypoScript injection for
 * lib.parseFunc_RTE.tags.img, ensuring it works with or without
 * Bootstrap Package, site sets, or fluid_styled_content.
 */
#[CoversClass(AddTypoScriptAfterTemplatesListener::class)]
final class AddTypoScriptAfterTemplatesListenerTest extends UnitTestCase
{
    private AddTypoScriptAfterTemplatesListener $listener;

    protected function setUp(): void
    {
        parent::setUp();
        $this->listener = new AddTypoScriptAfterTemplatesListener();
    }

    /**
     * Creates an AfterTemplatesHaveBeenDeterminedEvent with the given template rows.
     *
     * @param array<int, array<string, mixed>> $templateRows
     */
    private function createEvent(array $templateRows): AfterTemplatesHaveBeenDeterminedEvent
    {
        $request = $this->createMock(ServerRequestInterface::class);

        return new AfterTemplatesHaveBeenDeterminedEvent(
            [],      // rootline
            $request,
            $templateRows,
        );
    }

    #[Test]
    public function listenerInjectsTypoScriptWhenTemplatesExist(): void
    {
        $templateRows = [
            [
                'uid'                 => 1,
                'pid'                 => 0,
                'title'               => 'Main Template',
                'include_static_file' => '',
            ],
        ];

        $event = $this->createEvent($templateRows);
        ($this->listener)($event);

        $result = $event->getTemplateRows();

        self::assertCount(2, $result);
        self::assertSame('rte_ckeditor_image_auto', $result[1]['uid']);
        self::assertSame('RTE CKEditor Image (auto-injected)', $result[1]['title']);
        self::assertIsString($result[1]['include_static_file']);
        self::assertStringContainsString(
            'EXT:rte_ckeditor_image/Configuration/TypoScript/ImageRendering/',
            $result[1]['include_static_file'],
        );
    }

    #[Test]
    public function listenerDoesNotInjectWhenNoTemplatesExist(): void
    {
        $event = $this->createEvent([]);
        ($this->listener)($event);

        $result = $event->getTemplateRows();

        self::assertCount(0, $result);
    }

    #[Test]
    public function listenerDoesNotDuplicateWhenAlreadyIncluded(): void
    {
        $templateRows = [
            [
                'uid'                 => 1,
                'pid'                 => 0,
                'title'               => 'Main Template',
                'include_static_file' => 'EXT:rte_ckeditor_image/Configuration/TypoScript/',
            ],
        ];

        $event = $this->createEvent($templateRows);
        ($this->listener)($event);

        $result = $event->getTemplateRows();

        self::assertCount(1, $result);
        self::assertSame(1, $result[0]['uid']);
    }

    #[Test]
    public function listenerDetectsInclusionInAnyTemplateRow(): void
    {
        $templateRows = [
            [
                'uid'                 => 1,
                'pid'                 => 0,
                'title'               => 'Bootstrap Package',
                'include_static_file' => 'EXT:bootstrap_package/Configuration/TypoScript/',
            ],
            [
                'uid'                 => 2,
                'pid'                 => 0,
                'title'               => 'Custom Template',
                'include_static_file' => 'EXT:fluid_styled_content/,EXT:rte_ckeditor_image/Configuration/TypoScript/ImageRendering/',
            ],
        ];

        $event = $this->createEvent($templateRows);
        ($this->listener)($event);

        $result = $event->getTemplateRows();

        // Should not add because rte_ckeditor_image is already in row 2
        self::assertCount(2, $result);
    }

    #[Test]
    public function listenerAppendsAfterAllExistingTemplates(): void
    {
        $templateRows = [
            [
                'uid'                 => 1,
                'pid'                 => 0,
                'title'               => 'Bootstrap Package Full',
                'include_static_file' => 'EXT:bootstrap_package/Configuration/TypoScript/',
            ],
            [
                'uid'                 => 2,
                'pid'                 => 0,
                'title'               => 'Site Template',
                'include_static_file' => 'EXT:my_site/Configuration/TypoScript/',
            ],
        ];

        $event = $this->createEvent($templateRows);
        ($this->listener)($event);

        $result = $event->getTemplateRows();

        self::assertCount(3, $result);
        // Our template should be LAST (index 2)
        self::assertSame('rte_ckeditor_image_auto', $result[2]['uid']);
        // Original templates should be unchanged
        self::assertSame(1, $result[0]['uid']);
        self::assertSame(2, $result[1]['uid']);
    }

    #[Test]
    public function listenerWorksWithBootstrapPackageSiteSet(): void
    {
        // Simulates Bootstrap Package loaded via site set
        // Bootstrap Package does: lib.parseFunc_RTE < lib.parseFunc
        // Our listener should inject AFTER this to override
        $templateRows = [
            [
                'uid'                 => 'bootstrap-package/full',
                'pid'                 => 0,
                'title'               => 'Bootstrap Package (Site Set)',
                'include_static_file' => 'EXT:bootstrap_package/Configuration/TypoScript/',
            ],
        ];

        $event = $this->createEvent($templateRows);
        ($this->listener)($event);

        $result = $event->getTemplateRows();

        self::assertCount(2, $result);
        // Our template comes AFTER Bootstrap Package
        self::assertSame('rte_ckeditor_image_auto', $result[1]['uid']);
    }

    #[Test]
    public function listenerWorksWithFluidStyledContentOnly(): void
    {
        $templateRows = [
            [
                'uid'                 => 'typo3/fluid-styled-content',
                'pid'                 => 0,
                'title'               => 'Fluid Styled Content',
                'include_static_file' => 'EXT:fluid_styled_content/Configuration/TypoScript/',
            ],
        ];

        $event = $this->createEvent($templateRows);
        ($this->listener)($event);

        $result = $event->getTemplateRows();

        self::assertCount(2, $result);
        self::assertSame('rte_ckeditor_image_auto', $result[1]['uid']);
    }

    #[Test]
    public function listenerWorksWithMultipleSiteSets(): void
    {
        $templateRows = [
            [
                'uid'                 => 'typo3/fluid-styled-content',
                'pid'                 => 0,
                'title'               => 'Fluid Styled Content',
                'include_static_file' => 'EXT:fluid_styled_content/Configuration/TypoScript/',
            ],
            [
                'uid'                 => 'bootstrap-package/full',
                'pid'                 => 0,
                'title'               => 'Bootstrap Package',
                'include_static_file' => 'EXT:bootstrap_package/Configuration/TypoScript/',
            ],
        ];

        $event = $this->createEvent($templateRows);
        ($this->listener)($event);

        $result = $event->getTemplateRows();

        self::assertCount(3, $result);
        // Our template is LAST, after all site sets
        self::assertSame('rte_ckeditor_image_auto', $result[2]['uid']);
    }

    #[Test]
    public function injectedTemplateHasCorrectStructure(): void
    {
        $templateRows = [
            [
                'uid'                 => 1,
                'pid'                 => 0,
                'title'               => 'Root',
                'include_static_file' => '',
            ],
        ];

        $event = $this->createEvent($templateRows);
        ($this->listener)($event);

        $result   = $event->getTemplateRows();
        $injected = $result[1];

        self::assertSame('rte_ckeditor_image_auto', $injected['uid']);
        self::assertSame(0, $injected['pid']);
        self::assertSame('RTE CKEditor Image (auto-injected)', $injected['title']);
        self::assertSame(0, $injected['root']);
        self::assertSame(0, $injected['clear']);
        self::assertSame(
            'EXT:rte_ckeditor_image/Configuration/TypoScript/ImageRendering/',
            $injected['include_static_file'],
        );
        self::assertSame('', $injected['constants']);
        self::assertSame('', $injected['config']);
        self::assertSame('', $injected['basedOn']);
        self::assertSame(0, $injected['includeStaticAfterBasedOn']);
        self::assertSame(0, $injected['static_file_mode']);
    }

    #[Test]
    public function listenerHandlesMissingIncludeStaticFileKey(): void
    {
        $templateRows = [
            [
                'uid'   => 1,
                'pid'   => 0,
                'title' => 'Template without include_static_file',
                // Note: include_static_file key is missing
            ],
        ];

        $event = $this->createEvent($templateRows);
        ($this->listener)($event);

        $result = $event->getTemplateRows();

        // Should still inject (missing key treated as empty string)
        self::assertCount(2, $result);
        self::assertSame('rte_ckeditor_image_auto', $result[1]['uid']);
    }

    #[Test]
    public function listenerHandlesNullIncludeStaticFile(): void
    {
        $templateRows = [
            [
                'uid'                 => 1,
                'pid'                 => 0,
                'title'               => 'Template with null',
                'include_static_file' => null,
            ],
        ];

        $event = $this->createEvent($templateRows);
        ($this->listener)($event);

        $result = $event->getTemplateRows();

        // Should still inject (null treated as empty)
        self::assertCount(2, $result);
    }

    #[Test]
    public function listenerDetectsPartialExtensionKeyMatch(): void
    {
        // Ensure we detect 'rte_ckeditor_image' even in comma-separated lists
        $templateRows = [
            [
                'uid'                 => 1,
                'pid'                 => 0,
                'title'               => 'Combined Template',
                'include_static_file' => 'EXT:fluid_styled_content/,EXT:rte_ckeditor_image/Configuration/TypoScript/,EXT:my_site/',
            ],
        ];

        $event = $this->createEvent($templateRows);
        ($this->listener)($event);

        $result = $event->getTemplateRows();

        // Should NOT inject - already present
        self::assertCount(1, $result);
    }
}
