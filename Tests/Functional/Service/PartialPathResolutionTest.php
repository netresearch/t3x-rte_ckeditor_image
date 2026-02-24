<?php

/*
 * This file is part of the package netresearch/rte-ckeditor-image.
 *
 * For the full copyright and license information, please read the
 * LICENSE file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Netresearch\RteCKEditorImage\Tests\Functional\Service;

use Netresearch\RteCKEditorImage\Domain\Model\ImageRenderingDto;
use Netresearch\RteCKEditorImage\Service\ImageRenderingService;
use PHPUnit\Framework\Attributes\Test;
use Psr\Http\Message\ServerRequestInterface;
use TYPO3\CMS\Core\Core\Environment;
use TYPO3\CMS\Core\Http\ServerRequest;
use TYPO3\CMS\Core\Package\PackageManager;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\TestingFramework\Core\Functional\FunctionalTestCase;

/**
 * Functional tests for partial path resolution (TDD for issue #547).
 *
 * Tests that partials are shipped in TYPO3 standard location and that
 * integrator overrides work from both old and new locations.
 *
 * @see https://github.com/netresearch/t3x-rte_ckeditor_image/issues/547
 *
 * @author  Netresearch DTT GmbH
 * @license https://www.gnu.org/licenses/agpl-3.0.de.html
 */
final class PartialPathResolutionTest extends FunctionalTestCase
{
    /**
     * Test directory paths that need cleanup.
     *
     * @var list<string>
     */
    private const TEST_CLEANUP_PATHS = [
        '/tests/override-old',
        '/tests/override-new',
        '/tests/override-priority',
    ];

    protected array $testExtensionsToLoad = [
        'typo3conf/ext/rte_ckeditor_image',
    ];

    protected array $coreExtensionsToLoad = [
        'typo3/cms-rte-ckeditor',
    ];

    /** @phpstan-ignore property.uninitialized (initialized in setUp) */
    private ImageRenderingService $renderer;

    /** @phpstan-ignore property.uninitialized (initialized in setUp) */
    private ServerRequestInterface $request;

    protected function setUp(): void
    {
        parent::setUp();

        $this->importCSVDataSet(__DIR__ . '/../Controller/Fixtures/sys_file_storage.csv');
        $this->importCSVDataSet(__DIR__ . '/../Controller/Fixtures/sys_file.csv');

        $this->renderer = $this->get(ImageRenderingService::class);
        $this->request  = new ServerRequest();

        // Defensive cleanup: ensure clean state before each test
        $this->cleanupTestDirectories();
    }

    protected function tearDown(): void
    {
        // Guaranteed cleanup regardless of test outcome
        $this->cleanupTestDirectories();

        parent::tearDown();
    }

    /**
     * Verify partials are shipped in TYPO3 standard location.
     *
     * Issue #547 requirement:
     * - Partials must be in Resources/Private/Partials/Image/ (not Templates/Partials/)
     *
     * @see https://github.com/netresearch/t3x-rte_ckeditor_image/issues/547
     */
    #[Test]
    public function partialsAreShippedInTypo3StandardLocation(): void
    {
        /** @var PackageManager $packageManager */
        $packageManager = $this->get(PackageManager::class);

        $extensionPath = $packageManager->getPackage('rte_ckeditor_image')->getPackagePath();

        // Target location per issue #547: Resources/Private/Partials/Image/
        $newStandardPath = $extensionPath . 'Resources/Private/Partials/Image/';

        // All required partials
        $requiredPartials = [
            'Tag.html',
            'TagInFigure.html',
            'Link.html',
            'Figure.html',
        ];

        self::assertDirectoryExists(
            $newStandardPath,
            'Partials must be in TYPO3 standard location: Resources/Private/Partials/Image/',
        );

        foreach ($requiredPartials as $partial) {
            self::assertFileExists(
                $newStandardPath . $partial,
                sprintf(
                    'Required partial "%s" must exist in standard location',
                    $partial,
                ),
            );
        }
    }

    /**
     * Test that basic image rendering works with default configuration.
     *
     * This verifies that partials are found and rendering produces valid output.
     * Note: This does NOT verify partials are in any specific location,
     * only that rendering works with the current configuration.
     */
    #[Test]
    public function basicRenderingWorksWithDefaultConfiguration(): void
    {
        $imageDto = new ImageRenderingDto(
            src: '/fileadmin/test.jpg',
            width: 800,
            height: 600,
            alt: 'Test image',
            title: null,
            htmlAttributes: [],
            caption: null,
            link: null,
            isMagicImage: false,
        );

        // This should work if partials are found
        $html = $this->renderer->render($imageDto, $this->request);

        self::assertStringContainsString('<img', $html);
        self::assertStringContainsString('src="/fileadmin/test.jpg"', $html);
    }

    /**
     * Test that integrator overrides in OLD location still work (BC).
     *
     * Integrators with existing overrides in:
     * typo3conf/ext/site_package/Resources/Private/Templates/Partials/Image/
     *
     * should continue to work after migration.
     */
    #[Test]
    public function integratorOverridesInOldLocationStillWork(): void
    {
        // Create a temporary override directory in old location pattern
        $overridePath = Environment::getVarPath() . '/tests/override-old/Templates/Partials/Image';
        GeneralUtility::mkdir_deep($overridePath);

        // Create override partial
        $customPartial = <<<'HTML'
            <html xmlns:f="http://typo3.org/ns/TYPO3/CMS/Fluid/ViewHelpers" data-namespace-typo3-fluid="true">
            <!-- OVERRIDE-OLD-LOCATION -->
            <img
                src="{image.src}"
                width="{image.width}"
                height="{image.height}"
                alt="{image.alt}"
                class="custom-from-old-path"
            />
            </html>
            HTML;
        $this->writeTestFile($overridePath . '/Tag.html', $customPartial);

        $imageDto = new ImageRenderingDto(
            src: '/fileadmin/test.jpg',
            width: 800,
            height: 600,
            alt: 'Test image',
            title: null,
            htmlAttributes: [],
            caption: null,
            link: null,
            isMagicImage: false,
        );

        // Configure renderer to use override path (simulating integrator config)
        // Priority 100 overrides the default paths (typically 0-50)
        $config = [
            'partialRootPaths.' => [
                '100' => Environment::getVarPath() . '/tests/override-old/Templates/Partials/',
            ],
        ];

        $html = $this->renderer->render($imageDto, $this->request, $config);

        // Verify the override was used
        self::assertStringContainsString('OVERRIDE-OLD-LOCATION', $html);
        self::assertStringContainsString('custom-from-old-path', $html);
    }

    /**
     * Test that integrator overrides in NEW standard location work.
     *
     * After migration, integrators should override in:
     * typo3conf/ext/site_package/Resources/Private/Partials/Image/
     */
    #[Test]
    public function integratorOverridesInNewStandardLocationWork(): void
    {
        // Create a temporary override directory in new location pattern
        $overridePath = Environment::getVarPath() . '/tests/override-new/Partials/Image';
        GeneralUtility::mkdir_deep($overridePath);

        // Create override partial
        $customPartial = <<<'HTML'
            <html xmlns:f="http://typo3.org/ns/TYPO3/CMS/Fluid/ViewHelpers" data-namespace-typo3-fluid="true">
            <!-- OVERRIDE-NEW-LOCATION -->
            <img
                src="{image.src}"
                width="{image.width}"
                height="{image.height}"
                alt="{image.alt}"
                class="custom-from-new-path"
            />
            </html>
            HTML;
        $this->writeTestFile($overridePath . '/Tag.html', $customPartial);

        $imageDto = new ImageRenderingDto(
            src: '/fileadmin/test.jpg',
            width: 800,
            height: 600,
            alt: 'Test image',
            title: null,
            htmlAttributes: [],
            caption: null,
            link: null,
            isMagicImage: false,
        );

        // Configure renderer to use override path (simulating integrator config)
        // Priority 100 overrides the default paths (typically 0-50)
        $config = [
            'partialRootPaths.' => [
                '100' => Environment::getVarPath() . '/tests/override-new/Partials/',
            ],
        ];

        $html = $this->renderer->render($imageDto, $this->request, $config);

        // Verify the override was used
        self::assertStringContainsString('OVERRIDE-NEW-LOCATION', $html);
        self::assertStringContainsString('custom-from-new-path', $html);
    }

    /**
     * Test that higher priority override wins over lower priority.
     *
     * When both old and new location overrides exist, higher priority wins.
     */
    #[Test]
    public function higherPriorityOverrideWins(): void
    {
        // Create override in "old" location with lower priority
        $oldOverridePath = Environment::getVarPath() . '/tests/override-priority/old/Templates/Partials/Image';
        GeneralUtility::mkdir_deep($oldOverridePath);
        $lowPriorityPartial = <<<'HTML'
            <html xmlns:f="http://typo3.org/ns/TYPO3/CMS/Fluid/ViewHelpers" data-namespace-typo3-fluid="true">
            <!-- LOW-PRIORITY -->
            <img src="{image.src}" alt="{image.alt}" class="low-priority" />
            </html>
            HTML;
        $this->writeTestFile($oldOverridePath . '/Tag.html', $lowPriorityPartial);

        // Create override in "new" location with higher priority
        $newOverridePath = Environment::getVarPath() . '/tests/override-priority/new/Partials/Image';
        GeneralUtility::mkdir_deep($newOverridePath);
        $highPriorityPartial = <<<'HTML'
            <html xmlns:f="http://typo3.org/ns/TYPO3/CMS/Fluid/ViewHelpers" data-namespace-typo3-fluid="true">
            <!-- HIGH-PRIORITY -->
            <img src="{image.src}" alt="{image.alt}" class="high-priority" />
            </html>
            HTML;
        $this->writeTestFile($newOverridePath . '/Tag.html', $highPriorityPartial);

        $imageDto = new ImageRenderingDto(
            src: '/fileadmin/test.jpg',
            width: 800,
            height: 600,
            alt: 'Test image',
            title: null,
            htmlAttributes: [],
            caption: null,
            link: null,
            isMagicImage: false,
        );

        // Configure with both paths - TYPO3 Fluid uses higher numeric key as higher priority
        // Priority 100 wins over priority 50, so the "new" location's template should be used
        $config = [
            'partialRootPaths.' => [
                '50'  => Environment::getVarPath() . '/tests/override-priority/old/Templates/Partials/',
                '100' => Environment::getVarPath() . '/tests/override-priority/new/Partials/',
            ],
        ];

        $html = $this->renderer->render($imageDto, $this->request, $config);

        // Higher priority should win
        self::assertStringContainsString('HIGH-PRIORITY', $html);
        self::assertStringContainsString('high-priority', $html);
        self::assertStringNotContainsString('LOW-PRIORITY', $html);
    }

    /**
     * Write a test file with error checking.
     *
     * Uses LOCK_EX to prevent race conditions in parallel test execution.
     */
    private function writeTestFile(string $path, string $content): void
    {
        $bytesWritten = file_put_contents($path, $content, LOCK_EX);

        if ($bytesWritten === false) {
            self::fail('Failed to create test file at: ' . $path);
        }
    }

    /**
     * Clean up all test directories.
     */
    private function cleanupTestDirectories(): void
    {
        foreach (self::TEST_CLEANUP_PATHS as $relativePath) {
            $fullPath = Environment::getVarPath() . $relativePath;

            if (is_dir($fullPath)) {
                GeneralUtility::rmdir($fullPath, true);
            }
        }
    }
}
