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

use Helhum\Typo3Console\Install\Action\InstallActionInterface;
use Helhum\Typo3Console\Mvc\Cli\CommandDispatcher;
use Helhum\Typo3Console\Mvc\Cli\ConsoleOutput;
use Symfony\Component\Yaml\Yaml;

class FixupSiteConfigAction implements InstallActionInterface
{
    public function setOutput(ConsoleOutput $output): void
    {
        $this->output = $output;
    }

    public function setCommandDispatcher(?CommandDispatcher $commandDispatcher = null): void
    {
        $this->commandDispatcher = $commandDispatcher;
    }

    public function shouldExecute(array $actionDefinition, array $options = []): bool
    {
        return true;
    }

    public function execute(array $actionDefinition, array $options = []): bool
    {
        $siteConfigFile = getenv('TYPO3_PATH_APP') . '/config/sites/main/config.yaml';
        if (!file_exists($siteConfigFile)) {
            return true;
        }
        $siteConfig = Yaml::parse(file_get_contents($siteConfigFile));
        $siteConfig['base'] = '/';
        file_put_contents($siteConfigFile, Yaml::dump($siteConfig, 99));

        return true;
    }
}
