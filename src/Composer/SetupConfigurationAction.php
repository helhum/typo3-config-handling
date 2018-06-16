<?php
declare(strict_types=1);
namespace Helhum\TYPO3\ConfigHandling\Composer;

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
use Helhum\TYPO3\ConfigHandling\ConfigDumper;
use Helhum\TYPO3\ConfigHandling\RootConfig;
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
        $configFile = RootConfig::getLocalConfigFile();
        $customSettingsDefinition = $actionDefinition['customSettings'] ?? [];

        $customConfig = $customSettingsDefinition['defaults'] ?? [];
        $argumentDefinitions = $customSettingsDefinition['arguments'] ?? [];
        $interactiveArguments = new InteractiveActionArguments($this->output);
        $arguments = $interactiveArguments->populate($argumentDefinitions, $options);
        foreach ($arguments as $argumentName => $argumentValue) {
            $customConfig = Config::setValue($customConfig, $argumentDefinitions[$argumentName]['configPath'], $argumentValue);
        }

        $this->configDumper->dumpToFile($customConfig, $configFile);

        if (!empty($actionDefinition['extractConfig'])) {
            $commandArguments = [
                '--config-file',
                $configFile
            ];
            $ignoredPaths = $actionDefinition['extractConfig']['ignorePaths'] ?? [];
            foreach ($ignoredPaths as $ignoredPath) {
                $commandArguments[] = '--ignore-path';
                $commandArguments[] = $ignoredPath;
            }

            $this->commandDispatcher->executeCommand(
                'settings:extract',
                $commandArguments
            );
        }
        $this->commandDispatcher->executeCommand('settings:dump');

        return true;
    }
}
