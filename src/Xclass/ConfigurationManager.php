<?php
declare(strict_types=1);
namespace Helhum\TYPO3\ConfigHandling\Xclass;

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

/*
 * This file is part of the TYPO3 CMS project.
 *
 * It is free software; you can redistribute it and/or modify it under
 * the terms of the GNU General Public License, either version 2
 * of the License, or any later version.
 *
 * For the full copyright and license information, please read the
 * LICENSE.txt file that was distributed with this source code.
 *
 * The TYPO3 project - inspiring people to share!
 */

use Composer\InstalledVersions;
use Helhum\ConfigLoader\Config;
use Helhum\ConfigLoader\PathDoesNotExistException;
use Helhum\TYPO3\ConfigHandling\ConfigCleaner;
use Helhum\TYPO3\ConfigHandling\ConfigDumper;
use Helhum\TYPO3\ConfigHandling\ConfigLoader;
use Helhum\TYPO3\ConfigHandling\Processor\RemoveSettingsProcessor;
use Helhum\TYPO3\ConfigHandling\SettingsFiles;
use TYPO3\CMS\Core\Core\Environment;
use TYPO3\CMS\Core\Crypto\Random;
use TYPO3\CMS\Core\Information\Typo3Version;
use TYPO3\CMS\Core\Utility\ArrayUtility;
use TYPO3\CMS\Core\Utility\GeneralUtility;

/**
 * Handle loading and writing of global and local (instance specific)
 * configuration.
 *
 * This class handles the access to the files
 * - EXT:core/Configuration/DefaultConfiguration.php (default TYPO3_CONF_VARS)
 * - config/system/settings.php or typo3conf/system/settings.php - previously known as LocalConfiguration.php
 * - config/system/additional.php or typo3conf/system/additional.php (optional additional code blocks) - previously known as typo3conf/AdditionalConfiguration.php
 *
 * IMPORTANT:
 *   This class is intended for internal core use ONLY.
 *   Extensions should usually use the resulting $GLOBALS['TYPO3_CONF_VARS'] array,
 *   do not try to modify settings in the config/system/settings.php file with an extension.
 * @internal
 */
class ConfigurationManager
{
    private readonly ConfigLoader $configLoader;

    private readonly ConfigDumper $configDumper;

    private readonly ConfigCleaner $configCleaner;

    /**
     * @var array
     */
    private $mainConfig;

    /**
     * @var array
     */
    private $defaultConfig;

    /**
     * @var string Path to default TYPO3_CONF_VARS file, relative to the public web folder
     */
    protected $defaultConfigurationFile = '/Configuration/DefaultConfiguration.php';

    /**
     * @var string Path to description file for TYPO3_CONF_VARS, relative to the public web folder
     */
    protected $defaultConfigurationDescriptionFile = 'EXT:core/Configuration/DefaultConfigurationDescription.yaml';

    /**
     * @var string Path to local overload TYPO3_CONF_VARS file, relative to the public web folder
     */
    protected $localConfigurationFile = 'LocalConfiguration.php';

    /**
     * @var string Path to additional local file, relative to the public web folder
     */
    protected $additionalConfigurationFile = 'AdditionalConfiguration.php';

    /**
     * @var string Path to factory configuration file used during installation as LocalConfiguration boilerplate
     */
    protected $factoryConfigurationFile = '/Configuration/FactoryConfiguration.php';

    /**
     * @var string Path to possible additional factory configuration file delivered by packages
     */
    protected $additionalFactoryConfigurationFile = 'AdditionalFactoryConfiguration.php';

    public readonly string $systemConfigurationFileLocation;

    /**
     * Writing to these configuration paths is always allowed,
     * even if the requested sub path does not exist yet.
     *
     * @var array
     */
    protected array $allowedSettingsPaths = [
        'EXTCONF',
        'DB',
        'SYS/caching/cacheConfigurations',
        'SYS/session',
        'EXTENSIONS',
    ];

    public function __construct(
        ?ConfigLoader $configLoader = null,
        ?ConfigDumper $configDumper = null,
        ?ConfigCleaner $configCleaner = null
    ) {
        $this->configLoader = $configLoader ?? new ConfigLoader(Environment::getContext()->isProduction());
        $this->configDumper = $configDumper ?? new ConfigDumper();
        $this->configCleaner = $configCleaner ?? new ConfigCleaner();
        $this->systemConfigurationFileLocation = (new Typo3Version())->getMajorVersion() > 11 ? $this->getSystemConfigurationFileLocation() : $this->getLocalConfigurationFileLocation();
    }

    /**
     * Return default configuration array
     *
     * @return array
     */
    public function getDefaultConfiguration()
    {
        if (!$this->defaultConfig) {
            $this->defaultConfig = require $this->getDefaultConfigurationFileLocation();
        }

        return $this->defaultConfig;
    }

    /**
     * Get the file location of the default configuration file,
     * currently the path and filename.
     *
     * @return string
     *
     * @internal
     */
    public function getDefaultConfigurationFileLocation(): string
    {
        return InstalledVersions::getInstallPath('typo3/cms-core') . $this->defaultConfigurationFile;
    }

    /**
     * Get the file location of the default configuration description file,
     * currently the path and filename.
     *
     * @return string
     *
     * @internal
     */
    public function getDefaultConfigurationDescriptionFileLocation()
    {
        return $this->defaultConfigurationDescriptionFile;
    }

    /**
     * Return configuration array of typo3conf/system/settings.php or config/system/settings.php, falls back
     * to typo3conf/LocalConfiguration.php
     *
     * @return array Content array of local configuration file
     */
    public function getLocalConfiguration(): array
    {
        return $this->configLoader->loadOwn();
    }

    /**
     * Get the file location of the local configuration file,
     * currently the path and filename.
     *
     * @return string
     *
     * @internal
     */
    public function getLocalConfigurationFileLocation(): string
    {
        return Environment::getLegacyConfigPath() . '/' . $this->localConfigurationFile;
    }

    /**
     * Get the file location of the TYPO3-project specific settings file,
     * currently the path and filename.
     *
     * Path to local overload TYPO3_CONF_VARS file.
     *
     * @internal
     */
    public function getSystemConfigurationFileLocation(bool $relativeToProjectRoot = false): string
    {
        // For composer-based installations, the file is in config/system/settings.php
        if (Environment::getProjectPath() !== Environment::getPublicPath()) {
            $path = Environment::getConfigPath() . '/system/settings.php';
        } else {
            $path = Environment::getLegacyConfigPath() . '/system/settings.php';
        }
        if ($relativeToProjectRoot) {
            return substr($path, strlen(Environment::getProjectPath()) + 1);
        }
        return $path;
    }

    /**
     * Returns local configuration array merged with default configuration
     *
     * @return array
     */
    public function getMergedLocalConfiguration(): array
    {
        if ($this->mainConfig === null) {
            $this->mainConfig = $this->configLoader->load();
        }

        return $this->mainConfig;
    }

    /**
     * Get the file location of the additional configuration file,
     * currently the path and filename.
     *
     * @return string
     *
     * @internal
     */
    public function getAdditionalConfigurationFileLocation(): string
    {
        return Environment::getLegacyConfigPath() . '/' . $this->additionalConfigurationFile;
    }

    /**
     * Get absolute file location of factory configuration file
     *
     * @return string
     */
    protected function getFactoryConfigurationFileLocation(): string
    {
        return InstalledVersions::getInstallPath('typo3/cms-core') . $this->factoryConfigurationFile;
    }

    /**
     * Get absolute file location of factory configuration file
     *
     * @return string
     */
    protected function getAdditionalFactoryConfigurationFileLocation(): string
    {
        return Environment::getLegacyConfigPath() . '/' . $this->additionalFactoryConfigurationFile;
    }

    /**
     * Override local configuration with new values.
     *
     * @param array $configurationToMerge Override configuration array
     */
    public function updateLocalConfiguration(array $configurationToMerge): void
    {
        // We take care exposing the legacy extension config ourselves
        unset($configurationToMerge['EXT']['extConf']);
        if (empty($configurationToMerge)) {
            return;
        }
        $overrideSettingsFile = SettingsFiles::getOverrideSettingsFile();
        if (!$this->canWriteConfiguration()) {
            throw new \RuntimeException(
                $overrideSettingsFile . ' is not writable.',
                1346323822
            );
        }
        $removedPaths = $this->getRemovedPaths();
        foreach ($removedPaths as $removedPath) {
            try {
                Config::getValue($configurationToMerge, $removedPath);
                $addedPaths[] = $removedPath;
            } catch (PathDoesNotExistException) {
                continue;
            }
        }
        if (!empty($addedPaths)) {
            $remainingPaths = array_diff($removedPaths, $addedPaths);
            $this->updateRemovalPaths($remainingPaths);
        }
        $remainingConfigToWrite = $this->configCleaner->cleanConfig(
            $configurationToMerge,
            $this->getLocalConfiguration()
        );
        if (!empty($remainingConfigToWrite)) {
            $this->configDumper->dumpToFile(array_replace_recursive($this->configLoader->loadOverrides(), $remainingConfigToWrite), $overrideSettingsFile);
            $this->configLoader->flushCache();
        }
    }

    private const REMOVE_PROCESSOR_KEY = '_stale_options';

    private function getRemovedPaths(): array
    {
        return $this->configLoader->loadOverrides()['processors'][self::REMOVE_PROCESSOR_KEY]['paths'] ?? [];
    }

    private function updateRemovalPaths(array $pathsToRemove): void
    {
        $overrideSettingsFile = SettingsFiles::getOverrideSettingsFile();
        $overrides = $this->configLoader->loadOverrides();
        $overrides['processors'][self::REMOVE_PROCESSOR_KEY] = [
            'class' => RemoveSettingsProcessor::class,
            'paths' => $pathsToRemove,
        ];
        if (empty($pathsToRemove)) {
            unset($overrides['processors'][self::REMOVE_PROCESSOR_KEY]);
            if (empty($overrides['processors'])) {
                unset($overrides['processors']);
            }
        }
        $this->configDumper->dumpToFile($overrides, $overrideSettingsFile);
    }

    /**
     * Get a value at given path from default configuration
     *
     * @param string $path Path to search for
     *
     * @return mixed Value at path
     */
    public function getDefaultConfigurationValueByPath(array|string $path): mixed
    {
        return ArrayUtility::getValueByPath($this->getDefaultConfiguration(), $path);
    }

    /**
     * Get a value at given path from local configuration
     *
     * @param string $path Path to search for
     *
     * @return mixed Value at path
     */
    public function getLocalConfigurationValueByPath(array|string $path): mixed
    {
        return ArrayUtility::getValueByPath($this->getLocalConfiguration(), $path);
    }

    /**
     * Get a value from configuration, this is default configuration
     * merged with local configuration
     *
     * @param string $path Path to search for
     *
     * @return mixed
     */
    public function getConfigurationValueByPath(array|string $path): mixed
    {
        return ArrayUtility::getValueByPath($this->getMergedLocalConfiguration(), $path);
    }

    /**
     * Update a given path in local configuration to a new value.
     * Warning: TO BE USED ONLY to update a single feature.
     * NOT TO BE USED within iterations to update multiple features.
     * To update multiple features use setLocalConfigurationValuesByPathValuePairs().
     *
     * @param string $path Path to update
     * @param mixed $value Value to set
     *
     * @return bool TRUE on success
     */
    public function setLocalConfigurationValueByPath($path, $value)
    {
        return $this->setLocalConfigurationValuesByPathValuePairs([$path => $value]);
    }

    /**
     * Update / set a list of path and value pairs in local configuration file
     *
     * @param array $pairs Key is path, value is value to set
     *
     * @return bool TRUE on success
     */
    public function setLocalConfigurationValuesByPathValuePairs(array $pairs): bool
    {
        $localConfiguration = [];
        foreach ($pairs as $path => $value) {
            if ($this->isValidLocalConfigurationPath($path)) {
                $localConfiguration = ArrayUtility::setValueByPath($localConfiguration, $path, $value);
            }
        }
        $this->updateLocalConfiguration($localConfiguration);

        return true;
    }

    /**
     * Remove keys from LocalConfiguration
     *
     * @param array $keys Array with key paths to remove from LocalConfiguration
     *
     * @return bool TRUE if something was removed
     */
    public function removeLocalConfigurationKeysByPath(array $keys): bool
    {
        $result = false;
        $localConfiguration = $this->getLocalConfiguration();
        $removedPaths = [];
        foreach ($keys as $path) {
            // Remove key if path is within LocalConfiguration
            if (ArrayUtility::isValidPath($localConfiguration, $path)) {
                $result = true;
                $pathParts = str_getcsv($path, '/', escape: '\\');
                $removedPaths[] = sprintf('"%s"', implode('"."', $pathParts));
            }
        }
        if (!empty($removedPaths)) {
            $alreadyRemovedPaths = $this->getRemovedPaths();
            $removedPaths = array_unique(array_merge($alreadyRemovedPaths, $removedPaths));
            $this->updateRemovalPaths($removedPaths);
        }

        return $result;
    }

    /**
     * Enables a certain feature and writes the option to LocalConfiguration.php
     * Short-hand method
     * Warning: TO BE USED ONLY to enable a single feature.
     * NOT TO BE USED within iterations to enable multiple features.
     * To update multiple features use setLocalConfigurationValuesByPathValuePairs().
     *
     * @param string $featureName something like "InlineSvgImages"
     *
     * @return bool true on successful writing the setting
     */
    public function enableFeature(string $featureName): bool
    {
        return $this->setLocalConfigurationValueByPath('SYS/features/' . $featureName, true);
    }

    /**
     * Disables a feature and writes the option to system/settings.php
     * Short-hand method
     * Warning: TO BE USED ONLY to disable a single feature.
     * NOT TO BE USED within iterations to disable multiple features.
     * To update multiple features use setLocalConfigurationValuesByPathValuePairs().
     *
     * @param string $featureName something like "InlineSvgImages"
     *
     * @return bool true on successful writing the setting
     */
    public function disableFeature(string $featureName): bool
    {
        return $this->setLocalConfigurationValueByPath('SYS/features/' . $featureName, false);
    }

    /**
     * Checks if the configuration can be written.
     *
     * @return bool
     *
     * @internal
     */
    public function canWriteConfiguration()
    {
        $fileLocation = SettingsFiles::getOverrideSettingsFile();

        return @is_writable(file_exists($fileLocation) ? $fileLocation : Environment::getConfigPath() . '/');
    }

    /**
     * Reads the configuration array and exports it to the global variable
     *
     * @internal
     *
     * @throws \UnexpectedValueException
     */
    public function exportConfiguration(): void
    {
        $this->configLoader->populate();
        $this->mainConfig = null;
    }

    /**
     * Write configuration array to %config-dir%/system/settings.php
     *
     * @param array $configuration The local configuration to be written
     *
     * @throws \RuntimeException
     *
     * @return bool TRUE on success
     *
     * @internal
     */
    public function writeLocalConfiguration(array $configuration): bool
    {
        $configuration = $this->configCleaner->cleanConfig($configuration, $this->getLocalConfiguration());
        $this->updateLocalConfiguration($configuration);

        // Too many places require this file to exist, so we make sure to create it
        return $this->configDumper->dumpToFile([], $this->systemConfigurationFileLocation, "Auto generated by helhum/typo3-config-handling\nDo not edit this file");
    }

    /**
     * Write additional configuration array to config/system/additional.php / typo3conf/system/additional.php
     *
     * @param array $additionalConfigurationLines The configuration lines to be written
     *
     * @throws \RuntimeException
     *
     * @return bool TRUE on success
     *
     * @internal
     */
    public function writeAdditionalConfiguration(array $additionalConfigurationLines): bool
    {
        return GeneralUtility::writeFile(
            $this->getAdditionalConfigurationFileLocation(),
            "<?php\n" . implode("\n", $additionalConfigurationLines) . "\n"
        );
    }

    /**
     * Uses FactoryConfiguration file and a possible AdditionalFactoryConfiguration
     * file in typo3conf to create a basic config/system/settings.php. This is used
     * by the installer in an early step.
     *
     * @throws \RuntimeException
     * @internal
     */
    public function createLocalConfigurationFromFactoryConfiguration(): void
    {
        if (file_exists($this->getLocalConfigurationFileLocation())) {
            throw new \RuntimeException(
                basename($this->getSystemConfigurationFileLocation(true)) . ' already exists',
                1364836026
            );
        }
        $localConfigurationArray = require $this->getFactoryConfigurationFileLocation();
        $additionalFactoryConfigurationFileLocation = $this->getAdditionalFactoryConfigurationFileLocation();
        if (file_exists($additionalFactoryConfigurationFileLocation)) {
            $additionalFactoryConfigurationArray = require $additionalFactoryConfigurationFileLocation;
            ArrayUtility::mergeRecursiveWithOverrule(
                $localConfigurationArray,
                $additionalFactoryConfigurationArray
            );
        }
        $randomKey = GeneralUtility::makeInstance(Random::class)->generateRandomHexString(96);
        $localConfigurationArray['SYS']['encryptionKey'] = $randomKey;

        $this->writeLocalConfiguration($localConfigurationArray);
    }

    /**
     * Check if access / write to given path in local configuration is allowed.
     *
     * @param string $path Path to search for
     * @return bool TRUE if access is allowed
     */
    protected function isValidLocalConfigurationPath($path)
    {
        // Early return for white listed paths
        foreach ($this->allowedSettingsPaths as $allowedSettingsPath) {
            if (str_starts_with($path, (string) $allowedSettingsPath)) {
                return true;
            }
        }

        return ArrayUtility::isValidPath($this->getDefaultConfiguration(), $path);
    }
}
