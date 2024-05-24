<?php

namespace Netresearch\RteCKEditorImage\Utils;

use TYPO3\CMS\Core\Resource\File;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Extbase\Service\ImageService;

class CheckProcessed
{
    /**
     * Check if a file has a processed variant
     *
     *   $processingConfiguration = [
     *     'width' => '200c',
     *     'height' => '200c',
     *   ];
     *
     * @param File $file The file object
     * @param array $imageConfiguration The image configuration
     * @return bool True if there is a processed variant, otherwise false
     */
    function hasProcessedVariant(File $file, array $imageConfiguration): bool
    {
        /** @var ImageService $imageService */
        $imageService = GeneralUtility::makeInstance(ImageService::class);

        // Process the file with the given configuration
        try {
            $processedImage = $imageService->applyProcessingInstructions($file, $imageConfiguration);

            // Check if the processed file exists
            return $processedImage !== null && $processedImage->getOriginalFile()->exists();
        } catch (\Exception $e) {
            return false;
        }
    }
}
