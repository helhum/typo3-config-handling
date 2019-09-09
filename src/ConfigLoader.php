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
use Helhum\TYPO3\ConfigHandling\Processor\ExtensionSettingsSerializer;
use Helhum\Typo3Console\Mvc\Cli\Symfony\Input\ArgvInput;
use TYPO3\CMS\Core\Core\Environment;
use TYPO3\CMS\Core\Service\OpcodeCacheService;
use TYPO3\CMS\Core\Utility\GeneralUtility;

class ConfigLoader
{
    /**
     * @var bool
     */
    private $isProduction;

    /**
     * @var string
     */
    private $alternativeSettingsFile;

    public function __construct(bool $isProduction, string $alternativeSettingsFile = null)
    {
        $this->isProduction = $isProduction;
        $this->alternativeSettingsFile = $alternativeSettingsFile;
    }

    public function populate()
    {
        $shouldCache = $this->shouldCache();
        $hasCache = $shouldCache ? file_exists($cacheFile = $this->getCacheFile()) : false;
        if ($hasCache) {
            $config = require $cacheFile;
        } else {
            $config = $this->load();
        }
        $GLOBALS['TYPO3_CONF_VARS'] = $config;
        if ($shouldCache && !$hasCache && isset($cacheFile)) {
            $configString = var_export($config, true);
            $configString = <<<EOF
<?php
return
$configString;

EOF;
            GeneralUtility::mkdir_deep(dirname($cacheFile));
            GeneralUtility::writeFile($cacheFile, $configString);
        }
    }

    private function shouldCache(): bool
    {
        if (!Environment::isCli()) {
            return $this->isProduction || getenv('TYPO3_CONFIG_HANDLING_CACHE');
        }
        if (getenv('TYPO3_CONSOLE_SUB_PROCESS')) {
            return false;
        }
        $shouldCache = $this->isProduction || getenv('TYPO3_CONFIG_HANDLING_CACHE');
        $input = new ArgvInput();
        $lowLevelNamespaces = '/(cache|install|upgrade|configuration):/';

        return $shouldCache && preg_match($lowLevelNamespaces, $input->getFirstArgument() ?? 'list') === 0;
    }

    public function load(): array
    {
        return $this->buildLoader()->load();
    }

    public function loadBase(): array
    {
        $configFile = $this->alternativeSettingsFile ?? SettingsFiles::getSettingsFile($this->isProduction);

        return (new Typo3Config($configFile))->readBaseConfig();
    }

    public function flushCache(): void
    {
        if (!$this->isProduction) {
            return;
        }
        @unlink($this->getCacheFile());
        GeneralUtility::makeInstance(OpcodeCacheService::class)->clearAllActive();
    }

    private function getCacheFile(): string
    {
        return getenv('TYPO3_PATH_APP') . '/var/cache/code/cache_core' . sprintf(CachedConfigurationLoader::CACHE_FILE_PATTERN, $this->getCacheIdentifier());
    }

    private function buildLoader(): ConfigurationLoader
    {
        $configFile = $this->alternativeSettingsFile ?? SettingsFiles::getSettingsFile($this->isProduction);

        return new ConfigurationLoader(
            [
                new Typo3Config($configFile),
            ],
            [
                new PlaceholderValue(false),
                new ExtensionSettingsSerializer(),
            ]
        );
    }

    private function getCacheIdentifier(): string
    {
        return 'production';
    }
}
