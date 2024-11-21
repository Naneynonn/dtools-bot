<?php

declare(strict_types=1);

namespace Naneynonn\Core\App;

use Ragnarok\Fenrir\Discord;
use Ragnarok\Fenrir\Gateway\Events\Ready;
use Ragnarok\Fenrir\Gateway\Events\InteractionCreate;

use Ragnarok\Fenrir\Enums\InteractionCallbackType;
use Ragnarok\Fenrir\Enums\MessageFlag;
use Ragnarok\Fenrir\Enums\Permission;

use Ragnarok\Fenrir\Interaction\CommandInteraction;
use Ragnarok\Fenrir\Interaction\Helpers\InteractionCallbackBuilder;

use Ragnarok\Fenrir\Rest\Helpers\Channel\EmbedBuilder;

use Naneynonn\Core\App\Buffer;

use Naneynonn\Loader;
use Naneynonn\Memory;
use Naneynonn\Language;

use Clue\React\Redis\RedisClient;
use React\EventLoop\LoopInterface;

use function Naneynonn\hasPermission;

abstract class CommandHelper
{
  use Memory;

  protected Discord $discord;
  protected Ready $ready;
  protected RedisClient $redis;
  protected Language $lng;

  protected LoopInterface $loop;
  protected Buffer $buffer;

  public function __construct(Loader $loader)
  {
    $this->discord = $loader->discord;
    $this->ready = $loader->ready;
    $this->lng = $loader->lng;

    $this->redis = $loader->redis;
    $this->loop = $loader->loop;
    $this->buffer = $loader->buffer;
  }

  protected function sendMessage(CommandInteraction $command, ?EmbedBuilder $embed = null, string $content = ''): void
  {
    $callback = InteractionCallbackBuilder::new()
      ->setFlags(MessageFlag::EPHEMERAL->value)
      ->setType(InteractionCallbackType::CHANNEL_MESSAGE_WITH_SOURCE);

    if (!empty($embed)) {
      $callback->addEmbed($embed);
    }

    if (!empty($content)) {
      $callback->setContent($content);
    }

    $command->createInteractionResponse($callback);
  }

  protected function isServerAdmin(InteractionCreate $interaction): bool
  {
    return hasPermission(bitmask: (int) ($interaction->member?->permissions ?? 0), permission: Permission::ADMINISTRATOR);
  }

  protected function setLocale(?string $locale): void
  {
    if (empty($locale)) return;

    $shortLocale = substr($locale, 0, 2);
    $this->lng->setLocale($shortLocale);
  }
}
