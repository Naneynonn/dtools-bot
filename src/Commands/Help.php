<?php

namespace Naneynonn\Commands;

use Ragnarok\Fenrir\Discord;
use Ragnarok\Fenrir\Gateway\Events\Ready;

use Ragnarok\Fenrir\Interaction\CommandInteraction;
use Ragnarok\Fenrir\Interaction\Helpers\InteractionCallbackBuilder;

use Ragnarok\Fenrir\Enums\InteractionCallbackType;
use Ragnarok\Fenrir\Enums\MessageFlag;

use Naneynonn\CacheHelper;
use Naneynonn\Language;
use Naneynonn\Memory;
use Naneynonn\Embeds;

use Naneynonn\Attr\Command;

#[Command(name: 'help')]
class Help
{
  use Memory;

  private Discord $discord;
  private Language $lng;
  private Ready $ready;
  private CacheHelper $cache;

  public function __construct(Discord $discord, Language $lng, Ready $ready, CacheHelper $cache)
  {
    $this->discord = $discord;
    $this->lng = $lng;
  }

  // public function register(): CommandBuilder
  // {
  //   return CommandBuilder::new()
  //     ->setName('help')
  //     ->setDescription('DTools Bot Information')
  //     ->setDescriptionLocalizations([
  //       'ru' => 'Информация о боте DTools',
  //       'uk' => 'Інформація про бота DTools'
  //     ])
  //     ->setType(ApplicationCommandTypes::CHAT_INPUT);
  // }

  public function handle(CommandInteraction $command): void
  {
    $interaction = $command->interaction;
    $this->setLocale(locale: $interaction->locale);

    $callback = InteractionCallbackBuilder::new()
      ->addEmbed(Embeds::commandHelp(lng: $this->lng))
      ->setFlags(MessageFlag::EPHEMERAL->value)
      ->setType(InteractionCallbackType::CHANNEL_MESSAGE_WITH_SOURCE);

    $command->createInteractionResponse($callback);

    $this->getMemoryUsage(text: '[~] Command /help |');
  }

  private function setLocale(?string $locale): void
  {
    if (empty($locale)) return;

    $shortLocale = substr($locale, 0, 2);
    $this->lng->setLocale($shortLocale);
  }
}
