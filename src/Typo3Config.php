<?php
declare(strict_types=1);
namespace Helhum\TYPO3\ConfigHandling;

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
use Helhum\ConfigLoader\InvalidArgumentException;
use Helhum\ConfigLoader\Reader\CollectionReader;
use Helhum\ConfigLoader\Reader\ConfigReaderInterface;
use Helhum\TYPO3\ConfigHandling\ConfigReader\ArrayReader;
use Helhum\TYPO3\ConfigHandling\ConfigReader\CustomProcessingReader;
use Helhum\TYPO3\ConfigHandling\ConfigReader\Typo3BaseConfigReader;
use Helhum\TYPO3\ConfigHandling\ConfigReader\Typo3DefaultConfigPresenceReader;

class Typo3Config implements ConfigReaderInterface
{
    private readonly Typo3DefaultConfigPresenceReader $baseReader;

    private readonly CustomProcessingReader $reader;

    private readonly CustomProcessingReader $ownConfigReader;

    private readonly ConfigReaderInterface $overridesReader;

    public function __construct(string $configFile, ?ConfigurationReaderFactory $readerFactory = null)
    {
        $readerFactory ??= new ConfigurationReaderFactory(dirname($configFile));
        $readerFactory->setReaderFactoryForType(
            'typo3',
            static fn(string $resource): Typo3BaseConfigReader => new Typo3BaseConfigReader($resource),
            false
        );
        $readerFactory->setReaderFactoryForType(
            'environment',
            static function (string $resource, array $options) use ($readerFactory): ArrayReader|ConfigReaderInterface {
                $environmentName = $_ENV[$resource] ?? $_SERVER[$resource] ?? getenv($resource);
                if (!$environmentName) {
                    return new ArrayReader([]);
                }
                if (!isset($options['match'], $options['map'])) {
                    throw new InvalidArgumentException('match and map needs to be set for this resource', 1661512027);
                }
                $configFile = preg_replace($options['match'], (string) $options['map'], $environmentName);
                if ($configFile === null || $configFile === $environmentName) {
                    return new ArrayReader([]);
                }
                $environmentReader = $readerFactory->createRootReader($configFile);
                if (($options['require_on_match'] ?? true) && !$environmentReader->hasConfig()) {
                    throw new InvalidArgumentException(sprintf('Could not import environment resource "%s" with name "%s" from "%s"', $resource, $environmentName, $options['map']), 1661513227);
                }

                return $environmentReader;
            },
            false
        );
        $this->baseReader = new Typo3DefaultConfigPresenceReader(
            $readerFactory->createRootReader($configFile)
        );
        $this->reader = new CustomProcessingReader(
            new CollectionReader(
                $this->baseReader,
                $readerFactory->createRootReader(SettingsFiles::getEnvironmentSettingsFile()),
                $readerFactory->createRootReader(SettingsFiles::getOverrideSettingsFile())
            )
        );
        $readerFactory->setReaderFactoryForType(
            'typo3',
            fn(): ArrayReader => new ArrayReader([]),
            false
        );
        $this->ownConfigReader = new CustomProcessingReader(
            new CollectionReader(
                $readerFactory->createRootReader($configFile),
                $readerFactory->createRootReader(SettingsFiles::getEnvironmentSettingsFile()),
                $readerFactory->createRootReader(SettingsFiles::getOverrideSettingsFile())
            )
        );
        $this->overridesReader = $readerFactory->createReader(SettingsFiles::getOverrideSettingsFile());
    }

    public function hasConfig(): bool
    {
        return $this->reader->hasConfig();
    }

    /**
     * Complete config
     *
     * @return array
     */
    public function readConfig(): array
    {
        return $this->reader->readConfig();
    }

    /**
     * Complete config, but without overrides config
     *
     * @return array
     */
    public function readBaseConfig(): array
    {
        return $this->baseReader->readConfig();
    }

    /**
     * Config with overrides file, but without TYPO3 defaults
     *
     * @return array
     */
    public function readOwnConfig(): array
    {
        return $this->ownConfigReader->readConfig();
    }

    /**
     * Config of overrides file only
     *
     * @return array
     */
    public function readOverridesConfig(): array
    {
        return $this->overridesReader->hasConfig() ? $this->overridesReader->readConfig() : [];
    }

    public function getValue(string $path)
    {
        return Config::getValue($this->readConfig(), $path);
    }
}
