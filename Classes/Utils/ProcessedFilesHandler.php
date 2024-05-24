<?php

namespace Netresearch\RteCKEditorImage\Utils;

use Symfony\Component\Process\Process;
use TYPO3\CMS\Core\Resource\File;
use TYPO3\CMS\Core\Resource\ProcessedFile;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Extbase\Service\ImageService;

class ProcessedFilesHandler
{
    /**
     * Create a processed variant of a file based on the given configuration.
     * Returns the processedFile or false if the file could not be created
     *
     * Example for the image configuration:
     *   $imageConfiguration = [
     *     'width' => '200c',
     *     'height' => '200c',
     *   ];
     *
     * @param File $file The file object
     * @param array $imageConfiguration The image configuration
     * @return ProcessedFile|bool
     */
    function createProcessedFile(File $file, array $imageConfiguration): ProcessedFile|bool
    {
        /** @var ImageService $imageService */
        $imageService = GeneralUtility::makeInstance(ImageService::class);

        // Process the file with the given configuration
        try {
            $processedImage = $imageService->applyProcessingInstructions($file, $imageConfiguration);

            // Check if the processed file exists
            if ($processedImage instanceof ProcessedFile && $processedImage->getOriginalFile()->exists()) {
                return $processedImage;
            }

            return false;
        } catch (\Exception $e) {
            return false;
        }
    }
}
