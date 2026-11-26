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
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class ConfigLoaderTest extends TestCase
{
    #[Test]
    public function notExistingConfigFileReturnsTypo3DefaultConfiguration(): void
    {
        $configLoader = new ConfigLoader(true, '/not/existing.yaml');
        $this->assertArrayHasKey('SYS', $configLoader->load());
    }

    #[Test]
    public function notImportedTypo3DefaultConfigStillIncludesTypo3DefaultConfiguration(): void
    {
        $root = __DIR__ . '/Fixtures/ConfigLoaderTest';
        $configLoader = new ConfigLoader(true, $root . '/config.yaml');
        $actualResult = $configLoader->load();
        $this->assertArrayHasKey('SYS', $actualResult);
        $this->assertArrayHasKey('foo', $actualResult);
        $this->assertArrayHasKey('LOG', $actualResult);
    }

    #[Test]
    public function importingTypo3DefaultConfigurationRespectsSpecifiedExcludes(): void
    {
        $root = __DIR__ . '/Fixtures/ConfigLoaderTest';
        $configLoader = new ConfigLoader(true, $root . '/import_default.yaml');
        $actualResult = $configLoader->load();
        $this->assertArrayHasKey('SYS', $actualResult);
        $this->assertArrayHasKey('foo', $actualResult);
        $this->assertArrayNotHasKey('LOG', $actualResult);
    }

    #[Test]
    public function placeHoldersAreReplaced(): void
    {
        putenv('FOO=bar');

        $root = __DIR__ . '/Fixtures/ConfigLoaderTest';
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

    #[Test]
    public function invalidPlaceHoldersAreReplacedWithNull(): void
    {
        $root = __DIR__ . '/Fixtures/ConfigLoaderTest';
        $configLoader = new ConfigLoader(true, $root . '/not_existing_placeholder.yaml');
        $actualResult = $configLoader->load();

        $this->assertArrayHasKey('env', $actualResult);

        $this->assertNull($actualResult['env']);
    }

    #[Test]
    public function extensionSettingsArePreserved(): void
    {
        $root = __DIR__ . '/Fixtures/ConfigLoaderTest';
        $configLoader = new ConfigLoader(true, $root . '/extension.yaml');
        $actualResult = $configLoader->load();

        $this->assertSame(['foo' => 'bar'], $actualResult['EXTENSIONS']['bar_ext']);
        $this->assertArrayNotHasKey('extConf', $actualResult['EXT'] ?? []);
    }

    #[Test]
    public function customProcessorsAreCalled(): void
    {
        $root = __DIR__ . '/Fixtures/ConfigLoaderTest';
        $configLoader = new ConfigLoader(true, $root . '/processors.yaml');
        $actualResult = $configLoader->load();

        $this->assertArrayHasKey('newKey', $actualResult);
        $this->assertArrayHasKey('foo', $actualResult);
        $this->assertSame('baz', $actualResult['foo']);
    }
}
