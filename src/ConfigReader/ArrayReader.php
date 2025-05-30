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

use Helhum\ConfigLoader\Config;
use Helhum\ConfigLoader\PathDoesNotExistException;
use Helhum\ConfigLoader\Reader\ConfigReaderInterface;

class ArrayReader implements ConfigReaderInterface
{
    public function __construct(
        private readonly array $config,
        private readonly ?string $configPath = null
    ) {}

    public function hasConfig(): bool
    {
        if ($this->configPath) {
            try {
                Config::getValue($this->config, $this->configPath, []);

                return true;
            } catch (PathDoesNotExistException) {
                return false;
            }
        }

        return true;
    }

    public function readConfig(): array
    {
        if ($this->configPath) {
            $config = Config::getValue($this->config, $this->configPath, []);
            if (!\is_array($config)) {
                throw new \RuntimeException(sprintf('Config for path "%s" is not an array', $this->configPath), 1527373063);
            }

            return Config::getValue($this->config, $this->configPath, []);
        }

        return $this->config;
    }
}
