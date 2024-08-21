<?php

use Netresearch\RteCKEditorImage\Controller\SelectImageController;

return [
    'rteckeditorimage_wizard_select_image' => [
        'path' => '/rte/wizard/selectimage',
        'target' => SelectImageController::class . '::mainAction',
        'parameters' => [
            'mode' => 'file',
        ],
    ],
];
