<?php

declare(strict_types=1);

namespace Naneynonn\Events;

use Ragnarok\Fenrir\Gateway\Events\GuildDelete;
use Ragnarok\Fenrir\Constants\Events;

use Naneynonn\Attr\EventHandlerFor;

use Naneynonn\Embeds;
use Naneynonn\Model;
use Naneynonn\Core\App\EventHelper;

#[EventHandlerFor(Events::GUILD_DELETE)]
class GuildDeleteEvent extends EventHelper
{
  public function handle(GuildDelete $event): void
  {
    if ($event->unavailable) return;

    $existingIds = array_column($this->ready->guilds, 'id');
    $guildKey = array_search($event->id, $existingIds, true);

    $model = new Model();
    $guild = $model->deleteGuild(server_id: $event->id);
    $model->close();

    if ($guildKey !== false) {
      // Удаляем гильдию из массива, если она найдена
      unset($this->ready->guilds[$guildKey]);

      // Чтобы переиндексировать массив после удаления элемента, если это необходимо
      $this->ready->guilds = array_values($this->ready->guilds);

      if ($guild) {
        Embeds::errLogGuild(event: $event, guild: $guild);
      }
    }

    $this->getMemoryUsage(text: "[~] Events::GUILD_DELETE | ID: {$event->id}");
  }
}
