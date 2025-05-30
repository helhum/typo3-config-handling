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
use Helhum\ConfigLoader\InvalidConfigurationFileException;
use Helhum\ConfigLoader\Processor\PlaceholderValue;
use Helhum\TYPO3\ConfigHandling\Processor\ExtensionSettingsSerializer;
use Helhum\Typo3Console\Mvc\Cli\Symfony\Input\ArgvInput;
use TYPO3\CMS\Core\Core\Environment;
use TYPO3\CMS\Core\Service\OpcodeCacheService;
use TYPO3\CMS\Core\Utility\GeneralUtility;

class ConfigLoader
{
    private readonly string $settingsFile;

    public function __construct(
        private readonly bool $isProduction,
        ?string $settingsFile = null
    ) {
        $this->settingsFile = $settingsFile ?? SettingsFiles::getSettingsFile($this->isProduction);
    }

    public function populate(): void
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

    /**
     * Complete config
     * Cached in production
     *
     * @throws InvalidConfigurationFileException
     *
     * @return array
     */
    public function load(): array
    {
        return $this->buildLoader()->load();
    }

    /**
     * Complete config, but without overrides config
     *
     * @return array
     */
    public function loadBase(): array
    {
        return (new Typo3Config($this->settingsFile))->readBaseConfig();
    }

    /**
     * Config with overrides file, but without TYPO3 defaults
     *
     * @return array
     */
    public function loadOwn(): array
    {
        return (new Typo3Config($this->settingsFile))->readOwnConfig();
    }

    /**
     * Config from overrides file
     *
     * @return array
     */
    public function loadOverrides(): array
    {
        return (new Typo3Config($this->settingsFile))->readOverridesConfig();
    }

    public function flushCache(): void
    {
        if (!$this->shouldCache()) {
            return;
        }
        $cacheFilePattern = str_replace($this->getCacheIdentifier(), '*', $this->getCacheFile());
        foreach (glob($cacheFilePattern) as $cacheFile) {
            @unlink($cacheFile);
        }
        GeneralUtility::makeInstance(OpcodeCacheService::class)->clearAllActive();
    }

    private function getCacheFile(): string
    {
        return getenv('TYPO3_PATH_APP') . '/var/cache/code/core' . sprintf(CachedConfigurationLoader::CACHE_FILE_PATTERN, $this->getCacheIdentifier());
    }

    private function buildLoader(): ConfigurationLoader
    {
        return new ConfigurationLoader(
            [
                new Typo3Config($this->settingsFile),
            ],
            [
                new PlaceholderValue(false),
                new ExtensionSettingsSerializer(),
            ]
        );
    }

    private function getCacheIdentifier(): string
    {
        return sha1(Environment::getContext() . filemtime(Environment::getConfigPath()));
    }
}
