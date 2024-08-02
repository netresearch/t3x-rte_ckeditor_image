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
     * Returns the ProcessedFile or throws an exception if creation failed.
     *
     * Example for the image configuration:
     *   $imageConfiguration = [
     *     'width' => '200c',
     *     'height' => '200c',
     *   ];
     *
     * @param  File    $file               The file object
     * @param  mixed[] $imageConfiguration The image configuration
     * @throws \Exception
     * @return ProcessedFile
     */
    public function createProcessedFile(File $file, array $imageConfiguration): ProcessedFile
    {
        /**
         * @var ImageService $imageService
         */
        $imageService = GeneralUtility::makeInstance(ImageService::class);

        // Process the file with the given configuration
        try {
            return $imageService->applyProcessingInstructions($file, $imageConfiguration);
        } catch (\Exception) {
            throw new \Exception('Could not create processed file', 1716565499);
        }
    }
}
