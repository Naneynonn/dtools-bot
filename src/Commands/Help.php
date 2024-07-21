<?php

declare(strict_types=1);

namespace Naneynonn\Commands;

use Ragnarok\Fenrir\Interaction\CommandInteraction;

use Naneynonn\Embeds;
use Naneynonn\Attr\Command;
use Naneynonn\Core\App\CommandHelper;

#[Command(name: 'help')]
class Help extends CommandHelper
{
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

    $this->sendMessage(command: $command, embed: Embeds::commandHelp(lng: $this->lng));
    $this->getMemoryUsage(text: '[~] Command /help |');
  }
}
