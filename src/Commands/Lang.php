<?php

declare(strict_types=1);

namespace Naneynonn\Commands;

use Ragnarok\Fenrir\Interaction\CommandInteraction;

use Symfony\Component\Intl\Languages;

use Naneynonn\Model;
use Naneynonn\Embeds;
use Naneynonn\Attr\Command;
use Naneynonn\Core\App\CommandHelper;
use Naneynonn\Core\Attr\SubCommand;

#[Command(name: 'lang')]
class Lang extends CommandHelper
{
  // public function register(): CommandBuilder
  // {
  //   return CommandBuilder::new()
  //     ->setName('lang')
  //     ->setDescription('Show bot language')
  //     ->setDescriptionLocalizations([
  //       'ru' => 'Показать язык бота',
  //       'uk' => 'Показати мову бота'
  //     ])
  //     ->addOption(
  //       CommandOptionBuilder::new()
  //         ->setName('set')
  //         ->setDescription('Set bot language')
  //         ->setDescriptionLocalizations([
  //           'ru' => 'Установить язык бота',
  //           'uk' => 'Встановити мову бота'
  //         ])
  //         ->setType(ApplicationCommandOptionType::STRING)
  //         ->setRequired(false)
  //         ->addChoice(name: 'English', value: 'en', localizedNames: [
  //           'ru' => 'Английский',
  //           'uk' => 'Англійська',
  //         ])
  //         ->addChoice(name: 'Russian', value: 'ru', localizedNames: [
  //           'ru' => 'Русский',
  //           'uk' => 'Російська',
  //         ])
  //         ->addChoice(name: 'Ukrainian', value: 'uk', localizedNames: [
  //           'ru' => 'Украинский',
  //           'uk' => 'Українська',
  //         ])
  //     )
  //     ->setType(ApplicationCommandTypes::CHAT_INPUT);
  // }

  // public function handle(CommandInteraction $command): void
  // {
  //   $interaction = $command->interaction;
  //   $this->setLocale(locale: $interaction->locale);

  //   if (is_null($interaction->guild_id ?? null)) {
  //     $this->sendMessage(command: $command, embed: Embeds::noPerm(lng: $this->lng));
  //     return;
  //   }

  //   $model = new Model();
  //   $server = $model->getServerLang(id: $interaction->guild_id);
  //   $model->close();

  //   $this->sendMessage(command: $command, embed: Embeds::info(text: $this->lng->trans('embed.lang.server', ['%lang%' => Languages::getName($server['lang'])])));

  //   $this->getMemoryUsage(text: '[~] Command /lang |');
  // }

  // #[SubCommand(name: 'set')]
  // public function set(CommandInteraction $command): void
  // {
  //   $interaction = $command->interaction;
  //   $this->setLocale(locale: $interaction->locale);

  //   if (is_null($interaction->guild_id ?? null) || !$this->isServerAdmin(interaction: $interaction)) {
  //     $this->sendMessage(command: $command, embed: Embeds::noPerm(lng: $this->lng));
  //     return;
  //   }

  //   $model = new Model();
  //   $model->setServerLang(server_id: $interaction->guild_id, lang: $command->getOption('set')->value);
  //   $model->close();

  //   $this->sendMessage(command: $command, embed: Embeds::success(text: $this->lng->trans('embed.lang.set')));
  // }

  // TEMP
  public function handle(CommandInteraction $command): void
  {
    $interaction = $command->interaction;
    $this->setLocale(locale: $interaction->locale);

    if (is_null($interaction->guild_id ?? null)) {
      $this->sendMessage(command: $command, embed: Embeds::noPerm(lng: $this->lng));
      return;
    }

    $model = new Model();

    if ($command->hasOption('set')) {
      $model->setServerLang(server_id: $interaction->guild_id, lang: $command->getOption('set')->value);
      $this->sendMessage(command: $command, embed: Embeds::success(text: $this->lng->trans('embed.lang.set')));
    } else {
      $server = $model->getServerLang(id: $interaction->guild_id);
      $this->sendMessage(command: $command, embed: Embeds::info(text: $this->lng->trans('embed.lang.server', ['%lang%' => Languages::getName($server['lang'])])));
    }

    $model->close();

    $this->getMemoryUsage(text: '[~] Command /lang |');
  }
}
