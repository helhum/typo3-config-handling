<?php
declare(strict_types=1);

return [
    'settings:encrypt' => [
        'class' => \Helhum\TYPO3\ConfigHandling\Command\EncryptSettingsCommand::class,
        'runLevel' => \Helhum\Typo3Console\Core\Booting\RunLevel::LEVEL_COMPILE,
    ],
    'settings:extract' => [
        'class' => \Helhum\TYPO3\ConfigHandling\Command\ExtractSettingsCommand::class,
        'runLevel' => \Helhum\Typo3Console\Core\Booting\RunLevel::LEVEL_COMPILE,
    ],
];
