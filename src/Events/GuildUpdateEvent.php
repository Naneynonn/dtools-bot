<?php

namespace Naneynonn\Events;

use Ragnarok\Fenrir\Discord;
use Ragnarok\Fenrir\Gateway\Events\GuildUpdate;
use Ragnarok\Fenrir\Constants\Events;
use Ragnarok\Fenrir\Gateway\Events\Ready;
use Ragnarok\Fenrir\Parts\Guild;

use Naneynonn\Attr\EventHandlerFor;

use Naneynonn\Language;
use Naneynonn\Memory;
use Naneynonn\Model;
use Naneynonn\CacheHelper;

#[EventHandlerFor(Events::GUILD_UPDATE)]
class GuildUpdateEvent
{
  use Memory;

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

  public function handle(GuildUpdate $event): void
  {
    if (empty($event) || is_object_empty($event)) return;

    $this->discord->rest->guild->get(guildId: $event->id, withCounts: true)->then(function (Guild $guild) {
      // TODO: не хватает онлайна
      $model = new Model();
      $model->updateGuildInfo(name: $guild->name, is_active: true, icon: $guild->icon ?? null, members_online: $guild->approximate_presence_count, members_all: $guild->approximate_member_count, server_id: $guild->id);
      $model->close();
    });

    $this->getMemoryUsage(text: "[~] Events::GUILD_UPDATE | ID: {$event->id}");
  }
}
