<?php
if (!isset($_ENV['TYPO3_TESTING']) && getenv('TYPO3_TESTING') === false) {
    class_alias(\Helhum\TYPO3\ConfigHandling\Xclass\ConfigurationManager::class, \TYPO3\CMS\Core\Configuration\ConfigurationManager::class);
}
