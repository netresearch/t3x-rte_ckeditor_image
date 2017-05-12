<?php

/**
 * Definitions of routes
 */
return [
    // Register RTE browse links wizard
    'rteckeditorimage_wizard_select_image' => [
        'path' => '/rte/wizard/selectimage',
        'target' => \Netresearch\RteCKEditor\Controller\SelectImageController::class . '::mainAction'
    ],
];
