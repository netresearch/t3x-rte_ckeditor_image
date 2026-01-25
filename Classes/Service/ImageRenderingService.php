<?php

/*
 * This file is part of the package netresearch/rte-ckeditor-image.
 *
 * For the full copyright and license information, please read the
 * LICENSE file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Netresearch\RteCKEditorImage\Service;

use Netresearch\RteCKEditorImage\Domain\Model\ImageRenderingDto;
use Netresearch\RteCKEditorImage\Domain\Model\LinkDto;
use Psr\Http\Message\ServerRequestInterface;
use TYPO3\CMS\Core\View\ViewFactoryData;
use TYPO3\CMS\Core\View\ViewFactoryInterface;

/**
 * Unified image rendering service using ViewFactoryInterface.
 *
 * RESPONSIBILITY: Presentation layer only - render validated DTOs via Fluid templates.
 * NO business logic, NO validation - this service trusts the DTO.
 *
 * SECURITY: All validation MUST occur in ImageResolverService before reaching this layer.
 *
 * @author  Netresearch DTT GmbH
 * @license https://www.gnu.org/licenses/agpl-3.0.de.html
 *
 * @see    https://www.netresearch.de
 */
class ImageRenderingService
{
    private const TEMPLATE_ROOT_PATH = 'EXT:rte_ckeditor_image/Resources/Private/Templates/';

    /**
     * Template path constants for different rendering contexts.
     */
    private const TEMPLATE_STANDALONE = 'Image/Standalone';

    private const TEMPLATE_WITH_CAPTION = 'Image/WithCaption';

    private const TEMPLATE_LINK = 'Image/Link';

    private const TEMPLATE_LINK_WITH_CAPTION = 'Image/LinkWithCaption';

    private const TEMPLATE_POPUP = 'Image/Popup';

    private const TEMPLATE_POPUP_WITH_CAPTION = 'Image/PopupWithCaption';

    public function __construct(
        private readonly ViewFactoryInterface $viewFactory,
    ) {}

    /**
     * Default partial root path.
     */
    private const PARTIAL_ROOT_PATH = 'EXT:rte_ckeditor_image/Resources/Private/Templates/Partials/';

    /**
     * Default layout root path.
     */
    private const LAYOUT_ROOT_PATH = 'EXT:rte_ckeditor_image/Resources/Private/Templates/Layouts/';

    /**
     * Render image HTML from validated DTO.
     *
     * @param ImageRenderingDto      $imageData Validated image data
     * @param ServerRequestInterface $request   Current request
     * @param array<string, mixed>   $config    TypoScript configuration for template paths
     *
     * @return string Rendered HTML
     */
    public function render(
        ImageRenderingDto $imageData,
        ServerRequestInterface $request,
        array $config = [],
    ): string {
        // 1. Select template based on rendering context
        $templatePath = $this->selectTemplate($imageData);

        // 2. Build template paths from configuration (with defaults)
        $paths = $this->buildTemplatePaths($config);

        // 3. Create view via ViewFactoryInterface (TYPO3 v13 standard)
        $viewFactoryData = new ViewFactoryData(
            templateRootPaths: $paths['templateRootPaths'],
            partialRootPaths: $paths['partialRootPaths'],
            layoutRootPaths: $paths['layoutRootPaths'],
            request: $request,
        );

        $view = $this->viewFactory->create($viewFactoryData);

        // 3. Assign variables (DTO data already validated/sanitized)
        $view->assign('image', $imageData);

        // 4. Render template and normalize whitespace
        $output = $view->render($templatePath);

        // Normalize whitespace to prevent parseFunc_RTE from creating <p>&nbsp;</p> artifacts.
        // This allows templates to use readable multi-line formatting.
        //
        // Step 1: Collapse whitespace within HTML tags (handles multi-line attributes)
        // Example: <a href="..."\n   target="..."> becomes <a href="..." target="...">
        // Regex handles > inside quoted attributes (e.g., data-config="a > b")
        $output = preg_replace_callback(
            '/<[a-zA-Z](?:"[^"]*"|\'[^\']*\'|[^>])*>/s',
            static fn (array $match): string => (string) preg_replace('/\s+/', ' ', $match[0]),
            $output,
        ) ?? $output;

        // Step 2: Remove whitespace between HTML tags
        // Example: </a>\n    <figcaption> becomes </a><figcaption>
        $output = preg_replace('/>\s+</', '><', $output) ?? $output;

        return trim($output);
    }

    /**
     * Template selection logic based on rendering context.
     *
     * @param ImageRenderingDto $imageData Image data
     *
     * @return string Template path relative to Templates/
     */
    private function selectTemplate(ImageRenderingDto $imageData): string
    {
        $hasCaption = $imageData->caption !== null && $imageData->caption !== '';
        $hasLink    = $imageData->link instanceof LinkDto;
        $isPopup    = $imageData->link instanceof LinkDto && $imageData->link->isPopup;

        return match (true) {
            $isPopup && $hasCaption => self::TEMPLATE_POPUP_WITH_CAPTION,
            $isPopup                => self::TEMPLATE_POPUP,
            $hasLink && $hasCaption => self::TEMPLATE_LINK_WITH_CAPTION,
            $hasLink                => self::TEMPLATE_LINK,
            $hasCaption             => self::TEMPLATE_WITH_CAPTION,
            default                 => self::TEMPLATE_STANDALONE,
        };
    }

    /**
     * Build template paths from configuration, merging with defaults.
     *
     * Supports both direct configuration and settings. wrapper:
     * - Direct: templateRootPaths., partialRootPaths., layoutRootPaths.
     * - Wrapped: settings.templateRootPaths., etc.
     *
     * Paths are sorted by numeric key (TYPO3 convention: higher = higher priority).
     * Default extension paths are added with key 0 to ensure they're lowest priority.
     *
     * @param array<string, mixed> $config TypoScript configuration
     *
     * @return array{templateRootPaths: list<string>, partialRootPaths: list<string>, layoutRootPaths: list<string>}
     */
    private function buildTemplatePaths(array $config): array
    {
        // Check for settings. wrapper (as documented in Template-Overrides.rst)
        $settings = isset($config['settings.']) && is_array($config['settings.'])
            ? $config['settings.']
            : $config;

        // Extract path configurations (ensure arrays)
        $templateConfig = isset($settings['templateRootPaths.']) && is_array($settings['templateRootPaths.'])
            ? $settings['templateRootPaths.']
            : [];
        $partialConfig = isset($settings['partialRootPaths.']) && is_array($settings['partialRootPaths.'])
            ? $settings['partialRootPaths.']
            : [];
        $layoutConfig = isset($settings['layoutRootPaths.']) && is_array($settings['layoutRootPaths.'])
            ? $settings['layoutRootPaths.']
            : [];

        // Build paths with defaults at key 0 (lowest priority)
        // Filter to ensure non-empty string values only
        $filteredTemplateConfig = $this->filterNonEmptyStringPaths($templateConfig);
        $filteredPartialConfig  = $this->filterNonEmptyStringPaths($partialConfig);
        $filteredLayoutConfig   = $this->filterNonEmptyStringPaths($layoutConfig);

        $templatePaths = $this->mergePathsWithDefault(
            $filteredTemplateConfig,
            self::TEMPLATE_ROOT_PATH,
        );

        $partialPaths = $this->mergePathsWithDefault(
            $filteredPartialConfig,
            self::PARTIAL_ROOT_PATH,
        );

        $layoutPaths = $this->mergePathsWithDefault(
            $filteredLayoutConfig,
            self::LAYOUT_ROOT_PATH,
        );

        return [
            'templateRootPaths' => $templatePaths,
            'partialRootPaths'  => $partialPaths,
            'layoutRootPaths'   => $layoutPaths,
        ];
    }

    /**
     * Filter array to keep only non-empty string values, preserving keys.
     *
     * @param array<mixed> $config Configuration array to filter
     *
     * @return array<int|string, string> Filtered array with non-empty string values
     */
    private function filterNonEmptyStringPaths(array $config): array
    {
        $filtered = [];

        foreach ($config as $key => $value) {
            if (is_string($value) && $value !== '') {
                $filtered[$key] = $value;
            }
        }

        return $filtered;
    }

    /**
     * Merge custom paths with default, sorted by numeric key.
     *
     * Custom paths can only use numeric keys > 0 to ensure the default path
     * at priority 0 is always preserved. Non-numeric keys are ignored to
     * prevent accidental overrides (e.g. casting "foo" to 0).
     *
     * @param array<int|string, string> $customPaths Custom paths from TypoScript (pre-filtered)
     * @param string                    $defaultPath Default path for this extension
     *
     * @return list<string> Sorted paths (lower key = lower priority)
     */
    private function mergePathsWithDefault(array $customPaths, string $defaultPath): array
    {
        // Start with default at key 0 (always preserved)
        $paths = [0 => $defaultPath];

        // Merge custom paths, but never allow overriding key 0
        foreach ($customPaths as $key => $path) {
            // Accept only numeric keys > 0 to preserve default at priority 0
            if (is_int($key)) {
                $intKey = $key;
            } elseif ($key !== '' && ctype_digit($key)) {
                // String key that looks like a number (e.g. '10' from TypoScript)
                $intKey = (int) $key;
            } else {
                // Ignore non-numeric keys to avoid accidental overrides (e.g. casting to 0)
                continue;
            }

            if ($intKey > 0) {
                $paths[$intKey] = $path;
            }
        }

        // Sort by key (TYPO3 convention: lower number = lower priority)
        ksort($paths);

        // Return as list (values only, in sorted order)
        return array_values($paths);
    }
}
