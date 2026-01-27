<?php

defined('TYPO3') or die();

$columns = [
    'unsplash_photo_id' => [
        'label' => 'Unsplash Photo ID',
        'config' => [
            'type' => 'input',
            'readOnly' => true,
        ],
    ],
    'unsplash_photo_url' => [
        'label' => 'Unsplash Photo URL',
        'config' => [
            'type' => 'link',
            'readOnly' => true,
        ],
    ],
    'unsplash_photographer_url' => [
        'label' => 'Photographer URL',
        'config' => [
            'type' => 'link',
            'readOnly' => true,
        ],
    ],
];

\TYPO3\CMS\Core\Utility\ExtensionManagementUtility::addTCAcolumns('sys_file_metadata', $columns);
\TYPO3\CMS\Core\Utility\ExtensionManagementUtility::addToAllTCAtypes(
    'sys_file_metadata',
    '--div--;Unsplash,unsplash_photo_id,unsplash_photo_url,unsplash_photographer_url'
);
