# Extended configuration handling for TYPO3 CMS

## Installation

TYPO3 Config Handling does only work for composer enabled (Composer Mode) TYPO3 projects.

1. Run `composer req helhum/typo3-config-handling`

## Disclaimer

Before using this package you should be aware of the following:

1. To achieve maximum performance and convenience, this package overrides the TYPO3 class `TYPO3\CMS\Core\Configuration\ConfigurationManager`.
As with any other overrides of classes from packages, this comes with several disadvantages,
most prominently that bugfixes introduced in the upstream package won't automatically be applied when using this package.
If you are reluctant to accept this disadvantage, you should abstain from using this package.
1. To achieve maximum performance in production, this package caches configuration values in a single file.
This means, when you run your TYPO3 application during development with `TYPO3_CONTEXT` set to `Production`,
manual changes to configuration files affect the cache. However, when using any TYPO3 UI (Install Tool) to change configuration values
the cache is automatically flushed. To avoid hassle, it is recommended to set `TYPO3_CONTEXT` set to `Development`
in your development environments.

## Features

### Settings depending on the environment
Depending in which environment (on which server) TYPO3 runs, some settings need to be changed to match this environment.
For example database connection settings, paths to external tools like Graphicsmagick or mail delivery settings.
TYPO3 Config Handling enables you to [distribute your TYPO3 settings over multiple files](#multiple-settings-files) and
pulling in [configuration values from environment variables](#placeholders).

### Configuration files in other formats
TYPO3 only allows to provide settings in two PHP files `LocalConfiguration.php` and `AdditionalConfiguration.php`.
When using this package it is possible to provide TYPO3 settings in Yaml files (and theoretically in any other format which can be parsed to an array, however currently only Yaml is implemented).
With newer TYPO3 versions Yaml format has been adopted for multiple things like form definitions, RTE configuration or sites configuration.
With TYPO3 Config Handling it is also possible to provide system settings in Yaml format.

### Configuration files stored in `config` folder
TYPO3 stores its configuration files in the document root. When using TYPO3 Config handling, the configuration files are stored in the `config` folder
along side with the TYPO3 sites configuration files.

### Enhanced site configuration
TYPO3 9.5 introduced the notion of a site. Sites are configured in yaml files stored in `config/sites/<site-identifier>/config.yaml`.
This great concept comes with some small limitations, that can be overcome by using this package:
1. Indentation of the yaml files is 2 spaces, which makes reading them hard. This is changed by now using 4 spaces
   when the configuration files are re-written when using the sites module.
1. While importing other files is possible using the imports feature, the imports feature is limited
   to import files either from extensions or relative to the TYPO3 main directory (PATH_site).
   This is fixed by using the more advanced handling of imports relative to the current config file
1. TYPO3 by default only supports `env` placeholder replacement, which means environment dependent site
   configuration must make use of environment variables. To overcome this limitation, with this package it is possible
   to override site configuration within the regular main configuration:
   ```yaml
   Site:
        site-identifier:
            base: 'https://overridden.tld' 
   ```
   With that it is possible to put all environment specific configuration into `override.settings.yaml`, without
   the need to expose some settings in the environment.
To enable this feature, an XCLASS needs to be registered in your main configuration:
```yaml
SYS:
    Objects:
        TYPO3\CMS\Core\Configuration\SiteConfiguration:
            className: Helhum\TYPO3\ConfigHandling\Typo3SiteConfiguration

```

### Encrypting values in configuration files
Credentials should not be put into version control. To achieve this, it would be possible to put credentials
either in environment variables or the `overrides.settings.yaml` on the respective systems. This however
can a tedious process depending on how your target systems are set up. This package comes with a compromise.
Credentials are encrypted with a strong encryption and then put into version control.
Then only the encryption key needs to be provided in the target environment once. If this is done
new encrypted values can be added over time by adding them to version control.
To encrypt values in a single configuration file, the cli command `typo3cms settings:encrypt -c config/live.yaml`
can be used. If no encryption key is provided to this command a new encryption key is generated and presented
in the output.
This encryption key needs then be put in the `override.settings.yaml` on the target system once:
```yaml
SYS:
    settingsEncryptionKey: def000008a...
```
By doing so, all values that follow the syntax `%decrypt(<encryptedString>)%` will be decrypted on the fly and presented as plaintext values to TYPO3.

The `settings:encrypt` cli command looks for configuration values in the following format: `%encrypt(<value to encrypt>)%`
The values are extracted from such placeholders, encrypted using the given encryption key and replaced with `%decrypt(<encryptedString>)%`

There are some prerequisites to follow if you want to use this feature:
* The composer package `defuse/php-encryption` needs to be installed in your project.
* The decryption processor needs to be added to one of your configuration files:
  ```yaml
  processors:
     - class: Helhum\TYPO3\ConfigHandling\Processor\DecryptSettingsProcessor
  ```

As you can see, the decryption is solely based on a processor, which handles the placeholder. 
It would therefore be possible to implement your own processor, which fetches credentials from a credential store
in your target environments. E.g. you could have placeholders like `%encrypt(my-database-password)%` and your
processor code could look up for the credential in a vault by using the identifier `my-database-password`.
In future versions, some of these vaults might be supported with this package, or third party packages could just provide
the sources for support of such credential stores.

## Migrating your TYPO3 project to use TYPO3 Config Handling
1. [Install](#install) the package using composer
1. Run `vendor/bin/typo3cms settings:extract --config-file config/settings.yaml` to extract configuration values from an existing `LocalConfiguration.php` file

## Multiple settings files

## Placeholders

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
            "settings": "config/settings.yaml",
            "dev-settings": "config/dev.settings.yaml",
            "override-settings": "config/override.settings.yaml",
            "install-steps": "config/setup/install.steps.yaml"
        }
    }
}
```

### Example to match Symfony framework default layout

```json
{
    "extra": {
        "helhum/typo3-config-handling": {
            "settings": "config/config_prod.yaml",
            "dev-settings": "config/config_dev.yaml"
        }
    }
}
```

### Example to match Neos Flow framework style layout

```json
{
    "extra": {
        "helhum/typo3-config-handling": {
            "settings": "Configuration/Settings.yaml",
            "dev-settings": "Configuration/Development/Settings.yaml"
        }
    }
}
```
