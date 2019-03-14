<?php
declare(strict_types=1);

return [
    'commands' => [
        'settings:extract' => [
            'class' => \Helhum\TYPO3\ConfigHandling\Command\ExtractSettingsCommand::class,
        ],
    ],
    'runLevels' => [
        'helhum/typo3-config-handling:settings:*' => \Helhum\Typo3Console\Core\Booting\RunLevel::LEVEL_COMPILE,
    ],
    'bootingSteps' => [
    ],
];
