<?php
declare(strict_types=1);
namespace Helhum\TYPO3\ConfigHandling\ConfigReader;

use Helhum\ConfigLoader\ConfigurationReaderFactory;
use Helhum\ConfigLoader\InvalidArgumentException;
use Helhum\ConfigLoader\InvalidConfigurationFileException;
use Helhum\ConfigLoader\Processor\PlaceholderValue;
use TYPO3\CMS\Core\Configuration\Loader\Exception\YamlParseException;
use TYPO3\CMS\Core\Configuration\Loader\YamlFileLoader;
use TYPO3\CMS\Core\Core\Environment;

final readonly class SiteYamlFileLoader extends YamlFileLoader
{
    public function __construct(private YamlFileLoader $yamlFileLoader)
    {
    }

    public function load(string $fileName, int $flags = self::PROCESS_PLACEHOLDERS | self::PROCESS_IMPORTS): array
    {
        if (basename($fileName) !== 'config.yaml') {
            return $this->yamlFileLoader->load($fileName, $flags);
        }
        $factory = new ConfigurationReaderFactory(Environment::getConfigPath());
        try {
            $configuration = $factory->createRootReader($fileName)->readConfig();
        } catch (InvalidArgumentException|InvalidConfigurationFileException $exception) {
            throw new YamlParseException($exception->getMessage(), 1795690800, $exception);
        }
        if (($flags & self::PROCESS_PLACEHOLDERS) === 0) {
            return $configuration;
        }
        $siteIdentifier = basename(dirname($fileName));

        return (new PlaceholderValue(false))->processConfig(array_replace_recursive(
            $configuration,
            $GLOBALS['TYPO3_CONF_VARS']['Site'][$siteIdentifier] ?? [],
        ));
    }
}
