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
     * Render image HTML from validated DTO.
     *
     * @param ImageRenderingDto      $imageData Validated image data
     * @param ServerRequestInterface $request   Current request
     *
     * @return string Rendered HTML
     */
    public function render(
        ImageRenderingDto $imageData,
        ServerRequestInterface $request,
    ): string {
        // 1. Select template based on rendering context
        $templatePath = $this->selectTemplate($imageData);

        // 2. Create view via ViewFactoryInterface (TYPO3 v13 standard)
        $viewFactoryData = new ViewFactoryData(
            templateRootPaths: [self::TEMPLATE_ROOT_PATH],
            partialRootPaths: [self::TEMPLATE_ROOT_PATH . 'Partials/'],
            layoutRootPaths: [self::TEMPLATE_ROOT_PATH . 'Layouts/'],
            request: $request,
        );

        $view = $this->viewFactory->create($viewFactoryData);

        // 3. Assign variables (DTO data already validated/sanitized)
        $view->assign('image', $imageData);

        // 4. Render template (TYPO3 v13 ViewInterface accepts template name in render())
        return $view->render($templatePath);
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
}
