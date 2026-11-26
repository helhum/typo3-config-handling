<?php
declare(strict_types=1);
namespace Helhum\TYPO3\ConfigHandling\Tests\Functional;

use Helhum\TYPO3\ConfigHandling\Typo3SiteConfiguration;
use Helhum\TYPO3\ConfigHandling\Typo3SiteConfigurationV13;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Psr\EventDispatcher\EventDispatcherInterface;
use Psr\Log\NullLogger;
use Symfony\Component\Filesystem\Filesystem;
use TYPO3\CMS\Core\Cache\Frontend\FrontendInterface;
use TYPO3\CMS\Core\Cache\Frontend\PhpFrontend;
use TYPO3\CMS\Core\Configuration\Event\SiteConfigurationLoadedEvent;
use TYPO3\CMS\Core\Configuration\Loader\Exception\YamlParseException;
use TYPO3\CMS\Core\Configuration\Loader\YamlFileLoader;
use TYPO3\CMS\Core\Core\ApplicationContext;
use TYPO3\CMS\Core\Core\Environment;
use TYPO3\CMS\Core\Information\Typo3Version;
use TYPO3\CMS\Core\Site\Entity\SiteSettings;
use TYPO3\CMS\Core\Site\Set\SetRegistry;
use TYPO3\CMS\Core\Site\SiteSettingsFactory;
use TYPO3\CMS\Core\Utility\GeneralUtility;

final class Typo3SiteConfigurationTest extends TestCase
{
    private string $configPath;
    private array $originalConfiguration;
    private string|false $originalBase;
    private array $originalEnvironment;

    protected function setUp(): void
    {
        $this->originalConfiguration = $GLOBALS['TYPO3_CONF_VARS'] ?? [];
        $this->originalBase = getenv('CONFIG_HANDLING_SITE_BASE');
        $this->originalEnvironment = (new \ReflectionClass(Environment::class))->getStaticProperties();
        $this->configPath = sys_get_temp_dir() . '/config-handling-' . bin2hex(random_bytes(8));
        (new Filesystem())->mirror(__DIR__ . '/Fixtures/Typo3SiteConfigurationTest', $this->configPath);
        Environment::initialize(
            new ApplicationContext('Testing'), true, true,
            $this->configPath, $this->configPath, $this->configPath,
            $this->configPath, __FILE__, PHP_OS,
        );
        putenv('CONFIG_HANDLING_SITE_BASE=https://environment.example/');
        $GLOBALS['TYPO3_CONF_VARS'] = ['SYS' => ['folderCreateMask' => '2775', 'fileCreateMask' => '0664']];
    }

    protected function tearDown(): void
    {
        $GLOBALS['TYPO3_CONF_VARS'] = $this->originalConfiguration;
        putenv($this->originalBase === false ? 'CONFIG_HANDLING_SITE_BASE' : 'CONFIG_HANDLING_SITE_BASE=' . $this->originalBase);
        GeneralUtility::purgeInstances();
        (new Filesystem())->remove($this->configPath);
        foreach ($this->originalEnvironment as $name => $value) {
            (new \ReflectionProperty(Environment::class, $name))->setValue(null, $value);
        }
    }

    #[Test]
    public function loadPreservesPlaceholders(): void
    {
        $configuration = $this->createSiteConfiguration()->load('main');

        self::assertSame('%env(CONFIG_HANDLING_SITE_BASE)%', $configuration['base']);
        self::assertSame(1, $configuration['rootPageId']);
    }

    #[Test]
    public function resolvedSiteUsesImports(): void
    {
        $sites = $this->createSiteConfiguration()->resolveAllExistingSites(false);

        self::assertSame('Imported title', $sites['main']->getConfiguration()['websiteTitle']);
    }

    #[Test]
    public function resolvedSiteReplacesPlaceholders(): void
    {
        $sites = $this->createSiteConfiguration()->resolveAllExistingSites(false);

        self::assertSame('https://environment.example/', (string)$sites['main']->getBase());
    }

    #[Test]
    public function resolvedSiteUsesGlobalOverrides(): void
    {
        $GLOBALS['TYPO3_CONF_VARS']['Site']['main']['base'] = 'https://override.example/';
        $sites = $this->createSiteConfiguration()->resolveAllExistingSites(false);

        self::assertSame('https://override.example/', (string)$sites['main']->getBase());
    }

    #[Test]
    public function resolvedSiteUsesConfigurationEvent(): void
    {
        $dispatcher = $this->createMock(EventDispatcherInterface::class);
        $dispatcher->expects(self::once())->method('dispatch')->willReturnCallback(
            static function (SiteConfigurationLoadedEvent $event): SiteConfigurationLoadedEvent {
                $configuration = $event->getConfiguration();
                $configuration['base'] = 'https://event.example/';
                $event->setConfiguration($configuration);
                return $event;
            }
        );
        $sites = $this->createSiteConfiguration(dispatcher: $dispatcher)->resolveAllExistingSites(false);

        self::assertSame('https://event.example/', (string)$sites['main']->getBase());
    }

    #[Test]
    public function writeInvalidatesRuntimeCache(): void
    {
        $runtimeCache = $this->createMock(FrontendInterface::class);
        $removed = [];
        $runtimeCache->expects(self::atLeastOnce())->method('remove')->willReturnCallback(
            static function (string $identifier) use (&$removed): bool {
                $removed[] = $identifier;
                return true;
            }
        );

        $this->createSiteConfiguration(runtimeCache: $runtimeCache)->write('main', ['rootPageId' => 1, 'base' => '/']);

        self::assertContains('sites-configuration', $removed);
    }

    #[Test]
    public function writeCreatesSiteDirectory(): void
    {
        $configuration = ['rootPageId' => 2, 'base' => '/'];
        $siteConfiguration = $this->createSiteConfiguration();
        $siteConfiguration->write('new', $configuration);

        self::assertSame($configuration, $siteConfiguration->load('new'));
    }

    #[Test]
    public function invalidYamlRaisesTypo3Exception(): void
    {
        file_put_contents($this->configPath . '/main/config.yaml', 'base: [');
        $this->expectException(YamlParseException::class);

        $this->createSiteConfiguration()->load('main');
    }

    private function createSiteConfiguration(
        ?EventDispatcherInterface $dispatcher = null,
        ?FrontendInterface $runtimeCache = null,
    ): Typo3SiteConfiguration|Typo3SiteConfigurationV13 {
        $settings = $this->createStub(SiteSettingsFactory::class);
        $settings->method('getSettings')->willReturn(SiteSettings::createFromSettingsTree([]));
        $settings->method('createSettings')->willReturn(SiteSettings::createFromSettingsTree([]));
        $cache = $this->createStub(PhpFrontend::class);
        $cache->method('require')->willReturn(false);
        $dispatcher ??= new class implements EventDispatcherInterface {
            public function dispatch(object $event): object
            {
                return $event;
            }
        };
        $arguments = [
            $this->configPath,
            $settings,
            $this->createStub(SetRegistry::class),
            $dispatcher,
            $cache,
            new YamlFileLoader(new NullLogger()),
            $runtimeCache ?? $this->createStub(FrontendInterface::class),
        ];
        if ((new Typo3Version())->getMajorVersion() >= 14) {
            $arguments[] = new NullLogger();
            return new Typo3SiteConfiguration(...$arguments);
        }

        return new Typo3SiteConfigurationV13(...$arguments);
    }
}
