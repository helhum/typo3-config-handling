<?php
declare(strict_types=1);
namespace Helhum\TYPO3\ConfigHandling\Command;

use Defuse\Crypto\Key;
use Helhum\ConfigLoader\ConfigurationReaderFactory;
use Helhum\ConfigLoader\Processor\Placeholder\PlaceholderCollection;
use Helhum\ConfigLoader\Processor\PlaceholderValue;
use Helhum\TYPO3\ConfigHandling\ConfigDumper;
use Helhum\TYPO3\ConfigHandling\Processor\Placeholder\EncryptPlaceholder;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

class EncryptSettingsCommand extends Command
{
    protected function configure()
    {
        $this->setDefinition(
            [
                new InputOption(
                    'config-file',
                    '-c',
                    InputOption::VALUE_REQUIRED,
                    'Config file with settings to encrypt'
                ),
                new InputOption(
                    'encryption-key',
                    '-e',
                    InputOption::VALUE_REQUIRED,
                    'Encryption key to use for encryption'
                ),
            ]
        )
        ->setDescription('Encrypts values in a given config file');
    }

    #[\Override]
    public function isEnabled(): bool
    {
        return class_exists(Key::class);
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        if (!$configFile = $input->getOption('config-file')) {
            $io->error('Please specify a config file');

            return self::FAILURE;
        }
        $encryptionKey = $input->getOption('encryption-key');
        if (empty($encryptionKey)) {
            $generateKey = $io->confirm('No encryption key given. Should a new one be generated?', false);
            if (!$generateKey) {
                $io->error('No key given, cannot encrypt config file');

                return self::FAILURE;
            }
            $encryptionKey = Key::createNewRandomKey()->saveToAsciiSafeString();
            $io->warning('Generated new encryption key');
            $io->writeln(sprintf('Key: %s', $encryptionKey));
        }

        $factory = new ConfigurationReaderFactory(getenv('TYPO3_PATH_COMPOSER_ROOT'));
        $placeholderValue = new PlaceholderValue(
            false,
            new PlaceholderCollection(
                [
                    new EncryptPlaceholder($encryptionKey),
                ]
            )
        );
        $reader = $factory->createReader($configFile);
        $originalConfig = $reader->readConfig();
        $encryptedConfig = $placeholderValue->processConfig($originalConfig);

        if ($encryptedConfig === $originalConfig) {
            $io->warning(sprintf('No values found to encrypt in file "%s"', $input->getOption('config-file')));

            return self::FAILURE;
        }
        $configDumper = new ConfigDumper();
        $configDumper->dumpToFile($encryptedConfig, $configFile);

        $io->success(sprintf('Encrypted values in file "%s"', $input->getOption('config-file')));

        return self::SUCCESS;
    }
}
