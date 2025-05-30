<?php
declare(strict_types=1);
namespace Helhum\TYPO3\ConfigHandling;

class SettingsFiles
{
    private static ?array $cachedComposerConfig = null;

    public static function getSettingsFile(bool $isProduction): string
    {
        if ($isProduction || !file_exists(self::getComposerConfig()['dev-settings'])) {
            return self::getComposerConfig()['settings'];
        }

        return self::getComposerConfig()['dev-settings'];
    }

    public static function getOverrideSettingsFile(): string
    {
        return self::getComposerConfig()['override-settings'];
    }

    public static function getEnvironmentSettingsFile(): string
    {
        return self::getComposerConfig()['environment-settings'];
    }

    public static function getInstallStepsFile(): string
    {
        return self::getComposerConfig()['install-steps'];
    }

    private static function getComposerConfig(): array
    {
        if (self::$cachedComposerConfig) {
            return self::$cachedComposerConfig;
        }
        $composerRoot = getenv('TYPO3_PATH_COMPOSER_ROOT');
        $appRoot = getenv('TYPO3_PATH_APP');
        $rootConfig = [
            'settings' => $appRoot . '/config/settings.yaml',
            'dev-settings' => $appRoot . '/config/dev.settings.yaml',
            'environment-settings' => $appRoot . '/config/environment.settings.yaml',
            'override-settings' => $appRoot . '/config/override.settings.yaml',
            'install-steps' => $appRoot . '/config/setup/install.steps.yaml',
        ];
        $composerConfig = \json_decode(file_get_contents($composerRoot . '/composer.json'), true);
        foreach ($rootConfig as $name => $defaultValue) {
            if (!empty($composerConfig['extra']['helhum/typo3-config-handling'][$name])) {
                $rootConfig[$name] = $composerRoot . '/' . $composerConfig['extra']['helhum/typo3-config-handling'][$name];
            }
        }

        return self::$cachedComposerConfig = $rootConfig;
    }
}
