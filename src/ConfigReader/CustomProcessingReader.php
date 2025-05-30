<?php
declare(strict_types=1);
namespace Helhum\TYPO3\ConfigHandling\ConfigReader;

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

use Helhum\ConfigLoader\ConfigurationLoader;
use Helhum\ConfigLoader\Reader\ConfigReaderInterface;

class CustomProcessingReader implements ConfigReaderInterface
{
    public function __construct(
        private readonly ConfigReaderInterface $baseReader
    ) {}

    public function hasConfig(): bool
    {
        return $this->baseReader->hasConfig();
    }

    public function readConfig(): array
    {
        $mainConfig = $this->baseReader->readConfig();
        $processors = [];
        if (isset($mainConfig['processors'])) {
            $processors = $this->createCustomProcessors($mainConfig['processors']);
            unset($mainConfig['processors']);
        }
        $readers = [new ArrayReader($mainConfig)];

        return (new ConfigurationLoader($readers, $processors))->load();
    }

    private function createCustomProcessors(array $processorsConfig): array
    {
        $processors = [];
        foreach ($processorsConfig as $processorConfig) {
            $processors[] = new $processorConfig['class']($processorConfig);
        }

        return $processors;
    }
}
