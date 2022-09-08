<?php
declare(strict_types=1);
namespace Helhum\TYPO3\ConfigHandling\Processor;

use Helhum\ConfigLoader\Processor\ConfigProcessorInterface;
use Helhum\ConfigLoader\Processor\Placeholder\PlaceholderCollection;
use Helhum\ConfigLoader\Processor\PlaceholderValue;
use Helhum\TYPO3\ConfigHandling\Processor\Placeholder\DecryptPlaceholder;

class DecryptSettingsProcessor implements ConfigProcessorInterface
{
    /**
     * @param array $config
     *
     * @throws \InvalidArgumentException
     *
     * @return array
     */
    public function processConfig(array $config): array
    {
        $secret = $config['SYS']['settingsEncryptionKey'] ?? '';
        unset($config['SYS']['settingsEncryptionKey']);
        if (empty($secret)) {
            return $config;
        }
        $placeholderValue = new PlaceholderValue(
            false,
            new PlaceholderCollection(
                [
                    new DecryptPlaceholder($secret),
                ]
            )
        );

        return $placeholderValue->processConfig($config);
    }
}
