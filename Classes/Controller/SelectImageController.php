<?php

/**
 * This file is part of the package netresearch/rte-ckeditor-image.
 *
 * For the full copyright and license information, please read the
 * LICENSE file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Netresearch\RteCKEditorImage\Controller;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use TYPO3\CMS\Backend\Controller\ElementBrowserController;
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
     * @var bool
     */
    protected bool $isInfoAction = false;

    /**
     * @var ResourceFactory
     */
    private ResourceFactory $resourceFactory;

    /**
     * @var MagicImageService
     */
    private MagicImageService $magicImageService;

    /**
     * Forward to infoAction if wanted
     *
     * @param ServerRequestInterface $request
     *
     * @return ResponseInterface
     */
    public function mainAction(ServerRequestInterface $request): ResponseInterface
    {
        $this->resourceFactory = GeneralUtility::makeInstance(ResourceFactory::class);
        $this->magicImageService = GeneralUtility::makeInstance(MagicImageService::class);

        $isInfoAction = GeneralUtility::_GP('action') === 'info';
        $queryParams = $request->getQueryParams();

        if (!$isInfoAction) {
            $bparams = explode('|', (string)$queryParams['bparams']);

            if (isset($bparams[3]) && ($bparams[3] === '')) {
                $bparams[3] = $GLOBALS['TYPO3_CONF_VARS']['GFX']['imagefile_ext'];
                $queryParams['bparams'] = implode('|', $bparams);
            }
        }

        $request = $request->withQueryParams($queryParams);

        return $isInfoAction ? $this->infoAction($request) : parent::mainAction($request);
    }

    /**
     * Retrieve image info
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
            die;
        }

        $file          = $this->getImage((int)$id);
        $processedFile = $this->processImage($file, $params);

        return new JsonResponse(
            [
                'uid'       => $file->getUid(),
                'alt'       => $file->getProperty('alternative') ?? '',
                'title'     => $file->getProperty('title') ?? '',
                'width'     => $file->getProperty('width'),
                'height'    => $file->getProperty('height'),
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
            ]
        );
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
        } catch (\Exception) {
            $file = null;
        }

        if ($file === null) {
            header(HttpUtility::HTTP_STATUS_404);
            die;
        }

        return $file;
    }

    /**
     * Get the processed image.
     *
     * @param File     $file   The original image file
     * @param string[] $params The parameters used to process the image
     *
     * @return ProcessedFile
     */
    protected function processImage(File $file, array $params): ProcessedFile
    {
        // TODO: Get this from page ts config
        $this->magicImageService->setMagicImageMaximumDimensions(
            [
                'buttons.' => [
                    'image.' => [
                        'options.' => [
                            'magic.' => [
                                'maxWidth' => 1920,
                                'maxHeight' => 9999,
                            ],
                        ],
                    ],
                ],
            ]
        );

        return $this->magicImageService
            ->createMagicImage(
                $file,
                [
                    'width'  => (int)($params['width'] ?? $file->getProperty('width')),
                    'height' => (int)($params['height'] ?? $file->getProperty('height')),
                ]
            );
    }
}
