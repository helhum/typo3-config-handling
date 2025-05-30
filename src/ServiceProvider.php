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

use Helhum\TYPO3\ConfigHandling\Command\EncryptSettingsCommand;
use Helhum\TYPO3\ConfigHandling\Command\ExtractSettingsCommand;
use Psr\Container\ContainerInterface;
use TYPO3\CMS\Core\Console\CommandRegistry;
use TYPO3\CMS\Core\Package\AbstractServiceProvider;

class ServiceProvider extends AbstractServiceProvider
{
    protected static function getPackagePath(): string
    {
        return __DIR__ . '/..';
    }

    public function getFactories(): array
    {
        return [
            ExtractSettingsCommand::class => static::getExtractSettingsCommand(...),
            EncryptSettingsCommand::class => static::getEncryptSettingsCommand(...),
        ];
    }

    #[\Override]
    public function getExtensions(): array
    {
        return [
                CommandRegistry::class => static::configureCommands(...),
            ] + parent::getExtensions();
    }

    protected static function getPackageName(): string
    {
        return 'helhum/typo3-config-handling';
    }

    public static function getExtractSettingsCommand(): ExtractSettingsCommand
    {
        return new ExtractSettingsCommand('settings:extract');
    }

    public static function getEncryptSettingsCommand(): EncryptSettingsCommand
    {
        return new EncryptSettingsCommand('settings:encrypt');
    }

    public static function configureCommands(ContainerInterface $container, CommandRegistry $commandRegistry): CommandRegistry
    {
        $commandRegistry->addLazyCommand('settings:extract', ExtractSettingsCommand::class, 'Extract values from LocalConfiguration.php to Yaml configuration');
        $commandRegistry->addLazyCommand('settings:encrypt', EncryptSettingsCommand::class, 'Encrypts values in a given config file');

        return $commandRegistry;
    }

}
