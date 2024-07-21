<?php

declare(strict_types=1);

use Ragnarok\Fenrir\Enums\ApplicationCommandOptionType;
use Ragnarok\Fenrir\Enums\ApplicationCommandTypes;
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

await(
  $discord->rest->globalCommand->createApplicationCommand(
    $appId,
    CommandBuilder::new()
      ->setName('automod')
      ->setDescription('Automoderation module')
      ->setDescriptionLocalizations([
        'ru' => 'Модуль автомодерации',
        'uk' => 'Модуль автомодерації'
      ])
      ->addOption(
        CommandOptionBuilder::new()
          ->setName('filter')
          ->setDescription('Settings module')
          ->setDescriptionLocalizations([
            'ru' => 'Настройка модуля',
            'uk' => 'Налаштування модуля'
          ])
          ->setType(ApplicationCommandOptionType::SUB_COMMAND)
          ->addOption(
            CommandOptionBuilder::new()
              ->setName('enable')
              ->setDescription('Enable/disable auto-moderation module')
              ->setDescriptionLocalizations([
                'ru' => 'Включить/выключить модуль авто-модерации',
                'uk' => 'Включити/вимкнути модуль авто-модерації'
              ])
              ->setType(ApplicationCommandOptionType::BOOLEAN)
              ->setRequired(true)
          )
      )
      ->addOption(
        CommandOptionBuilder::new()
          ->setName('log')
          ->setDescription('Automoderation module')
          ->setDescriptionLocalizations([
            'ru' => 'Модуль автомодерации',
            'uk' => 'Модуль автомодерації'
          ])
          ->setType(ApplicationCommandOptionType::SUB_COMMAND)
          ->addOption(
            CommandOptionBuilder::new()
              ->setName('channel')
              ->setDescription('Specify the channel ID to which the logs will be sent')
              ->setDescriptionLocalizations([
                'ru' => 'Укажите ID канала в который будут отправлять логи',
                'uk' => 'Вкажіть ID каналу в який будуть відправляти логи'
              ])
              ->setType(ApplicationCommandOptionType::CHANNEL)
              ->setRequired(true)
          )
      )
      ->addOption(
        CommandOptionBuilder::new()
          ->setName('badwords')
          ->setDescription('Bad Word Filter')
          ->setDescriptionLocalizations([
            'ru' => 'Фильтр плохих слов',
            'uk' => 'Фільтр поганих слів'
          ])
          ->setType(ApplicationCommandOptionType::SUB_COMMAND)
          ->addOption(
            CommandOptionBuilder::new()
              ->setName('enable')
              ->setDescription('Enable/disable badwords filter')
              ->setDescriptionLocalizations([
                'ru' => 'Включить/выключить фильтр плохих слов',
                'uk' => 'Увімкнути/вимкнути фільтр поганих слів'
              ])
              ->setType(ApplicationCommandOptionType::BOOLEAN)
              ->setRequired(true)
          )
      )
      ->addOption(
        CommandOptionBuilder::new()
          ->setName('replace')
          ->setDescription('Similar characters filter')
          ->setDescriptionLocalizations([
            'ru' => 'Фильтр похожих символов',
            'uk' => 'Фільтр схожих символів'
          ])
          ->setType(ApplicationCommandOptionType::SUB_COMMAND)
          ->addOption(
            CommandOptionBuilder::new()
              ->setName('enable')
              ->setDescription('Enable/disable replace filter')
              ->setDescriptionLocalizations([
                'ru' => 'Включить/выключить фильтр похожих символов',
                'uk' => 'Увімкнути/вимкнути фільтр схожих символів'
              ])
              ->setType(ApplicationCommandOptionType::BOOLEAN)
              ->setRequired(true)
          )
      )
      ->addOption(
        CommandOptionBuilder::new()
          ->setName('zalgo')
          ->setDescription('Zalgo Filter')
          ->setDescriptionLocalizations([
            'ru' => 'Фильтр Залго (нечитаемые символы)',
            'uk' => 'Фільтр Залго (нечитабельні символи)'
          ])
          ->setType(ApplicationCommandOptionType::SUB_COMMAND)
          ->addOption(
            CommandOptionBuilder::new()
              ->setName('enable')
              ->setDescription('Enable/disable zalgo filter')
              ->setDescriptionLocalizations([
                'ru' => 'Включить/выключить фильтр залго (нечитаемые символы)',
                'uk' => 'Увімкнути/вимкнути фільтр залго (нечитабельні символи)'
              ])
              ->setType(ApplicationCommandOptionType::BOOLEAN)
              ->setRequired(true)
          )
      )
      ->addOption(
        CommandOptionBuilder::new()
          ->setName('duplicate')
          ->setDescription('Duplicate Filter')
          ->setDescriptionLocalizations([
            'ru' => 'Фильтр одинаковых слов/символов',
            'uk' => 'Фільтр однакових слів/символів'
          ])
          ->setType(ApplicationCommandOptionType::SUB_COMMAND)
          ->addOption(
            CommandOptionBuilder::new()
              ->setName('enable')
              ->setDescription('Enable/disable duplicate filter')
              ->setDescriptionLocalizations([
                'ru' => 'Включить/выключить фильтр одинаковых слов/символов',
                'uk' => 'Увімкнути/вимкнути фільтр однакових слів/символів'
              ])
              ->setType(ApplicationCommandOptionType::BOOLEAN)
              ->setRequired(true)
          )
      )
      ->addOption(
        CommandOptionBuilder::new()
          ->setName('caps')
          ->setDescription('Caps Filter')
          ->setDescriptionLocalizations([
            'ru' => 'Фильтр капса',
            'uk' => 'Фільтр капсу'
          ])
          ->setType(ApplicationCommandOptionType::SUB_COMMAND)
          ->addOption(
            CommandOptionBuilder::new()
              ->setName('enable')
              ->setDescription('Enable/disable caps filter')
              ->setDescriptionLocalizations([
                'ru' => 'Включить/выключить фильтр капса',
                'uk' => 'Увімкнути/вимкнути фільтр капсу'
              ])
              ->setType(ApplicationCommandOptionType::BOOLEAN)
              ->setRequired(true)
          )
      )
      ->setType(ApplicationCommandTypes::CHAT_INPUT)
  )
);

die('Commands added');
