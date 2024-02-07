<?php
declare(strict_types=1);
namespace Helhum\TYPO3\ConfigHandling\Tests\Unit;

use Helhum\TYPO3\ConfigHandling\ConfigDumper;
use Helhum\TYPO3\ConfigHandling\Processor\RemoveSettingsProcessor;
use org\bovigo\vfs\vfsStream;
use PHPUnit\Framework\TestCase;

class ConfigDumperTest extends TestCase
{
    /**
     * @test
     */
    public function dumpingEmptyArrayProducesYamlWithEmptyArray(): void
    {
        vfsStream::setup();
        $root = vfsStream::url('root');
        $settingsFile = $root . '/settings.yaml';
        $configDumper = new ConfigDumper();
        $configDumper->dumpToFile(
            [],
            $settingsFile
        );
        self::assertSame(
            '{  }',
            file_get_contents($settingsFile),
        );
    }

    /**
     * @test
     */
    public function dumpingConfigWithOnlyProcessorConfigDoesProduceValidYaml(): void
    {
        vfsStream::setup();
        $root = vfsStream::url('root');
        $settingsFile = $root . '/settings.yaml';
        $configDumper = new ConfigDumper();
        $configDumper->dumpToFile(
            [
                'processors' => [
                    'foo' => [
                        'class' => RemoveSettingsProcessor::class,
                        'paths' => [
                            '"SYS"."sqlDebug"',
                        ],
                    ],
                ],
            ],
            $settingsFile
        );
        self::assertSame(
            'processors:' . chr(10) . '    foo: { class: Helhum\TYPO3\ConfigHandling\Processor\RemoveSettingsProcessor, paths: [\'"SYS"."sqlDebug"\'] }' . chr(10) . chr(10),
            file_get_contents($settingsFile),
        );
    }

    /**
     * @test
     */
    public function dumpingConfigWithOnlyImportConfigDoesProduceValidYaml(): void
    {
        vfsStream::setup();
        $root = vfsStream::url('root');
        $settingsFile = $root . '/settings.yaml';
        $configDumper = new ConfigDumper();
        $configDumper->dumpToFile(
            [
                'imports' => [
                    [
                        'resource' => 'foo.yaml',
                    ],
                ],
            ],
            $settingsFile
        );
        self::assertSame(
            'imports:' . chr(10) . '    - { resource: foo.yaml }' . chr(10) . chr(10),
            file_get_contents($settingsFile),
        );
    }

    /**
     * @test
     */
    public function dumpingConfigWithImportAndProcessorsProduceNiceOutput(): void
    {
        vfsStream::setup();
        $root = vfsStream::url('root');
        $settingsFile = $root . '/settings.yaml';
        $configDumper = new ConfigDumper();
        $configDumper->dumpToFile(
            [
                'processors' => [
                    'foo' => [
                        'class' => RemoveSettingsProcessor::class,
                        'paths' => [
                            '"SYS"."sqlDebug"',
                        ],
                    ],
                ],
                'imports' => [
                    [
                        'resource' => 'foo.yaml',
                    ],
                ],
                'foo' => 'bar'
            ],
            $settingsFile
        );
        self::assertSame(
            'imports:' . chr(10) . '    - { resource: foo.yaml }' . chr(10) .
            'processors:' . chr(10) . '    foo: { class: Helhum\TYPO3\ConfigHandling\Processor\RemoveSettingsProcessor, paths: [\'"SYS"."sqlDebug"\'] }' . chr(10) . chr(10) .
            'foo: bar' . chr(10),
            file_get_contents($settingsFile),
        );
    }

    /**
     * @test
     */
    public function commentBlockIsAddedProperly(): void
    {
        vfsStream::setup();
        $root = vfsStream::url('root');
        $settingsFile = $root . '/settings.yaml';
        $configDumper = new ConfigDumper();
        $configDumper->dumpToFile(
            [
                'processors' => [
                    'foo' => [
                        'class' => RemoveSettingsProcessor::class,
                        'paths' => [
                            '"SYS"."sqlDebug"',
                        ],
                    ],
                ],
                'imports' => [
                    [
                        'resource' => 'foo.yaml',
                    ],
                ],
                'foo' => 'bar'
            ],
            $settingsFile,
            'MY COMMENT'
        );
        self::assertSame(
            '# MY COMMENT' . chr(10) .
            'imports:' . chr(10) . '    - { resource: foo.yaml }' . chr(10) .
            'processors:' . chr(10) . '    foo: { class: Helhum\TYPO3\ConfigHandling\Processor\RemoveSettingsProcessor, paths: [\'"SYS"."sqlDebug"\'] }' . chr(10) . chr(10) .
            'foo: bar' . chr(10),
            file_get_contents($settingsFile),
        );
    }
}
