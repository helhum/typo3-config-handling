# TYPO3 Config handling package

## Installation steps for helhum/typo3-config-handling

1. Run `composer req helhum/typo3-config-handling`

## Change configuration directory layout

Add the following section to you `composer.json` to change the configuration directory structure
to fits your needs. Note that you only need to specify the entry point config for the two contexts,
and inside these files you can specify imports of subsequent config files.

Optionally for the automatic LocalConfiguration.php config extraction you can specify different
files for main config and extension config being extracted to.

All paths are relative to your root composer.json directory and must not begin with a slash

### Default layout

```json
{
    "extra": {
        "helhum/typo3-config-handling": {
            "main-config": "config/settings.yaml",
            "prod-config": "config/settings.yaml",
            "dev-config": "config/dev.settings.yaml",
            "ext-config": "config/settings.extension.yaml"
        }
    }
}
```

### Example to match Symfony framework default layout

```json
{
    "extra": {
        "helhum/typo3-config-handling": {
            "prod-config": "config/config_prod.yaml",
            "dev-config": "config/config_dev.yaml"
        }
    }
}
```

### Example to match Neos Flow framework style layout

```json
{
    "extra": {
        "helhum/typo3-config-handling": {
            "main-config": "Configuration/Settings.yaml",
            "prod-config": "Configuration/Production/Settings.yaml",
            "dev-config": "Configuration/Development/Settings.yaml"
        }
    }
}
```
