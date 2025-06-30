<?php

declare(strict_types=1);

namespace Naneynonn\Events;

use Ragnarok\Fenrir\Gateway\Events\MessageUpdate;
use Ragnarok\Fenrir\Constants\Events;

use Naneynonn\Attr\EventHandlerFor;
use Naneynonn\Core\App\EventHelper;
use Naneynonn\Automod\Automod;

#[EventHandlerFor(Events::MESSAGE_UPDATE)]
class MessageUpdateEvent extends EventHelper
{
  public function handle(MessageUpdate $event): void
  {
    new Automod(message: $event, discord: $this->discord, lng: $this->lng, redis: $this->redis, loop: $this->loop)->handle();
  }
}
