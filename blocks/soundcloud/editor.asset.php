<?php
/**
 * SoundCloud Block — Editor Script Asset File
 *
 * WordPress uses this to enqueue the correct script dependencies before
 * editor.js runs. Must return an array with 'dependencies' and 'version'.
 *
 * @package Headless
 */
return [
    'dependencies' => [
        'wp-blocks',
        'wp-element',
        'wp-block-editor',
        'wp-api-fetch',
        'wp-components',
    ],
    'version' => '1.0.0',
];
