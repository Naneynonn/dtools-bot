<?php

use Discord\Builders\CommandBuilder;

use Discord\Parts\Interactions\Command\Command; // Please note to use this correct namespace!
use Discord\Parts\Interactions\Command\Option;
// use Discord\Parts\Interactions\Command\Choice;

// $discord->application->commands->save($discord->application->commands->create(
//   CommandBuilder::new()
//     ->setName('set')
//     ->setDescription('Bot setup')
//     ->setDescriptionLocalization('ru', 'Настройка бота')
//     ->setDescriptionLocalization('uk', 'Налаштування бота')
//     ->addOption((new Option($discord))
//         ->setName('automod')
//         ->setDescription('Automoderation module')
//         ->setDescriptionLocalization('ru', 'Модуль автомодерации')
//         ->setDescriptionLocalization('uk', 'Модуль автомодерації')
//         ->setType(Option::SUB_COMMAND_GROUP)
//         ->addOption((new Option($discord))
//             ->setName('filter')
//             ->setDescription('Enable/disable auto-moderation module')
//             ->setDescriptionLocalization('ru', 'Включить/выключить модуль авто-модерации')
//             ->setDescriptionLocalization('uk', 'Включити/вимкнути модуль авто-модерації')
//             ->setType(Option::SUB_COMMAND)
//             ->addOption((new Option($discord))
//                 ->setName('badwords')
//                 ->setDescription('Enable/disable auto-moderation module')
//                 ->setDescriptionLocalization('ru', 'Включить/выключить модуль авто-модерации')
//                 ->setDescriptionLocalization('uk', 'Включити/вимкнути модуль авто-модерації')
//                 ->setType(Option::BOOLEAN)
//                 ->setRequired(true)
//             )
//             ->addOption((new Option($discord))
//                 ->setName('caps')
//                 ->setDescription('Enable/disable auto-moderation module')
//                 ->setDescriptionLocalization('ru', 'Включить/выключить модуль авто-модерации')
//                 ->setDescriptionLocalization('uk', 'Включити/вимкнути модуль авто-модерації')
//                 ->setType(Option::BOOLEAN)
//                 ->setRequired(true)
//             )
//             ->addOption((new Option($discord))
//                 ->setName('replace')
//                 ->setDescription('Enable/disable auto-moderation module')
//                 ->setDescriptionLocalization('ru', 'Включить/выключить модуль авто-модерации')
//                 ->setDescriptionLocalization('uk', 'Включити/вимкнути модуль авто-модерації')
//                 ->setType(Option::BOOLEAN)
//                 ->setRequired(true)
//             )
//         )
//     )
//     ->toArray()
// ));

// $create_cmd = new Command($discord, [
//   'name' => 'set',
//   'description' => 'Bot setup',
//   'description_localizations' => [
//     'ru' => 'Настройка бота',
//     'uk' => 'Налаштування бота'
//   ],
//   'options' => [
//     // [
//     //   'name' => 'automod2',
//     //   'description' => 'Enable/disable auto-moderation module',
//     //   'description_localizations' => [
//     //     'ru' => 'Включить/выключить модуль авто-модерации',
//     //     'uk' => 'Включити/вимкнути модуль авто-модерації'
//     //   ],
//     //   'type' => 5,
//     //   'required' => true
//     // ],
//     [
//       'name' => 'automod',
//       'description' => 'Automoderation module',
//       'description_localizations' => [
//         'ru' => 'Модуль автомодерации',
//         'uk' => 'Модуль автомодерації'
//       ],
//       'type' => 2,
//       'options' => [
//         [
//           'name' => 'enable',
//           'description' => 'Enable/disable auto-moderation module',
//           'description_localizations' => [
//             'ru' => 'Включить/выключить модуль авто-модерации',
//             'uk' => 'Включити/вимкнути модуль авто-модерації'
//           ],
//           'type' => 5,
//           'required' => true
//         ],
//         // [
//         //   'name' => 'log',
//         //   'description' => 'Setting up channels for logs',
//         //   'description_localizations' => [
//         //     'ru' => 'Включить/выключить модуль авто-модерации',
//         //     'uk' => 'Включити/вимкнути модуль авто-модерації'
//         //   ],
//         //   'type' => 1,
//         //   'options' => [
//         //     [
//         //       'name' => 'badwords',
//         //       'description' => 'badwords enable',
//         //       // 'description_localizations' => [
//         //       //   'ru' => 'Включить/выключить модуль авто-модерации',
//         //       //   'uk' => 'Включити/вимкнути модуль авто-модерації'
//         //       // ],
//         //       'type' => 7,
//         //       'required' => true
//         //     ]
//         //   ]
//         // ]
//       ]
//     ]
//   ]
// ]);
// $discord->application->commands->save($create_cmd);
