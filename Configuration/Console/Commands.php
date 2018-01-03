<?php
return [
    'commands' => [
        'settings:dump' => [
            'class' => \Helhum\TYPO3\ConfigHandling\Command\SettingsDumpCommand::class,
        ],
        'settings:extract' => [
            'class' => \Helhum\TYPO3\ConfigHandling\Command\SettingsExtractCommand::class,
        ],
    ],
    'runLevels' => [
        'helhum/typo3-config-handling:settings:*' => \Helhum\Typo3Console\Core\Booting\RunLevel::LEVEL_COMPILE,
    ],
    'bootingSteps' => [
    ],
];
