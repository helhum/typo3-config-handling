<?php
declare(strict_types=1);
namespace Helhum\TYPO3\ConfigHandling;

use Symfony\Component\Yaml\Yaml;
use TYPO3\CMS\Core\Utility\GeneralUtility;

trait SiteConfigurationTrait
{
    public function write(string $siteIdentifier, array $configuration, bool $protectPlaceholders = false): void
    {
        $fileName = $this->configPath . '/' . $siteIdentifier . '/config.yaml';
        if (!file_exists($fileName)) {
            GeneralUtility::mkdir_deep($this->configPath . '/' . $siteIdentifier);
        }
        GeneralUtility::writeFile($fileName, Yaml::dump($configuration, 99));
        $this->siteConfigurationChanged();
        $this->cache->remove('pseudo-sites');
    }
}
