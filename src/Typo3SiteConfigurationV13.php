<?php
declare(strict_types=1);
namespace Helhum\TYPO3\ConfigHandling;

use Helhum\TYPO3\ConfigHandling\ConfigReader\SiteYamlFileLoader;
use Psr\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use TYPO3\CMS\Core\Cache\Frontend\FrontendInterface;
use TYPO3\CMS\Core\Cache\Frontend\PhpFrontend;
use TYPO3\CMS\Core\Configuration\Loader\YamlFileLoader;
use TYPO3\CMS\Core\Configuration\SiteConfiguration;
use TYPO3\CMS\Core\Site\Set\SetRegistry;
use TYPO3\CMS\Core\Site\SiteSettingsFactory;

class Typo3SiteConfigurationV13 extends SiteConfiguration
{
    use SiteConfigurationTrait;

    public function __construct(
        #[Autowire('%env(TYPO3:configPath)%/sites')]
        string $configPath,
        SiteSettingsFactory $siteSettingsFactory,
        SetRegistry $setRegistry,
        EventDispatcherInterface $eventDispatcher,
        #[Autowire(service: 'cache.core')]
        PhpFrontend $cache,
        YamlFileLoader $yamlFileLoader,
        #[Autowire(service: 'cache.runtime')]
        FrontendInterface $runtimeCache,
    ) {
        parent::__construct(
            $configPath,
            $siteSettingsFactory,
            $setRegistry,
            $eventDispatcher,
            $cache,
            new SiteYamlFileLoader($yamlFileLoader),
            $runtimeCache,
        );
    }
}
