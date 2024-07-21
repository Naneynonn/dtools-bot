<?php

declare(strict_types=1);

namespace Naneynonn\Commands;

use Ragnarok\Fenrir\Interaction\CommandInteraction;
use Ragnarok\Fenrir\Interaction\Helpers\InteractionCallbackBuilder;

use Ragnarok\Fenrir\Enums\InteractionCallbackType;
use Ragnarok\Fenrir\Enums\MessageFlag;
use Ragnarok\Fenrir\Enums\Permission;

use Naneynonn\Embeds;
use Naneynonn\Model;
use Naneynonn\Attr\Command;
use Naneynonn\Attr\SubCommand;
use Naneynonn\Core\App\CommandHelper;

use function Naneynonn\hasPermission;

#[Command(name: 'automod')]
class Automod extends CommandHelper
{
  // public function register(): CommandBuilder
  // {
  //   return CommandBuilder::new()
  //     ->setName('automod')
  //     ->setDescription('Automoderation module')
  //     ->setDescriptionLocalizations([
  //       'ru' => 'Модуль автомодерации',
  //       'uk' => 'Модуль автомодерації'
  //     ])
  //     ->addOption(
  //       CommandOptionBuilder::new()
  //         ->setName('filter')
  //         ->setDescription('Settings module')
  //         ->setDescriptionLocalizations([
  //           'ru' => 'Настройка модуля',
  //           'uk' => 'Налаштування модуля'
  //         ])
  //         ->setType(ApplicationCommandOptionType::SUB_COMMAND)
  //         ->addOption(
  //           CommandOptionBuilder::new()
  //             ->setName('enable')
  //             ->setDescription('Enable/disable auto-moderation module')
  //             ->setDescriptionLocalizations([
  //               'ru' => 'Включить/выключить модуль авто-модерации',
  //               'uk' => 'Включити/вимкнути модуль авто-модерації'
  //             ])
  //             ->setType(ApplicationCommandOptionType::BOOLEAN)
  //             ->setRequired(true)
  //         )
  //     )
  //     ->addOption(
  //       CommandOptionBuilder::new()
  //         ->setName('log')
  //         ->setDescription('Automoderation module')
  //         ->setDescriptionLocalizations([
  //           'ru' => 'Модуль автомодерации',
  //           'uk' => 'Модуль автомодерації'
  //         ])
  //         ->setType(ApplicationCommandOptionType::SUB_COMMAND)
  //         ->addOption(
  //           CommandOptionBuilder::new()
  //             ->setName('channel')
  //             ->setDescription('Specify the channel ID to which the logs will be sent')
  //             ->setDescriptionLocalizations([
  //               'ru' => 'Укажите ID канала в который будут отправлять логи',
  //               'uk' => 'Вкажіть ID каналу в який будуть відправляти логи'
  //             ])
  //             ->setType(ApplicationCommandOptionType::CHANNEL)
  //             ->setRequired(true)
  //         )
  //     )
  //     ->addOption(
  //       CommandOptionBuilder::new()
  //         ->setName('badwords')
  //         ->setDescription('Bad Word Filter')
  //         ->setDescriptionLocalizations([
  //           'ru' => 'Фильтр плохих слов',
  //           'uk' => 'Фільтр поганих слів'
  //         ])
  //         ->setType(ApplicationCommandOptionType::SUB_COMMAND)
  //         ->addOption(
  //           CommandOptionBuilder::new()
  //             ->setName('enable')
  //             ->setDescription('Enable/disable caps filter')
  //             ->setDescriptionLocalizations([
  //               'ru' => 'Включить/выключить фильтр капса',
  //               'uk' => 'Увімкнути/вимкнути фільтр капсу'
  //             ])
  //             ->setType(ApplicationCommandOptionType::BOOLEAN)
  //             ->setRequired(true)
  //         )
  //     )
  //     ->addOption(
  //       CommandOptionBuilder::new()
  //         ->setName('replace')
  //         ->setDescription('Similar characters filter')
  //         ->setDescriptionLocalizations([
  //           'ru' => 'Фильтр похожих символов',
  //           'uk' => 'Фільтр схожих символів'
  //         ])
  //         ->setType(ApplicationCommandOptionType::SUB_COMMAND)
  //         ->addOption(
  //           CommandOptionBuilder::new()
  //             ->setName('enable')
  //             ->setDescription('Enable/disable replace filter')
  //             ->setDescriptionLocalizations([
  //               'ru' => 'Включить/выключить фильтр похожих символов',
  //               'uk' => 'Увімкнути/вимкнути фільтр схожих символів'
  //             ])
  //             ->setType(ApplicationCommandOptionType::BOOLEAN)
  //             ->setRequired(true)
  //         )
  //     )
  //     ->addOption(
  //       CommandOptionBuilder::new()
  //         ->setName('zalgo')
  //         ->setDescription('Zalgo Filter')
  //         ->setDescriptionLocalizations([
  //           'ru' => 'Фильтр Залго (нечитаемые символы)',
  //           'uk' => 'Фільтр Залго (нечитабельні символи)'
  //         ])
  //         ->setType(ApplicationCommandOptionType::SUB_COMMAND)
  //         ->addOption(
  //           CommandOptionBuilder::new()
  //             ->setName('enable')
  //             ->setDescription('Enable/disable zalgo filter')
  //             ->setDescriptionLocalizations([
  //               'ru' => 'Включить/выключить фильтр залго (нечитаемые символы)',
  //               'uk' => 'Увімкнути/вимкнути фільтр залго (нечитабельні символи)'
  //             ])
  //             ->setType(ApplicationCommandOptionType::BOOLEAN)
  //             ->setRequired(true)
  //         )
  //     )
  //     ->addOption(
  //       CommandOptionBuilder::new()
  //         ->setName('duplicate')
  //         ->setDescription('Duplicate Filter')
  //         ->setDescriptionLocalizations([
  //           'ru' => 'Фильтр одинаковых слов/символов',
  //           'uk' => 'Фільтр однакових слів/символів'
  //         ])
  //         ->setType(ApplicationCommandOptionType::SUB_COMMAND)
  //         ->addOption(
  //           CommandOptionBuilder::new()
  //             ->setName('enable')
  //             ->setDescription('Enable/disable duplicate filter')
  //             ->setDescriptionLocalizations([
  //               'ru' => 'Включить/выключить фильтр одинаковых слов/символов',
  //               'uk' => 'Увімкнути/вимкнути фільтр однакових слів/символів'
  //             ])
  //             ->setType(ApplicationCommandOptionType::BOOLEAN)
  //             ->setRequired(true)
  //         )
  //     )
  //     ->setType(ApplicationCommandTypes::CHAT_INPUT);
  // }

  #[SubCommand(name: 'filter')]
  public function filter(CommandInteraction $command): void
  {
    $this->handle(command: $command);
  }

  #[SubCommand(name: 'log')]
  public function log(CommandInteraction $command): void
  {
    $this->handle(command: $command);
  }

  #[SubCommand(name: 'badwords')]
  public function badwords(CommandInteraction $command): void
  {
    $this->handle(command: $command);
  }

  #[SubCommand(name: 'caps')]
  public function caps(CommandInteraction $command): void
  {
    $this->handle(command: $command);
  }

  #[SubCommand(name: 'replace')]
  public function replace(CommandInteraction $command): void
  {
    $this->handle(command: $command);
  }

  #[SubCommand(name: 'zalgo')]
  public function zalgo(CommandInteraction $command): void
  {
    $this->handle(command: $command);
  }

  #[SubCommand(name: 'duplicate')]
  public function duplicate(CommandInteraction $command): void
  {
    $this->handle(command: $command);
  }

  public function handle(CommandInteraction $command): void
  {
    $interaction = $command->interaction;
    $this->setLocale(locale: $interaction->locale);

    if (is_null($interaction->guild_id ?? null) || !$this->isServerAdmin(interaction: $interaction)) {
      $this->sendMessage(command: $command, embed: Embeds::noPerm(lng: $this->lng));
      return;
    }

    $this->handleAdminCommand(command: $command, interaction: $interaction);
    $this->getMemoryUsage(text: '[~] Command /automod |');
  }

  private function handleAdminCommand(CommandInteraction $command, mixed $interaction): void
  {
    $model = new Model();

    if ($command->hasOption('filter')) {
      $value = $command->getOption('filter')?->options[0]?->value;
      if (is_null($value)) return;

      $title = $this->lng->trans('embed.automod.filter.disable');
      $color = $this->lng->trans('color.grey');

      if ($value) {
        $title = $this->lng->trans('embed.automod.filter.enable');
        $color = $this->lng->trans('color.success');
      }

      $model->automodToggle(server_id: $interaction->guild_id, is_enable: $value);
      $this->sendMessage(command: $command, embed: Embeds::response(color: $color, title: $title));
    }

    // TODO: NOT WORK
    if ($command->hasOption('log')) {
      $value = $command->getOption('log')?->options[0]?->value;
      if (is_null($value)) return;

      $model->updateAutomodLogChannel(server_id: $interaction->guild_id, log_channel: $value);
      $this->sendMessage(command: $command, embed: Embeds::success(text: $this->lng->trans('embed.automod.log')));
    }

    if ($command->hasOption('badwords')) {
      $this->toggleCommand(command: $command, interaction: $interaction, model: $model, type: 'badwords');
    }

    if ($command->hasOption('caps')) {
      $this->toggleCommand(command: $command, interaction: $interaction, model: $model, type: 'caps');
    }

    if ($command->hasOption('replace')) {
      $this->toggleCommand(command: $command, interaction: $interaction, model: $model, type: 'replace');
    }

    if ($command->hasOption('zalgo')) {
      $this->toggleCommand(command: $command, interaction: $interaction, model: $model, type: 'zalgo');
    }

    if ($command->hasOption('duplicate')) {
      $this->toggleCommand(command: $command, interaction: $interaction, model: $model, type: 'duplicate');
    }

    $model->close();
  }

  private function toggleCommand(CommandInteraction $command, mixed $interaction, Model $model, string $type): void
  {
    $value = $command->getOption($type)?->options[0]?->value;
    if (is_null($value)) return;

    $title = $this->lng->trans('embed.automod.filter.disable');
    $color = $this->lng->trans('color.grey');

    if ($value) {
      $title = $this->lng->trans('embed.automod.filter.enable');
      $color = $this->lng->trans('color.success');
    }

    $model->automodToggleCommands(server_id: $interaction->guild_id, is_enable: $value, type: $type);
    $this->sendMessage(command: $command, embed: Embeds::response(color: $color, title: $title));
  }
}
