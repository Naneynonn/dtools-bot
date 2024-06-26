<?php

declare(strict_types=1);

namespace Naneynonn\Commands;

use Ragnarok\Fenrir\Interaction\CommandInteraction;
use Ragnarok\Fenrir\Interaction\Helpers\InteractionCallbackBuilder;

use Ragnarok\Fenrir\Enums\InteractionCallbackType;
use Ragnarok\Fenrir\Enums\MessageFlag;
use Ragnarok\Fenrir\Enums\Permission;

use Symfony\Component\Intl\Languages;

use Naneynonn\Model;
use Naneynonn\Embeds;
use Naneynonn\Attr\Command;
use Naneynonn\Core\App\CommandHelper;

use function Naneynonn\hasPermission;

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

  public function handle(CommandInteraction $command): void
  {
    $interaction = $command->interaction;
    $this->setLocale(locale: $interaction->locale);

    $callback = InteractionCallbackBuilder::new()
      ->setFlags(MessageFlag::EPHEMERAL->value)
      ->setType(InteractionCallbackType::CHANNEL_MESSAGE_WITH_SOURCE);

    // Если команду используют в ЛС
    if (is_null($interaction->guild_id ?? null)) {
      $callback->setContent(content: Embeds::noPerm(lng: $this->lng));
    } else {
      if (hasPermission(bitmask: (int) $interaction->member->permissions, permission: Permission::ADMINISTRATOR)) {
        $this->handleAdminCommand(command: $command, interaction: $interaction, callback: $callback);
      } else {
        $callback->setContent(content: Embeds::noPerm(lng: $this->lng));
      }
    }

    $command->createInteractionResponse($callback);

    $this->getMemoryUsage(text: '[~] Command /lang |');
  }

  private function setLocale(?string $locale): void
  {
    if (empty($locale)) return;

    $shortLocale = substr($locale, 0, 2);
    $this->lng->setLocale($shortLocale);
    \Locale::setDefault($shortLocale);
  }

  private function handleAdminCommand(CommandInteraction $command, mixed $interaction, InteractionCallbackBuilder $callback): void
  {
    $model = new Model();

    if ($command->hasOption('set')) {
      $model->setServerLang(server_id: $interaction->guild_id, lang: $command->getOption('set')->value);
      $embed = Embeds::response(color: $this->lng->trans('color.success'), title: $this->lng->trans('embed.lang.set'));

      $callback->addEmbed(embed: $embed);
    } else {
      $server = $model->getServerLang(id: $interaction->guild_id);
      $embed = Embeds::getLang(lng: $this->lng, lang: Languages::getName($server['lang']));

      $callback->addEmbed(embed: $embed);
    }

    $model->close();
  }
}
