<?php

namespace Naneynonn\Events;

use Ragnarok\Fenrir\Discord;
use Ragnarok\Fenrir\Gateway\Events\MessageUpdate;
use Ragnarok\Fenrir\Constants\Events;
use Ragnarok\Fenrir\Gateway\Events\Ready;

use Naneynonn\Attr\EventHandlerFor;

use Naneynonn\MessageProcessor;
use Naneynonn\Language;

#[EventHandlerFor(Events::MESSAGE_UPDATE)]
class MessageUpdateEvent
{
  private Discord $discord;
  private Language $lng;
  private Ready $ready;

  public function __construct(Discord $discord, Language $lng, Ready $ready)
  {
    $this->discord = $discord;
    $this->lng = $lng;
    $this->ready = $ready;
  }

  public function handle(MessageUpdate $event): void
  {
    if (empty($event)) return;

    $processor = new MessageProcessor(message: $event, lng: $this->lng, discord: $this->discord);
    $processor->process();
  }
}
