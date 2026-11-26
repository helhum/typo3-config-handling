<?php
declare(strict_types=1);

use Helhum\TYPO3\ConfigHandling\Typo3SiteConfiguration;
use Helhum\TYPO3\ConfigHandling\Typo3SiteConfigurationV13;
use TYPO3\CMS\Core\Configuration\SiteConfiguration;
use TYPO3\CMS\Core\Information\Typo3Version;

return [
    'Site' => [
        'main' => [
            'base' => 'https://my-fancy-host.tld/',
        ],
    ],
    'SYS' => [
        'Objects' => [
            SiteConfiguration::class => [
                'className' => (new Typo3Version())->getMajorVersion() >= 14
                    ? Typo3SiteConfiguration::class
                    : Typo3SiteConfigurationV13::class,
            ],
        ],
    ],
];
