<?php
declare(strict_types=1);
namespace Helhum\TYPO3\ConfigHandling\Command;

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

use Helhum\TYPO3\ConfigHandling\Typo3SettingsDumper;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class SettingsDumpCommand extends Command
{
    /**
     * @var Typo3SettingsDumper
     */
    private $settingsDumper;

    public function __construct($name = null, Typo3SettingsDumper $settingsDumper = null)
    {
        parent::__construct($name);
        $this->settingsDumper = $settingsDumper ?: new Typo3SettingsDumper();
    }

    protected function configure()
    {
        $this->setDefinition(
            [
                new InputOption('--no-dev', null, InputOption::VALUE_NONE, 'When set, only LocalConfiguration.php is written to contain the merged configuration ready for production'),
                new InputOption('--strict', null, InputOption::VALUE_NONE, 'When set, an exception is thrown when accessing not existing env vars in configuration (ignored with --code)'),
                new InputOption('--cached', null, InputOption::VALUE_NONE, 'When set, parsed config will be cached in a PHP file (ignored with --code or --no-dev)'),
                new InputOption('--code', null, InputOption::VALUE_NONE, 'When set, PHP code is generated for placeholders, e.g. to access environment variables (implies --no-dev)'),
            ]
        )
        ->setDescription('Dump a (static) LocalConfiguration.php file')
        ->setHelp('The values are complied to respect all settings managed by the configuration loader.');
    }

    /**
     * Dump a (static) LocalConfiguration.php file
     *
     * The values are complied to respect all settings managed by the configuration loader.
     *
     * @throws \RuntimeException
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $this->settingsDumper->dump(
            $input->getOption('no-dev'),
            $input->getOption('code'),
            $input->getOption('strict'),
            $input->getOption('cached')
        );
    }
}
