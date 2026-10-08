<?php
return [
    'BE' => [
        'debug' => true,
        'installToolPassword' => '$argon2i$v=19$m=65536,t=16,p=1$YUVRUjBFUmp3ejlKYi9KWA$vhrJnXYRSbX07oeyf8WEZbYcjEu4MXtPlMcA4I+uOsU',
        'passwordHashing' => [
            'className' => 'TYPO3\\CMS\\Core\\Crypto\\PasswordHashing\\Argon2iPasswordHash',
            'options' => [],
        ],
    ],
    'DB' => [
        'Connections' => [
            'Default' => [
                'charset' => 'utf8',
                'driver' => 'mysqli',
            ],
        ],
    ],
    'EXTENSIONS' => [
        'backend' => [
            'backendFavicon' => '',
            'backendLogo' => '',
            'loginBackgroundImage' => '',
            'loginFootnote' => '',
            'loginHighlightColor' => '',
            'loginLogo' => '',
            'loginLogoAlt' => '',
        ],
        'eventnews' => [
            'overrideAdministrationModuleLabel' => '0',
        ],
        'extensionmanager' => [
            'automaticInstallation' => '1',
            'offlineMode' => '0',
        ],
        'news' => [
            'advancedMediaPreview' => '1',
            'archiveDate' => 'date',
            'categoryBeGroupTceFormsRestriction' => '0',
            'categoryRestriction' => '',
            'contentElementRelation' => '1',
            'dateTimeNotRequired' => '0',
            'hidePageTreeForAdministrationModule' => '0',
            'manualSorting' => '0',
            'pageTreePluginPreview' => '1',
            'prependAtCopy' => '1',
            'resourceFolderImporter' => '/news_import',
            'rteForTeaser' => '0',
            'showAdministrationModule' => '1',
            'slugBehaviour' => 'unique',
            'storageUidImporter' => '1',
            'tagPid' => '1',
        ],
        'ot_alerts' => [
            'pushoverEmergencyExpire' => '3600',
            'pushoverEmergencyRetry' => '60',
            'reminderInterval' => '3600',
        ],
        'ot_cefluidtemplates' => [
            'templates' => 'EXT:ot_cefluidtemplates/Resources/Private/Templates/',
        ],
        'ot_flippingbook' => [
            'flippingBookDirectory' => 'public/flippingbook/',
        ],
        'ot_heroimage' => [
            'desktopHeight' => '450',
            'desktopWidth' => '2560',
            'mobileHeight' => '576',
            'mobileWidth' => '768',
        ],
        'ot_irrebuttons' => [
            'enableButtonsForCTypes' => 'text',
            'icons' => 'chevron-left, chevron-right, download, file, file-pdf, paper-plane, arrow-up-right-from-square',
            'lightboxTypes' => 'lightbox, lightboxIframe',
            'pathIcons' => '',
        ],
        'scheduler' => [
            'maxLifetime' => '1440',
        ],
        'tt_address' => [
            'readOnlyNameField' => '1',
            'storeBackwardsCompatName' => '1',
            'telephoneValidationPatternForJs' => '/[^\\d\\+\\s\\-]/g',
            'telephoneValidationPatternForPhp' => '/[^\\d\\+\\s\\-]/',
        ],
        'webp' => [
            'async' => '0',
            'async_throttle_ms' => '0',
            'convert_all' => '1',
            'converter' => 'Plan2net\\Webp\\Converter\\MagickConverter',
            'converter_avif' => 'Plan2net\\Webp\\Converter\\MagickConverter',
            'converter_jxl' => 'Plan2net\\Webp\\Converter\\MagickConverter',
            'exclude_directories' => '',
            'filter_pattern' => '/\\.(jpe?g|png|gif)\\.(webp|avif|jxl)$/i',
            'formats_enabled' => 'webp',
            'hide_webp' => '1',
            'mime_types' => 'image/jpeg,image/png,image/gif',
            'mime_types_avif' => 'image/jpeg,image/png,image/gif',
            'mime_types_jxl' => 'image/jpeg,image/png,image/gif',
            'parameters' => 'image/jpeg::-quality 85 -define webp:lossless=false|image/png::-quality 75 -define webp:lossless=true|image/gif::-quality 85 -define webp:lossless=true',
            'parameters_avif' => 'image/jpeg::-quality 60|image/png::-quality 75|image/gif::-quality 60',
            'parameters_jxl' => 'image/jpeg::-quality 75|image/png::-quality 90|image/gif::-quality 75',
            'quality_by_width' => '',
            'quality_by_width_avif' => '',
            'quality_by_width_jxl' => '',
            'silent' => '0',
            'use_system_settings' => '1',
        ],
    ],
    'FE' => [
        'cacheHash' => [
            'enforceValidation' => true,
        ],
        'debug' => true,
        'disableNoCacheParameter' => true,
        'passwordHashing' => [
            'className' => 'TYPO3\\CMS\\Core\\Crypto\\PasswordHashing\\Argon2iPasswordHash',
            'options' => [],
        ],
    ],
    'GFX' => [
        'processor' => 'GraphicsMagick',
        'processor_effects' => false,
        'processor_enabled' => true,
        'processor_path' => '/usr/bin/',
    ],
    'LOG' => [
        'TYPO3' => [
            'CMS' => [
                'deprecations' => [
                    'writerConfiguration' => [
                        'notice' => [
                            'TYPO3\CMS\Core\Log\Writer\FileWriter' => [
                                'disabled' => false,
                            ],
                        ],
                    ],
                ],
            ],
        ],
    ],
    'MAIL' => [
        'transport' => 'sendmail',
        'transport_sendmail_command' => '/usr/local/bin/mailpit sendmail -t --smtp-addr 127.0.0.1:1025',
        'transport_smtp_encrypt' => '',
        'transport_smtp_password' => '',
        'transport_smtp_server' => '',
        'transport_smtp_username' => '',
    ],
    'SYS' => [
        'UTF8filesystem' => true,
        'caching' => [
            'cacheConfigurations' => [
                'hash' => [
                    'backend' => 'TYPO3\\CMS\\Core\\Cache\\Backend\\Typo3DatabaseBackend',
                ],
                'pages' => [
                    'backend' => 'TYPO3\\CMS\\Core\\Cache\\Backend\\Typo3DatabaseBackend',
                    'options' => [
                        'compression' => true,
                    ],
                ],
                'rootline' => [
                    'backend' => 'TYPO3\\CMS\\Core\\Cache\\Backend\\Typo3DatabaseBackend',
                    'options' => [
                        'compression' => true,
                    ],
                ],
            ],
        ],
        'devIPmask' => '*',
        'displayErrors' => 1,
        'encryptionKey' => '915342f1c0a95d1939b50e7023069bd7d0345e2ae2769e1d60d32ad9d893ba0cab4547ae7bb210e4bf3ae6658d013874',
        'exceptionalErrors' => 12290,
        'features' => [
            'frontend.cache.autoTagging' => true,
            'security.system.enforceAllowedFileExtensions' => true,
        ],
        'sitename' => 'New TYPO3 site',
        'systemMaintainers' => [
            2,
        ],
    ],
];
