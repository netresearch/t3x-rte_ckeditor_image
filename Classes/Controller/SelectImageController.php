<?php

/**
 * This file is part of the package netresearch/rte-ckeditor-image.
 *
 * For the full copyright and license information, please read the
 * LICENSE file that was distributed with this source code.
 */

declare(strict_types=1);

/**
 * See class comment
 *
 * PHP version 7
 *
 * @category   Netresearch
 * @package    RteCKEditor
 * @subpackage Controller
 * @author     Christian Opitz <christian.opitz@netresearch.de>
 * @license    http://www.netresearch.de Netresearch Copyright
 * @link       http://www.netresearch.de
 */


namespace Netresearch\RteCKEditorImage\Controller;

use Exception;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use TYPO3\CMS\Core\Configuration\Richtext;
use TYPO3\CMS\Core\Http\JsonResponse;
use TYPO3\CMS\Core\Resource\File;
use TYPO3\CMS\Core\Resource\Service\MagicImageService;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Recordlist\Controller\ElementBrowserController;
use TYPO3\CMS\Core\Resource\ProcessedFile;
use TYPO3\CMS\Core\Resource\ResourceFactory;
use TYPO3\CMS\Core\Utility\HttpUtility;

/**
 * Controller for the image select wizard
 *
 * PHP version 7
 *
 * @category   Netresearch
 * @package    RteCKEditor
 * @subpackage Controller
 * @author     Christian Opitz <christian.opitz@netresearch.de>
 * @license    http://www.gnu.de/documents/gpl-2.0.de.html GPL 2.0+
 * @link       http://www.netresearch.de
 */
class SelectImageController extends ElementBrowserController
{
    protected bool $isInfoAction;

    /**
     * Constructor
     */
    public function __construct()
    {
        $this->isInfoAction = GeneralUtility::_GP('action') === 'info';
        if (!$this->isInfoAction) {
            $bparams = explode('|', GeneralUtility::_GET('bparams'));
            if (!$bparams[3]) {
                $bparams[3] = $GLOBALS['TYPO3_CONF_VARS']['GFX']['imagefile_ext'];
                $_GET['bparams'] = implode('|', $bparams);
            }
        }
        $this->mode = 'file';
    }

    /**
     * Forward to infoAction if wanted
     *
     * @param ServerRequestInterface $request
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
     * @return ResponseInterface
     */
    public function infoAction(ServerRequestInterface $request): ResponseInterface
    {
        $id = $request->getQueryParams()['fileId'];
        $params = $request->getQueryParams()['P'] ?: [];
        if (!$id || !is_numeric($id)) {
            header(HttpUtility::HTTP_STATUS_412);
            die;
        }
        $file = $this->getImage((int)$id);
        $processedFile = $this->processImage($file, $params);

        $lang = $this->getLanguageService();
        $this->getLanguageService()->includeLLFile('EXT:core/Resources/Private/Language/locallang_core.xlf');
        $this->getLanguageService()->includeLLFile('EXT:frontend/Resources/Private/Language/locallang_ttc.xlf');
        $this->getLanguageService()->includeLLFile('EXT:rte_ckeditor_image/Resources/Private/Language/locallang_be.xlf');

        return new JsonResponse([
            'uid' => $file->getUid(),
            'alt' => empty($file->getProperty('alternative')) ? "" : $file->getProperty('alternative'),
            'title' => empty($file->getProperty('title')) ? "" : $file->getProperty('title'),
            'width' => $file->getProperty('width'),
            'height' =>$file->getProperty('height'),
            'url' => $this->prettifyImgUrl($file->getPublicUrl()),
            'processed' => [
                'width' => $processedFile->getProperty('width'),
                'height' => $processedFile->getProperty('height'),
                'url' => $this->prettifyImgUrl($processedFile->getPublicUrl())
            ],
            'lang' => [
                'override' => $lang->getLL('labels.placeholder.override'),
                'overrideNoDefault' => $lang->getLL('labels.placeholder.override_not_available'),
                'cssClass' => $lang->getLL('labels.ckeditor.cssclass'),
                'zoom' => $lang->getLL('image_zoom_formlabel')
            ]
        ]);
    }

    /**
     * Get the image url
     *
     * @param null|string $imgUrl
     *
     * @return string|null image url
     */
    protected function prettifyImgUrl(?string $imgUrl): ?string
    {
        if ($imgUrl === null) {
            return null;
        }

        $absoluteUrl = trim($imgUrl);
        if ((stripos($absoluteUrl, 'http') !== 0) && strpos($absoluteUrl, '/') !== 0) {
            $absoluteUrl = '/' .$absoluteUrl;
        }

        return $imgUrl;
    }

    /**
     * Get the image
     *
     * @param integer $id
     *
     * @return File
     */
    protected function getImage(int $id): ?File
    {
        try {
            $file = GeneralUtility::makeInstance(ResourceFactory::class)->getFileObject($id);
            if ($file->isDeleted() || $file->isMissing()) {
                $file = null;
            }
        } catch (Exception $e) {
            $file = null;
        }

        if (!$file) {
            header(HttpUtility::HTTP_STATUS_404);
            die;
        }

        return $file;
    }

    /**
     * Get the processed image
     *
     * @param File                 $file
     * @param array<string, mixed> $params
     *
     * @return ProcessedFile
     */
    protected function processImage(File $file, array $params): ProcessedFile
    {
        /** @var Richtext $richtextConfigurationProvider */
        $richtextConfigurationProvider = GeneralUtility::makeInstance(Richtext::class);
        $tsConfig = $richtextConfigurationProvider->getConfiguration(
            $params['table'],
            $params['fieldName'],
            (int)$params['pid'],
            $params['recordType'],
            ['richtext' => true]
        );

        /** @var MagicImageService $magicImageService */
        $magicImageService = GeneralUtility::makeInstance(MagicImageService::class);
        $magicImageService->setMagicImageMaximumDimensions($tsConfig);

        return $magicImageService->createMagicImage($file, [
            'width' => $params['width'] ?? $file->getProperty('width'),
            'height' => $params['height'] ?? $file->getProperty('height')
        ]);
    }
}
