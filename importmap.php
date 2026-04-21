<?php

/**
 * Returns the importmap for this application.
 *
 * - "path" is a path inside the asset mapper system. Use the
 *     "debug:asset-map" command to see the full list of paths.
 *
 * - "entrypoint" (JavaScript only) set to true for any module that will
 *     be used as an "entrypoint" (and passed to the importmap() Twig function).
 *
 * The "importmap:require" command can be used to add new entries to this file.
 */
return [
    'app' => [
        'path' => './assets/app.js',
        'entrypoint' => true,
    ],
    'firebase-messaging' => [
        'path' => './assets/firebase-messaging.js',
        'entrypoint' => true,
    ],
    '@hotwired/stimulus' => [
        'version' => '3.2.2',
    ],
    '@symfony/stimulus-bundle' => [
        'path' => './vendor/symfony/stimulus-bundle/assets/dist/loader.js',
    ],
    '@hotwired/turbo' => [
        'version' => '7.3.0',
    ],
    'centrifuge' => [
        'version' => '5.5.3',
    ],
    'firebase/app' => [
        'version' => '12.10.0',
    ],
    'firebase/messaging' => [
        'version' => '12.10.0',
    ],
    '@firebase/app' => [
        'version' => '0.14.9',
    ],
    '@firebase/messaging' => [
        'version' => '0.12.24',
    ],
    '@firebase/component' => [
        'version' => '0.7.1',
    ],
    '@firebase/logger' => [
        'version' => '0.5.0',
    ],
    '@firebase/util' => [
        'version' => '1.14.0',
    ],
    'idb' => [
        'version' => '7.1.1',
    ],
    '@firebase/installations' => [
        'version' => '0.6.20',
    ],
];
