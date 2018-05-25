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

use Helhum\ConfigLoader\CachedConfigurationLoader;
use Helhum\ConfigLoader\ConfigurationLoader;
use Helhum\ConfigLoader\Processor\PlaceholderValue;
use Helhum\ConfigLoader\Reader\ClosureConfigReader;
use Helhum\TYPO3\ConfigHandling\Processor\ExtensionSettingsSerializer;
use TYPO3\CMS\Core\Utility\GeneralUtility;

class ConfigLoader
{
    /**
     * @var string
     */
    private $configFile;

    /**
     * @var bool
     */
    private $strictPlaceholderParsing;

    /**
     * @var ConfigurationLoader
     */
    private $loader;

    public function __construct(string $configFile, bool $strictPlaceholderParsing = false)
    {
        $this->configFile = $configFile;
        $this->strictPlaceholderParsing = $strictPlaceholderParsing;
        $this->loader = $this->buildLoader($configFile);
    }

    public function populate(bool $enableCache = false)
    {
        if ($enableCache) {
            $cachedLoader = new CachedConfigurationLoader(
                $this->getCacheDir(),
                $this->getCacheIdentifier(),
                function () {
                    return $this->loader;
                }
            );
            $config = $cachedLoader->load();
        } else {
            $config = $this->load();
        }
        $GLOBALS['TYPO3_CONF_VARS'] = $config;
    }

    public function load(): array
    {
        return $this->loader->load();
    }

    private function buildLoader(string $configFile): ConfigurationLoader
    {
        $mainConfig = (new Typo3Config($configFile))->readConfig();
        $mainConfigReader = new ClosureConfigReader(
            function () use (&$mainConfig) {
                return $mainConfig;
            },
            function () use (&$mainConfig) {
                return !empty($mainConfig);
            }
        );
        $configReaders = [$mainConfigReader];
        $defaultConfigImported = !empty($mainConfig['SYS']['lang']['format']['priority']);
        if (!$defaultConfigImported) {
            $defaultConfigFile = getenv('TYPO3_PATH_ROOT') . '/typo3/sysext/core/Configuration/DefaultConfiguration.php';
            $defaultConfigReader = new ClosureConfigReader(
                    function () use ($defaultConfigFile) {
                        return require $defaultConfigFile;
                    },
                    function () use ($defaultConfigFile) {
                        return file_exists($defaultConfigFile);
                    }
                );
            array_unshift($configReaders, $defaultConfigReader);
        }

        $processors = [
            new PlaceholderValue($this->strictPlaceholderParsing),
            new ExtensionSettingsSerializer(),
        ];

        $customProcessors = null;
        if (isset($mainConfig['processors'])) {
            $processors = array_merge($processors, $this->createCustomProcessors($mainConfig['processors']));
            unset($mainConfig['processors']);
        }

        return new ConfigurationLoader(
            $configReaders,
            $processors
        );
    }

    private function createCustomProcessors(array $processorsConfig): array
    {
        $processors = [];
        foreach ($processorsConfig as $processorConfig) {
            $processors[] = new $processorConfig['class'];
        }

        return $processors;
    }

    private function getCacheDir(): string
    {
        return getenv('TYPO3_PATH_COMPOSER_ROOT') . '/var/cache';
    }

    private function getCacheIdentifier(): string
    {
        $rootDir = getenv('TYPO3_PATH_COMPOSER_ROOT');
        $confDir = dirname($this->configFile);
        $fileWatches = array_merge(
            [
                $rootDir . '/.env',
                $rootDir . '/composer.json',
            ],
            glob($confDir . '/*.*')
        );
        $identifier = GeneralUtility::getApplicationContext();
        foreach ($fileWatches as $fileWatch) {
            if (file_exists($fileWatch)) {
                $identifier .= filemtime($fileWatch);
            }
        }
        return md5($identifier);
    }
}
