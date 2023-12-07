<?php

namespace Naneynonn\Events;

use Ragnarok\Fenrir\Discord;
use Ragnarok\Fenrir\Gateway\Events\MessageUpdate;
use Ragnarok\Fenrir\Constants\Events;
use Ragnarok\Fenrir\Gateway\Events\Ready;

use Naneynonn\Attr\EventHandlerFor;

use Naneynonn\MessageProcessor;
use Naneynonn\Language;
use Naneynonn\CacheHelper;

#[EventHandlerFor(Events::MESSAGE_UPDATE)]
class MessageUpdateEvent
{
  private Discord $discord;
  private Language $lng;
  private Ready $ready;
  private CacheHelper $cache;

  public function __construct(Discord $discord, Language $lng, Ready $ready, CacheHelper $cache)
  {
    $this->discord = $discord;
    $this->lng = $lng;
    $this->ready = $ready;
    $this->cache = $cache;
  }

  public function handle(MessageUpdate $event): void
  {
    if (empty($event)) return;

    $processor = new MessageProcessor(message: $event, lng: $this->lng, discord: $this->discord, cache: $this->cache);
    $processor->process();
  }
}
