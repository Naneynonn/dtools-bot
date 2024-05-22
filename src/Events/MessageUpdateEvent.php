<?php

declare(strict_types=1);

namespace Naneynonn\Events;

use Ragnarok\Fenrir\Gateway\Events\MessageUpdate;
use Ragnarok\Fenrir\Constants\Events;

use Naneynonn\Attr\EventHandlerFor;
use Naneynonn\Core\App\EventHelper;
use Naneynonn\MessageProcessor;

#[EventHandlerFor(Events::MESSAGE_UPDATE)]
class MessageUpdateEvent extends EventHelper
{
  public function handle(MessageUpdate $event): void
  {
    $processor = new MessageProcessor(message: $event, lng: $this->lng, discord: $this->discord, redis: $this->redis, loop: $this->loop);
    $processor->process();
  }
}
