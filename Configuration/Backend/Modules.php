<?php

use Vendor\FalPhotoBrowser\Controller\PhotoBrowserController;

return [
    'falphotobrowser' => [
        'parent' => 'file',
        'position' => ['after' => 'file_FilelistList'],
        'access' => 'user',
        'workspaces' => 'live',
        'iconIdentifier' => 'module-falphotobrowser',
        'path' => '/module/file/photobrowser',
        'labels' => 'LLL:EXT:fal_photo_browser/Resources/Private/Language/locallang_mod.xlf',
        'routes' => [
            '_default' => [
                'target' => PhotoBrowserController::class . '::indexAction',
            ],
        ],
    ],
];
