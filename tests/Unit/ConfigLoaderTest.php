<?php
declare(strict_types=1);
namespace Helhum\TYPO3\ConfigHandling\Tests\Unit;

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

use Helhum\TYPO3\ConfigHandling\ConfigLoader;
use org\bovigo\vfs\vfsStream;
use PHPUnit\Framework\TestCase;
use TYPO3\CMS\Core\DependencyInjection\ContainerBuilder;
use TYPO3\CMS\Core\Resource\Security\FileNameValidator;

class ConfigLoaderTest extends TestCase
{
    protected function setUp(): void
    {
        $defaultConfig = [
            'LOG' => [
                'writer' => 'bla',
            ],
            'SYS' => [
                'lang' => [
                    'format' => [
                        'priority' => 'xlf,xml',
                    ],
                ],
            ],
        ];

        $structure = [
            'typo3' => [
                'sysext' => [
                    'core' => [
                        'Configuration' => [
                            'DefaultConfiguration.php' => '<?php return ' . var_export($defaultConfig, true) . ';',
                        ],
                    ],
                ],
            ],
        ];
        vfsStream::setup('root', null, $structure);
        $root = vfsStream::url('root');
        putenv('TYPO3_PATH_ROOT=' . $root);
        if (!class_exists(FileNameValidator::class) && !defined('FILE_DENY_PATTERN_DEFAULT')) {
            define('FILE_DENY_PATTERN_DEFAULT', '\\.(php[3-8]?|phpsh|phtml|pht|phar|shtml|cgi)(\\..*)?$|\\.pl$|^\\.htaccess$');
        }
    }

    /**
     * @test
     */
    public function notExistingConfigFileReturnsTypo3DefaultConfiguration(): void
    {
        $configLoader = new ConfigLoader(true, '/not/existing.yaml');
        $this->assertArrayHasKey('SYS', $configLoader->load());
    }

    /**
     * @test
     */
    public function notImportedTypo3DefaultConfigStillIncludesTypo3DefaultConfiguration(): void
    {
        $root = __DIR__ . '/Fixtures/config';
        $configLoader = new ConfigLoader(true, $root . '/config.yaml');
        $actualResult = $configLoader->load();
        $this->assertArrayHasKey('SYS', $actualResult);
        $this->assertArrayHasKey('foo', $actualResult);
        $this->assertArrayHasKey('LOG', $actualResult);
    }

    /**
     * @test
     */
    public function importingTypo3DefaultConfigurationRespectsSpecifiedExcludes(): void
    {
        $root = __DIR__ . '/Fixtures/config';
        $configLoader = new ConfigLoader(true, $root . '/import_default.yaml');
        $actualResult = $configLoader->load();
        $this->assertArrayHasKey('SYS', $actualResult);
        $this->assertArrayHasKey('foo', $actualResult);
        $this->assertArrayNotHasKey('LOG', $actualResult);
    }

    /**
     * @test
     */
    public function placeHoldersAreReplaced(): void
    {
        putenv('FOO=bar');

        $root = __DIR__ . '/Fixtures/config';
        $configLoader = new ConfigLoader(true, $root . '/placeholders.yaml');
        $actualResult = $configLoader->load();

        $this->assertArrayHasKey('env', $actualResult);
        $this->assertArrayHasKey('const', $actualResult);
        $this->assertArrayHasKey('conf', $actualResult);

        $this->assertSame('bar', $actualResult['env']);
        $this->assertSame(PHP_EOL, $actualResult['const']);
        $this->assertSame('success', $actualResult['conf']);

        putenv('FOO');
    }

    /**
     * @test
     */
    public function invalidPlaceHoldersAreReplacedWithNull(): void
    {
        $root = __DIR__ . '/Fixtures/config';
        $configLoader = new ConfigLoader(true, $root . '/not_existing_placeholder.yaml');
        $actualResult = $configLoader->load();

        $this->assertArrayHasKey('env', $actualResult);

        $this->assertNull($actualResult['env']);
    }

    /**
     * @test
     */
    public function extensionSettingsAreSerialized(): void
    {
        if (class_exists(ContainerBuilder::class)) {
            self::markTestSkipped('Functionality disabled for TYPO3 10.4');
        }
        $root = __DIR__ . '/Fixtures/config';
        $configLoader = new ConfigLoader(true, $root . '/extension.yaml');
        $actualResult = $configLoader->load();

        $this->assertArrayHasKey('EXT', $actualResult);
        $this->assertSame('a:1:{s:3:"foo";s:3:"bar";}', $actualResult['EXT']['extConf']['bar_ext']);
    }

    /**
     * @test
     */
    public function customProcessorsAreCalled(): void
    {
        $root = __DIR__ . '/Fixtures/config';
        $configLoader = new ConfigLoader(true, $root . '/processors.yaml');
        $actualResult = $configLoader->load();

        $this->assertArrayHasKey('newKey', $actualResult);
        $this->assertArrayHasKey('foo', $actualResult);
        $this->assertSame('baz', $actualResult['foo']);
    }
}
