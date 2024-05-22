<?php

declare(strict_types=1);

namespace Naneynonn\Events;

use Ragnarok\Fenrir\Constants\Events;
use Ragnarok\Fenrir\Gateway\Events\Ready;

use Naneynonn\Attr\EventHandlerFor;
use Naneynonn\Core\App\EventHelper;
use Naneynonn\Init;

#[EventHandlerFor(Events::READY)]
class ReadyEvent extends EventHelper
{
  public function handle(Ready $event): void
  {
    $init = new Init();
    $init->setPresence(discord: $this->discord);
  }
}
