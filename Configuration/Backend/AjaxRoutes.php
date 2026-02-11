<?php

use DkdDobberkau\FalPhotoBrowser\Controller\PhotoBrowserController;

return [
    'falphotobrowser_search' => [
        'path' => '/photobrowser/search',
        'target' => PhotoBrowserController::class . '::searchAction',
    ],
    'falphotobrowser_import' => [
        'path' => '/photobrowser/import',
        'methods' => ['POST'],
        'target' => PhotoBrowserController::class . '::importAction',
    ],
    'falphotobrowser_chat' => [
        'path' => '/photobrowser/chat',
        'methods' => ['POST'],
        'target' => PhotoBrowserController::class . '::chatAction',
    ],
    'falphotobrowser_collections' => [
        'path' => '/photobrowser/collections',
        'target' => PhotoBrowserController::class . '::collectionsAction',
    ],
];
