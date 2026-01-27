<?php

use Vendor\T3Unsplash\Controller\UnsplashController;

return [
    'unsplash_search' => [
        'path' => '/unsplash/search',
        'target' => UnsplashController::class . '::searchAction',
    ],
    'unsplash_import' => [
        'path' => '/unsplash/import',
        'methods' => ['POST'],
        'target' => UnsplashController::class . '::importAction',
    ],
    'unsplash_collections' => [
        'path' => '/unsplash/collections',
        'target' => UnsplashController::class . '::collectionsAction',
    ],
];
