<?php
declare(strict_types=1);
namespace Helhum\TYPO3\ConfigHandling;

use Helhum\ConfigLoader\ConfigurationReaderFactory;
use Helhum\ConfigLoader\Processor\PlaceholderValue;
use Symfony\Component\Finder\Finder;
use Symfony\Component\Yaml\Yaml;
use TYPO3\CMS\Core\Configuration\SiteConfiguration;
use TYPO3\CMS\Core\Core\Environment;
use TYPO3\CMS\Core\Site\Entity\SiteSettings;
use TYPO3\CMS\Core\Utility\GeneralUtility;

class Typo3SiteConfiguration extends SiteConfiguration
{
    /**
     * Load plain configuration
     * This method should only be used in case the original configuration as it exists in the file should be loaded,
     * for example for writing / editing configuration.
     *
     * All read related actions should be performed on the site entity.
     *
     * @param string $siteIdentifier
     *
     * @return array
     */
    public function load(string $siteIdentifier): array
    {
        $fileName = $this->configPath . '/' . $siteIdentifier . '/' . $this->configFileName;
        $factory = new ConfigurationReaderFactory(Environment::getConfigPath());
        return $factory->createRootReader($fileName)->readConfig();
    }

    protected function getSiteSettings(string $siteIdentifier, array $siteConfiguration): SiteSettings
    {
        $siteSettings = parent::getSiteSettings($siteIdentifier, $siteConfiguration);
        if (!isset($siteConfiguration['settings'])) {
            return $siteSettings;
        }
        return new SiteSettings(array_replace_recursive($siteSettings->getAll(), $siteConfiguration['settings']));
    }

    /**
     * Add or update a site configuration
     *
     * @param string $siteIdentifier
     * @param array $configuration
     *
     * @throws \TYPO3\CMS\Core\Cache\Exception\NoSuchCacheException
     */
    public function write(string $siteIdentifier, array $configuration, bool $protectPlaceholders = false): void
    {
        $fileName = $this->configPath . '/' . $siteIdentifier . '/' . $this->configFileName;
        if (!file_exists($fileName)) {
            GeneralUtility::mkdir_deep($this->configPath . '/' . $siteIdentifier);
        }
        $yamlFileContents = Yaml::dump($configuration, 99);
        GeneralUtility::writeFile($fileName, $yamlFileContents);
        $this->cache->remove($this->cacheIdentifier);
        $this->cache->remove('pseudo-sites');
    }

    /**
     * Read the site configuration from config files.
     *
     * @param bool $useCache
     *
     * @throws \TYPO3\CMS\Core\Cache\Exception\NoSuchCacheException
     *
     * @return array
     */
    protected function getAllSiteConfigurationFromFiles(bool $useCache = true): array
    {
        // Check if the data is already cached
        $siteConfiguration = $useCache ? $this->cache->require($this->cacheIdentifier) : false;
        if ($siteConfiguration !== false && $siteConfiguration !== null) {
            return $siteConfiguration;
        }
        $finder = new Finder();
        try {
            $finder->files()->depth(0)->name($this->configFileName)->in($this->configPath . '/*');
        } catch (\InvalidArgumentException $e) {
            // Directory $this->configPath does not exist yet
            $finder = [];
        }
        $factory = new ConfigurationReaderFactory(Environment::getConfigPath());
        $siteConfiguration = [];
        foreach ($finder as $fileInfo) {
            $configFile = GeneralUtility::fixWindowsFilePath((string)$fileInfo);
            $identifier = basename($fileInfo->getPath());
            $reader = $factory->createRootReader($configFile);
            $placeHolderProcessor = new PlaceholderValue(false);
            $configuration = $placeHolderProcessor->processConfig(
                array_replace_recursive(
                    $reader->readConfig(),
                    $GLOBALS['TYPO3_CONF_VARS']['Site'][$identifier] ?? []
                )
            );
            $siteConfiguration[$identifier] = $configuration;
        }
        $this->cache->set($this->cacheIdentifier, 'return ' . var_export($siteConfiguration, true) . ';');

        return $siteConfiguration;
    }
}
