<?php
declare(strict_types=1);
namespace Helhum\TYPO3\ConfigHandling\Processor;

use Helhum\ConfigLoader\Config;
use Helhum\ConfigLoader\InvalidArgumentException;
use Helhum\ConfigLoader\PathDoesNotExistException;
use Helhum\ConfigLoader\Processor\ConfigProcessorInterface;

class RemoveSettingsProcessor implements ConfigProcessorInterface
{
    public function __construct(
        private array $options
    ) {}

    /**
     * @param array $config
     *
     * @throws InvalidArgumentException
     *
     * @return array
     */
    public function processConfig(array $config): array
    {
        foreach ($this->options['paths'] ?? [] as $path) {
            try {
                $config = Config::removeValue($config, $path);
            } catch (PathDoesNotExistException) {
                // gracefully ignore not existing paths
            }
        }

        return $config;
    }
}
