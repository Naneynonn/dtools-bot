<?php

declare(strict_types=1);

use Ragnarok\Fenrir\Enums\ApplicationCommandOptionType;
use Ragnarok\Fenrir\Rest\Helpers\Command\CommandBuilder;
use Ragnarok\Fenrir\Rest\Helpers\Command\CommandOptionBuilder;

use function React\Async\await;

$appId = '867466728556068894';

await(
  $discord->rest->globalCommand->createApplicationCommand(
    $appId,
    CommandBuilder::new()
      ->setName('purge')
      ->setDescription('Bulk deletion of messages')
      ->setDescriptionLocalizations([
        'ru' => 'Массовое удаление сообщений',
        'uk' => 'Масове видалення повідомлень'
      ])
      ->addOption(
        CommandOptionBuilder::new()
          ->setName('any')
          ->setDescription('Mass deletion of all messages')
          ->setDescriptionLocalizations([
            'ru' => 'Массовое удаление всех сообщений',
            'uk' => 'Масове видалення всіх повідомлень'
          ])
          ->setType(ApplicationCommandOptionType::SUB_COMMAND)
          ->addOption(
            CommandOptionBuilder::new()
              ->setName('count')
              ->setDescription('The number of messages that will be deleted. From 1 to 100')
              ->setDescriptionLocalizations([
                'ru' => 'Количество сообщений, которое будет удалено. От 1 до 100',
                'uk' => 'Кількість повідомлень, які буде видалено. Від 1 до 100'
              ])
              ->setType(ApplicationCommandOptionType::INTEGER)
              ->setRequired(true)
          )
      )
      ->addOption(
        CommandOptionBuilder::new()
          ->setName('user')
          ->setDescription('Mass deletion of all user messages')
          ->setDescriptionLocalizations([
            'ru' => 'Массовое удаление всех сообщений пользователя',
            'uk' => 'Масове видалення всіх повідомлень користувача'
          ])
          ->setType(ApplicationCommandOptionType::SUB_COMMAND)
          ->addOption(
            CommandOptionBuilder::new()
              ->setName('count')
              ->setDescription('The number of messages that will be deleted. From 1 to 100')
              ->setDescriptionLocalizations([
                'ru' => 'Количество сообщений, которое будет удалено. От 1 до 100',
                'uk' => 'Кількість повідомлень, які буде видалено. Від 1 до 100'
              ])
              ->setType(ApplicationCommandOptionType::INTEGER)
              ->setRequired(true)
          )
          ->addOption(
            CommandOptionBuilder::new()
              ->setName('user')
              ->setDescription('User discord')
              ->setDescriptionLocalizations([
                'ru' => 'Пользователь дискорд',
                'uk' => 'Користувач дискорд'
              ])
              ->setType(ApplicationCommandOptionType::USER)
              ->setRequired(true)
          )
      )
      ->addOption(
        CommandOptionBuilder::new()
          ->setName('bots')
          ->setDescription('Mass deletion of all bot messages')
          ->setDescriptionLocalizations([
            'ru' => 'Массовое удаление всех сообщений ботов',
            'uk' => 'Масове видалення всіх повідомлень ботів'
          ])
          ->setType(ApplicationCommandOptionType::SUB_COMMAND)
          ->addOption(
            CommandOptionBuilder::new()
              ->setName('count')
              ->setDescription('The number of messages that will be deleted. From 1 to 100')
              ->setDescriptionLocalizations([
                'ru' => 'Количество сообщений, которое будет удалено. От 1 до 100',
                'uk' => 'Кількість повідомлень, які буде видалено. Від 1 до 100'
              ])
              ->setType(ApplicationCommandOptionType::INTEGER)
              ->setRequired(true)
          )
      )
  )
);

die('Commands added');
