<?php

/**
 * Definitions of routes
 */
return [
    'rteckeditorimage_wizard_select_image' => [
        'path' => '/rte/wizard/selectimage',
        'target' => \Netresearch\RteCKEditorImage\Controller\SelectImageController::class . '::mainAction'
    ]
];
