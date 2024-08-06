<?php

declare(strict_types=1);

namespace Naneynonn\Events;

use Ragnarok\Fenrir\Gateway\Events\MessageCreate;
use Ragnarok\Fenrir\Constants\Events;

use Naneynonn\Attr\EventHandlerFor;
use Naneynonn\Core\App\EventHelper;
use Naneynonn\Automod\ModerationHandler;

#[EventHandlerFor(Events::MESSAGE_CREATE)]
class MessageCreateEvent extends EventHelper
{
  public function handle(MessageCreate $message): void
  {
    (new ModerationHandler(message: $message, discord: $this->discord, lng: $this->lng, redis: $this->redis, loop: $this->loop))->handle();
  }
}
