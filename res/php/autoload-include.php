<?php
declare(strict_types=1);

use Helhum\TYPO3\ConfigHandling\Xclass\ConfigurationManager;
use TYPO3\CMS\Core\Configuration\ConfigurationManager as CoreConfigurationManager;

if (!isset($_ENV['TYPO3_TESTING']) && getenv('TYPO3_TESTING') === false) {
    class_alias(ConfigurationManager::class, CoreConfigurationManager::class);
}
