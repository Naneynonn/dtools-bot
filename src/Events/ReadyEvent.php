<?php

namespace Naneynonn\Events;

use Ragnarok\Fenrir\Discord;
use Ragnarok\Fenrir\Constants\Events;
use Ragnarok\Fenrir\Gateway\Events\Ready;

use Naneynonn\Attr\EventHandlerFor;

use Naneynonn\Language;
use Naneynonn\CacheHelper;
use Naneynonn\Init;

#[EventHandlerFor(Events::READY)]
class ReadyEvent
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

  public function handle(Ready $event): void
  {
    $init = new Init();

    $init->setPresence(discord: $this->discord);
  }
}
