<?php

declare(strict_types=1);

namespace Naneynonn\Commands;

use Ragnarok\Fenrir\Interaction\CommandInteraction;

use Naneynonn\Embeds;
use Naneynonn\Model;
use Naneynonn\Attr\Command;
use Naneynonn\Attr\SubCommand;
use Naneynonn\Core\App\CommandHelper;

#[Command(name: 'automod')]
class Automod extends CommandHelper
{
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

  #[SubCommand(name: 'invite')]
  public function invite(CommandInteraction $command): void
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

    if ($command->hasOption('invite')) {
      $this->toggleCommand(command: $command, interaction: $interaction, model: $model, type: 'invite');
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
