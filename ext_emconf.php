<?php

$EM_CONF[$_EXTKEY] = [
    'title' => 'FAL Photo Browser',
    'description' => 'Search and import stock photos directly in TYPO3 backend - Powered by Unsplash',
    'category' => 'be',
    'author' => 'Olivier Dobberkau',
    'author_email' => 'olivier.dobberkau@dkd.de',
    'state' => 'beta',
    'version' => '1.1.1',
    'constraints' => [
        'depends' => [
            'typo3' => '14.0.0-14.99.99',
        ],
        'conflicts' => [],
        'suggests' => [],
    ],
];
