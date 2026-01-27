<?php

use Vendor\T3Unsplash\Controller\UnsplashController;

return [
    't3unsplash' => [
        'parent' => 'file',
        'position' => ['after' => 'file_FilelistList'],
        'access' => 'user',
        'workspaces' => 'live',
        'iconIdentifier' => 'module-unsplash',
        'path' => '/module/file/unsplash',
        'labels' => 'LLL:EXT:t3_unsplash/Resources/Private/Language/locallang_mod.xlf',
        'routes' => [
            '_default' => [
                'target' => UnsplashController::class . '::indexAction',
            ],
        ],
    ],
];
