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

use Composer\InstalledVersions;
use Helhum\ConfigLoader\Reader\ConfigReaderInterface;

class Typo3BaseConfigReader implements ConfigReaderInterface
{
    private readonly string $resource;

    public function __construct(string $configName = 'DefaultConfiguration')
    {
        $configName = preg_replace('/[^a-zA-Z]/', '', $configName);
        $this->resource = sprintf(InstalledVersions::getInstallPath('typo3/cms-core') . '/Configuration/%s.php', $configName);
    }

    public function hasConfig(): bool
    {
        return file_exists($this->resource);
    }

    public function readConfig(): array
    {
        return require $this->resource;
    }
}
