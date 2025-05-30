<?php
declare(strict_types=1);
namespace Helhum\TYPO3\ConfigHandling;

/***************************************************************
 *  Copyright notice
 *
 *  (c) 2017 Helmut Hummel <info@helhum.io>
 *  All rights reserved
 *
 *  The GNU General Public License can be found at
 *  http://www.gnu.org/copyleft/gpl.html.
 *  A copy is found in the text file GPL.txt and important notices to the license
 *  from the author is found in LICENSE.txt distributed with these scripts.
 *
 *
 *  This script is distributed in the hope that it will be useful,
 *  but WITHOUT ANY WARRANTY; without even the implied warranty of
 *  MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 *  GNU General Public License for more details.
 *
 *  This copyright notice MUST APPEAR in all copies of the script!
 ***************************************************************/

use Helhum\ConfigLoader\ConfigurationReaderFactory;
use TYPO3\CMS\Core\Core\Environment;

class ConfigExtractor
{
    private readonly ConfigDumper $configDumper;

    private readonly ConfigCleaner $configCleaner;

    private readonly ConfigLoader $configLoader;

    private readonly ConfigurationReaderFactory $readerFactory;

    private readonly string $overrideConfigFile;

    public function __construct(
        ?ConfigDumper $configDumper = null,
        ?ConfigCleaner $configCleaner = null,
        ?ConfigLoader $configLoader = null,
        ?ConfigurationReaderFactory $readerFactory = null,
        ?string $overrideConfigFile = null
    ) {
        $this->configDumper = $configDumper ?? new ConfigDumper();
        $this->configCleaner = $configCleaner ?? new ConfigCleaner();
        $this->configLoader = $configLoader ?? new ConfigLoader(Environment::getContext()->isProduction());
        $this->readerFactory = $readerFactory ?? new ConfigurationReaderFactory();
        $this->overrideConfigFile = $overrideConfigFile ?: SettingsFiles::getOverrideSettingsFile();
    }

    public function extractConfig(array $config, array $defaultConfig, ?string $configFile = null): bool
    {
        $configFile = $configFile ?: $this->overrideConfigFile;
        $extractedConfig = false;
        $mainConfig = $this->configCleaner->cleanConfig(
            $config,
            $defaultConfig
        );

        if (!empty($mainConfig)) {
            $this->configDumper->dumpToFile(
                $this->mergeWithCurrentValuesCleanedFromBaseValues($mainConfig, $configFile),
                $configFile
            );
            $extractedConfig = true;
        }

        return $extractedConfig;
    }

    private function mergeWithCurrentValuesCleanedFromBaseValues(array $config, string $configFile): array
    {
        $currentConfig = [];
        if (file_exists($configFile)) {
            $currentConfig = $this->readerFactory->createReader($configFile)->readConfig();
        }

        return $this->configCleaner->cleanConfig(
            array_replace_recursive($currentConfig, $config),
            $this->configLoader->loadBase()
        );
    }
}
