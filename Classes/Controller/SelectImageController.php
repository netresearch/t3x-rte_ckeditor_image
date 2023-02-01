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
use TYPO3\CMS\Core\Configuration\Richtext;
use TYPO3\CMS\Core\Http\JsonResponse;
use TYPO3\CMS\Core\Resource\File;
use TYPO3\CMS\Core\Resource\ProcessedFile;
use TYPO3\CMS\Core\Resource\ResourceFactory;
use TYPO3\CMS\Core\Resource\Service\MagicImageService;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Core\Utility\HttpUtility;
use TYPO3\CMS\Recordlist\Controller\ElementBrowserController;

/**
 * Controller for the image select wizard.
 *
 * @author  Christian Opitz <christian.opitz@netresearch.de>
 * @license http://www.gnu.de/documents/gpl-2.0.de.html GPL 2.0+
 * @link    http://www.netresearch.de
 */
class SelectImageController extends ElementBrowserController
{
    /**
     * @var bool
     */
    protected bool $isInfoAction;

    /**
     * @var ResourceFactory
     */
    private ResourceFactory $resourceFactory;

    /**
     * @var Richtext
     */
    private Richtext $richText;

    /**
     * @var MagicImageService
     */
    private MagicImageService $magicImageService;

    /**
     * Constructor.
     */
    public function __construct()
    {
        // No dependency injection available here. :(
        $this->resourceFactory = GeneralUtility::makeInstance(ResourceFactory::class);
        $this->richText = GeneralUtility::makeInstance(Richtext::class);
        $this->magicImageService = GeneralUtility::makeInstance(MagicImageService::class);

        $this->isInfoAction = GeneralUtility::_GP('action') === 'info';

        if (!$this->isInfoAction) {
            $bparams = explode('|', GeneralUtility::_GET('bparams'));

            if (isset($bparams[3]) && ($bparams[3] === '')) {
                $bparams[3] = $GLOBALS['TYPO3_CONF_VARS']['GFX']['imagefile_ext'];
                $_GET['bparams'] = implode('|', $bparams);
            }
        }
    }

    /**
     * Forward to infoAction if wanted
     *
     * @param ServerRequestInterface $request
     *
     * @return ResponseInterface
     */
    public function mainAction(ServerRequestInterface $request): ResponseInterface
    {
        return $this->isInfoAction ? $this->infoAction($request) : parent::mainAction($request);
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
        $id     = $request->getQueryParams()['fileId'];
        $params = $request->getQueryParams()['P'] ?? [];

        if (!$id || !is_numeric($id)) {
            header(HttpUtility::HTTP_STATUS_412);
            die;
        }

        $file          = $this->getImage((int) $id);
        $processedFile = $this->processImage($file, $params);

        $lang = $this->getLanguageService();
        $this->getLanguageService()->includeLLFile('EXT:core/Resources/Private/Language/locallang_core.xlf');
        $this->getLanguageService()->includeLLFile('EXT:frontend/Resources/Private/Language/locallang_ttc.xlf');
        $this->getLanguageService()->includeLLFile('EXT:rte_ckeditor_image/Resources/Private/Language/locallang_be.xlf');

        return new JsonResponse([
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
                'override'          => $lang->getLL('labels.placeholder.override'),
                'overrideNoDefault' => $lang->getLL('labels.placeholder.override_not_available'),
                'cssClass'          => $lang->getLL('labels.ckeditor.cssclass'),
                'zoom'              => $lang->getLL('image_zoom_formlabel'),
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
        } catch (Exception $e) {
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
        $rteConfiguration = $this->richText
            ->getConfiguration(
                $params['table'],
                $params['fieldName'],
                (int) $params['pid'],
                $params['recordType'],
                [
                    'richtext' => true,
                ]
            );

        // Use the page tsConfig to set he maximum dimensions
        $this->magicImageService
            ->setMagicImageMaximumDimensions($rteConfiguration);

        return $this->magicImageService
            ->createMagicImage(
                $file,
                [
                    'width'  => (int) ($params['width'] ?? $file->getProperty('width')),
                    'height' => (int) ($params['height'] ?? $file->getProperty('height')),
                ]
            );
    }
}
