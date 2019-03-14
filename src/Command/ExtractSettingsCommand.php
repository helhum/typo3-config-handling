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

use Helhum\ConfigLoader\Config;
use Helhum\ConfigLoader\PathDoesNotExistException;
use Helhum\TYPO3\ConfigHandling\ConfigExtractor;
use Helhum\TYPO3\ConfigHandling\ConfigLoader;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use TYPO3\CMS\Core\Configuration\ConfigurationManager;

class ExtractSettingsCommand extends Command
{
    /**
     * @var ConfigurationManager
     */
    private $configurationManager;

    /**
     * @var ConfigExtractor
     */
    private $configExtractor;

    public function __construct(
        $name = null,
        ConfigurationManager $configurationManager = null,
        ConfigExtractor $configExtractor = null
    ) {
        parent::__construct($name);
        $this->configurationManager = $configurationManager ?: new ConfigurationManager();
        $this->configExtractor = $configExtractor ?: new ConfigExtractor();
    }

    protected function configure()
    {
        $this->setDefinition(
            [
                new InputOption(
                    'ignore-path',
                    '-i',
                    InputOption::VALUE_IS_ARRAY | InputOption::VALUE_REQUIRED,
                    'Ignore config path, when extracting config from LocalConfiguration.php',
                    []
                ),
                new InputOption(
                    'config-file',
                    '-c',
                    InputOption::VALUE_REQUIRED,
                    'Ignore config path, when extracting config from LocalConfiguration.php'
                ),
            ]
        )
        ->setDescription('Extract values from LocalConfiguration.php to Yaml configuration')
        ->setHelp('Values from a previously existing LocalConfiguration.php are extracted and written to Yaml config files.');
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $localConfigurationFile = $this->configurationManager->getLocalConfigurationFileLocation();
        if ($this->isAutoGenerated($localConfigurationFile)) {
            $output->writeln('<info>LocalConfiguration.php does not exist or is auto generated. Nothing to extract.</info>');

            return;
        }
        $configuration = require $localConfigurationFile;

        foreach ($input->getOption('ignore-path') as $path) {
            try {
                $configuration = Config::removeValue($configuration, $path);
            } catch (PathDoesNotExistException $e) {
                // We ignore it, when the path does not exist
            }
        }

        $this->configExtractor->extractConfig(
            $configuration,
            $this->configurationManager->getMergedLocalConfiguration(),
            $input->getOption('config-file')
        );
        $configLoader = new ConfigLoader(true);
        $configLoader->flushCache();
    }

    private function isAutoGenerated(string $file): bool
    {
        if (!file_exists($file)) {
            return false;
        }

        return strpos(file_get_contents($file), 'Auto generated by helhum/typo3-config-handling') !== false;
    }
}
