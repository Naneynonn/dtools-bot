<?php

declare(strict_types=1);

namespace Naneynonn\Events;

use Ragnarok\Fenrir\Gateway\Events\MessageCreate;
use Ragnarok\Fenrir\Constants\Events;

use Naneynonn\Attr\EventHandlerFor;
use Naneynonn\MessageProcessor;
use Naneynonn\Core\App\EventHelper;

#[EventHandlerFor(Events::MESSAGE_CREATE)]
class MessageCreateEvent extends EventHelper
{
  public function handle(MessageCreate $message): void
  {
    $processor = new MessageProcessor(message: $message, lng: $this->lng, discord: $this->discord, redis: $this->redis, loop: $this->loop);
    $processor->process();
  }
}
