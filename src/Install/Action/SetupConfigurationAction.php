<?php
declare(strict_types=1);
namespace Helhum\TYPO3\ConfigHandling\Install\Action;

/***************************************************************
 *  Copyright notice
 *
 *  (c) 2018 Helmut Hummel <info@helhum.io>
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

use Helhum\ConfigLoader\Config;
use Helhum\ConfigLoader\ConfigurationReaderFactory;
use Helhum\TYPO3\ConfigHandling\ConfigDumper;
use Helhum\TYPO3\ConfigHandling\SettingsFiles;
use Helhum\Typo3Console\Install\Action\InstallActionInterface;
use Helhum\Typo3Console\Install\Action\InteractiveActionArguments;
use Helhum\Typo3Console\Mvc\Cli\CommandDispatcher;
use Helhum\Typo3Console\Mvc\Cli\ConsoleOutput;

class SetupConfigurationAction implements InstallActionInterface
{
    /**
     * @var ConfigDumper
     */
    private $configDumper;

    /**
     * @var ConsoleOutput
     */
    private $output;

    /**
     * @var CommandDispatcher
     */
    private $commandDispatcher;

    public function __construct(ConfigDumper $configDumper = null)
    {
        $this->configDumper = $configDumper ?? new ConfigDumper();
    }

    public function setOutput(ConsoleOutput $output)
    {
        $this->output = $output;
    }

    public function setCommandDispatcher(CommandDispatcher $commandDispatcher = null)
    {
        $this->commandDispatcher = $commandDispatcher;
    }

    public function shouldExecute(array $actionDefinition, array $options = []): bool
    {
        return true;
    }

    public function execute(array $actionDefinition, array $options = []): bool
    {
        $this->populateCustomSettings($actionDefinition, $options);
        $this->storeCustomSettingsOverrides($actionDefinition);
        $this->copyEnvDistFile();

        return true;
    }

    private function populateCustomSettings(array $actionDefinition, array $options)
    {
        $customSettingsDefinition = $actionDefinition['customSettings'] ?? [];

        $customConfig = $customSettingsDefinition['defaults'] ?? [];
        $argumentDefinitions = $customSettingsDefinition['arguments'] ?? [];
        $interactiveArguments = new InteractiveActionArguments($this->output);
        $arguments = $interactiveArguments->populate($argumentDefinitions, $options);
        foreach ($arguments as $argumentName => $argumentValue) {
            $customConfig = Config::setValue($customConfig, $argumentDefinitions[$argumentName]['configPath'], $argumentValue);
        }
        $this->addValuesToOverrides($customConfig);
    }

    private function storeCustomSettingsOverrides(array $actionDefinition)
    {
        $customOverrideSettingsFile = $actionDefinition['customOverrideSettings'] ?? '';
        if (!empty($customOverrideSettingsFile)) {
            $factory = new ConfigurationReaderFactory(dirname(SettingsFiles::getInstallStepsFile()));
            $this->addValuesToOverrides($factory->createReader($customOverrideSettingsFile)->readConfig());
        }
    }

    private function addValuesToOverrides(array $values)
    {
        $configFile = SettingsFiles::getOverrideSettingsFile();
        $currentConfig = (new ConfigurationReaderFactory(dirname($configFile)))->createReader($configFile)->readConfig();
        $this->configDumper->dumpToFile(array_replace_recursive($currentConfig, $values), $configFile);
    }

    private function copyEnvDistFile()
    {
        $envFile = getenv('TYPO3_PATH_COMPOSER_ROOT') . '/.env';
        $envDistFile = getenv('TYPO3_PATH_COMPOSER_ROOT') . '/.env.dist';
        if (!file_exists($envFile) && file_exists($envDistFile)) {
            copy($envDistFile, $envFile);
        }
    }
}
