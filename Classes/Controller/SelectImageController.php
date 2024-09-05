<?php

/**
 * This file is part of the package netresearch/rte-ckeditor-image.
 *
 * For the full copyright and license information, please read the
 * LICENSE file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Netresearch\RteCKEditorImage\Controller;

use Exception;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use TYPO3\CMS\Backend\Controller\ElementBrowserController;
use TYPO3\CMS\Backend\Utility\BackendUtility;
use TYPO3\CMS\Core\Configuration\Richtext;
use TYPO3\CMS\Core\Http\JsonResponse;
use TYPO3\CMS\Core\Resource\File;
use TYPO3\CMS\Core\Resource\ProcessedFile;
use TYPO3\CMS\Core\Resource\ResourceFactory;
use TYPO3\CMS\Core\Resource\Service\MagicImageService;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Core\Utility\HttpUtility;
use TYPO3\CMS\Extbase\Utility\LocalizationUtility;

/**
 * Controller for the image select wizard.
 *
 * @author  Christian Opitz <christian.opitz@netresearch.de>
 * @license https://www.gnu.org/licenses/agpl-3.0.de.html
 * @link    https://www.netresearch.de
 */
class SelectImageController extends ElementBrowserController
{
    /**
     * Minimum allowed image dimension in pixels.
     */
    private const IMAGE_MIN_DIMENSION = 1;

    /**
     * Maximum allowed image dimension in pixels.
     *
     * Prevents resource exhaustion: 10000x10000px ≈ 400MB memory worst case.
     * Values above this limit will be clamped to prevent server crashes.
     */
    private const IMAGE_MAX_DIMENSION = 10000;

    /**
     * Default maximum width for images from TSConfig.
     */
    private const IMAGE_DEFAULT_MAX_WIDTH = 1920;

    /**
     * Default maximum height for images from TSConfig.
     */
    private const IMAGE_DEFAULT_MAX_HEIGHT = 9999;

    /**
     * @var ResourceFactory
     */
    private ResourceFactory $resourceFactory;

    /**
     * @var MagicImageService
     */
    private MagicImageService $magicImageService;

    /**
     * Forward to infoAction if wanted.
     *
     * @param ServerRequestInterface $request
     *
     * @return ResponseInterface
     */
    public function mainAction(ServerRequestInterface $request): ResponseInterface
    {
        $this->resourceFactory = GeneralUtility::makeInstance(ResourceFactory::class);
        $this->magicImageService = GeneralUtility::makeInstance(MagicImageService::class);

        $parsedBody = $request->getParsedBody();
        $queryParams = $request->getQueryParams();
        $isInfoAction = (
            (is_array($parsedBody) ? ($parsedBody['action'] ?? null) : null)
            ?? $queryParams['action']
            ?? null
        ) === 'info';

        if (!$isInfoAction) {
            $bparams = explode('|', (string) $queryParams['bparams']);

            if (isset($bparams[3]) && ($bparams[3] === '')) {
                $bparams[3] = $GLOBALS['TYPO3_CONF_VARS']['GFX']['imagefile_ext'];
                $queryParams['bparams'] = implode('|', $bparams);
            }
        }

        $request = $request->withQueryParams($queryParams);

        return $isInfoAction ? $this->infoAction($request) : parent::mainAction($request);
    }

    /**
     * Retrieve image info.
     *
     * @param ServerRequestInterface $request
     *
     * @return ResponseInterface
     */
    public function infoAction(ServerRequestInterface $request): ResponseInterface
    {
        $id              = $request->getQueryParams()['fileId'];
        $table           = $request->getQueryParams()['table'];
        $params          = $request->getQueryParams()['P'] ?? [];
        $params['table'] = $table;

        if (!$id || !is_numeric($id)) {
            header(HttpUtility::HTTP_STATUS_412);
            exit;
        }

        $file          = $this->getImage((int) $id);
        $maxDimensions = $this->getMaxDimensions($params);
        $processedFile = $this->processImage($file, $params, $maxDimensions);

        return new JsonResponse([
            'uid'       => $file->getUid(),
            'alt'       => $file->getProperty('alternative') ?? '',
            'title'     => $file->getProperty('title') ?? '',
            'width'     => min($file->getProperty('width'), $maxDimensions['width']),
            'height'    => min($file->getProperty('height'), $maxDimensions['height']),
            'url'       => $file->getPublicUrl(),
            'processed' => [
                'width'  => $processedFile->getProperty('width'),
                'height' => $processedFile->getProperty('height'),
                'url'    => $processedFile->getPublicUrl(),
            ],
            'lang'      => [
                'override'          => LocalizationUtility::translate(
                    'LLL:EXT:core/Resources/Private/Language/'
                    . 'locallang_core.xlf:labels.placeholder.override'
                ),
                'overrideNoDefault' => LocalizationUtility::translate(
                    'LLL:EXT:core/Resources/Private/Language/'
                    . 'locallang_core.xlf:labels.placeholder.override_not_available'
                ),
                'cssClass'          => LocalizationUtility::translate(
                    'LLL:EXT:rte_ckeditor_image/Resources/Private/Language/'
                    . 'locallang_be.xlf:labels.ckeditor.cssclass'
                ),
                'zoom'              => LocalizationUtility::translate(
                    'LLL:EXT:frontend/Resources/Private/Language/'
                    . 'locallang_ttc.xlf:image_zoom_formlabel'
                ),
            ],
        ]);
    }

    /**
     * Get the original image.
     *
     * @param int $id The uid of the file to instantiate
     *
     * @return File
     */
    protected function getImage(int $id): File
    {
        try {
            $file = $this->resourceFactory->getFileObject($id);

            if ($file->isDeleted() || $file->isMissing()) {
                $file = null;
            }
        } catch (Exception) {
            $file = null;
        }

        if (!$file instanceof File) {
            header(HttpUtility::HTTP_STATUS_404);
            exit;
        }

        return $file;
    }

    /**
     * Get the processed image.
     *
     * @param File                   $file          The original image file
     * @param string[]               $params        The parameters used to process the image
     * @param array<string, int> $maxDimensions The maximum width and height
     *
     * @return ProcessedFile
     */
    protected function processImage(File $file, array $params, array $maxDimensions): ProcessedFile
    {
        $this->magicImageService->setMagicImageMaximumDimensions([
            'buttons.' => [
                'image.' => [
                    'options.' => [
                        'magic.' => [
                            'maxWidth'  => $maxDimensions['width'],
                            'maxHeight' => $maxDimensions['height'],
                        ],
                    ],
                ],
            ],
        ]);

        return $this->magicImageService
            ->createMagicImage(
                $file,
                [
                    'width'  => (int) ($params['width'] ?? $file->getProperty('width')),
                    'height' => (int) ($params['height'] ?? $file->getProperty('height')),
                ]
            );
    }

    /**
     * Get maximum image dimensions from page TSConfig.
     *
     * Reads dimension limits from TSConfig and enforces reasonable bounds
     * to prevent resource exhaustion attacks.
     *
     * @param string[] $params Request parameters including optional 'pid' and 'richtextConfigurationName'
     *
     * @return array<string, int> Array with 'width' and 'height' keys containing validated dimension limits
     */
    protected function getMaxDimensions(array $params): array
    {
        $tsConfig = BackendUtility::getPagesTSconfig((int) ($params['pid'] ?? 0));
        $richtextConfigurationName = $params['richtextConfigurationName'] ?? 'default';
        if ($richtextConfigurationName === '') {
            $richtextConfigurationName = 'default';
        }

        // Safe array access: fallback to empty array if TSConfig structure doesn't exist
        $rteConfig = $tsConfig['RTE.'][$richtextConfigurationName . '.'] ?? [];
        $imageOptions = $rteConfig['buttons.']['image.']['options.']['magic.'] ?? [];

        // Type cast to ensure integers (handles string values from TSConfig)
        $maxHeight = (int) ($imageOptions['maxHeight'] ?? self::IMAGE_DEFAULT_MAX_HEIGHT);
        $maxWidth  = (int) ($imageOptions['maxWidth'] ?? self::IMAGE_DEFAULT_MAX_WIDTH);

        // Enforce reasonable bounds: 1px minimum, 10000px maximum
        // This prevents resource exhaustion (10000x10000 ≈ 400MB vs 50000x50000 ≈ 10GB)
        $maxHeight = max(self::IMAGE_MIN_DIMENSION, min(self::IMAGE_MAX_DIMENSION, $maxHeight));
        $maxWidth  = max(self::IMAGE_MIN_DIMENSION, min(self::IMAGE_MAX_DIMENSION, $maxWidth));

        return ['width' => $maxWidth, 'height' => $maxHeight];
    }
}
