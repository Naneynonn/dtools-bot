<?php

declare(strict_types=1);

namespace Naneynonn\Events;

use Ragnarok\Fenrir\Gateway\Events\MessageCreate;
use Ragnarok\Fenrir\Constants\Events;

use Naneynonn\Attr\EventHandlerFor;
use Naneynonn\Automod\Automod;
use Naneynonn\Core\App\EventHelper;
use Naneynonn\Reactions\RandomReaction;

#[EventHandlerFor(Events::MESSAGE_CREATE)]
class MessageCreateEvent extends EventHelper
{
  public function handle(MessageCreate $message): void
  {
    if (empty($message->guild_id)) return;

    new Automod(message: $message, discord: $this->discord, lng: $this->lng, redis: $this->redis, loop: $this->loop)->handle();
    (new RandomReaction(discord: $this->discord, message: $message, redis: $this->redis))->get();
  }
}
