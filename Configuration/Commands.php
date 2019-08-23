<?php
declare(strict_types=1);

return [
    'settings:dump' => [
        'class' => \Helhum\TYPO3\ConfigHandling\Command\DumpSettingsCommand::class,
        'runLevel' => \Helhum\Typo3Console\Core\Booting\RunLevel::LEVEL_COMPILE,
    ],
    'settings:extract' => [
        'class' => \Helhum\TYPO3\ConfigHandling\Command\ExtractSettingsCommand::class,
        'runLevel' => \Helhum\Typo3Console\Core\Booting\RunLevel::LEVEL_COMPILE,
    ],
];
