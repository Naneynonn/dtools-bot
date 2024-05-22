<?php

declare(strict_types=1);

namespace Naneynonn\Events;

use Ragnarok\Fenrir\Gateway\Events\GuildUpdate;
use Ragnarok\Fenrir\Constants\Events;
use Ragnarok\Fenrir\Parts\Guild;

use Naneynonn\Attr\EventHandlerFor;

use Naneynonn\Model;
use Naneynonn\Core\App\EventHelper;

#[EventHandlerFor(Events::GUILD_UPDATE)]
class GuildUpdateEvent extends EventHelper
{
  public function handle(GuildUpdate $event): void
  {
    $this->discord->rest->guild->get(guildId: $event->id, withCounts: true)->then(function (Guild $guild) {
      // TODO: не хватает онлайна
      $model = new Model();
      $model->updateGuildInfo(name: $guild->name, is_active: true, icon: $guild->icon ?? null, members_online: $guild->approximate_presence_count, members_all: $guild->approximate_member_count, server_id: $guild->id);
      $model->close();
    });

    $this->getMemoryUsage(text: "[~] Events::GUILD_UPDATE | ID: {$event->id}");
  }
}
