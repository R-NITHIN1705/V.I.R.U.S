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
    'htmx.org' => [
        'version' => '2.0.7',
    ],
    'htmx-ext-sse' => [
        'version' => '2.2.4',
    ],
    'react' => [
        'version' => '19.3.0',
    ],
    '@emotion/react/jsx-runtime' => [
        'version' => '11.11.1',
    ],
    '@tanstack/query-core' => [
        'version' => '5.102.8',
    ],
    'dequal' => [
        'version' => '2.0.3',
    ],
    'react/jsx-runtime' => [
        'version' => '18.2.0',
    ],
    '@emotion/cache' => [
        'version' => '11.11.0',
    ],
    '@babel/runtime/helpers/esm/extends' => [
        'version' => '7.22.3',
    ],
    '@emotion/weak-memoize' => [
        'version' => '0.3.1',
    ],
    'hoist-non-react-statics' => [
        'version' => '3.3.2',
    ],
    '@emotion/utils' => [
        'version' => '1.2.1',
    ],
    '@emotion/serialize' => [
        'version' => '1.1.2',
    ],
    '@emotion/use-insertion-effect-with-fallbacks' => [
        'version' => '1.0.1',
    ],
    '@babel/runtime/helpers/extends' => [
        'version' => '7.22.3',
    ],
    '@emotion/sheet' => [
        'version' => '1.4.0',
    ],
    'stylis' => [
        'version' => '4.2.0',
    ],
    '@emotion/memoize' => [
        'version' => '0.8.1',
    ],
    'react-is' => [
        'version' => '16.12.0',
    ],
    '@emotion/hash' => [
        'version' => '0.9.1',
    ],
    '@emotion/unitless' => [
        'version' => '0.8.1',
    ],
];
