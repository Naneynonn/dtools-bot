<?php

namespace Naneynonn\Events;

use Ragnarok\Fenrir\Discord;
use Ragnarok\Fenrir\Gateway\Events\GuildDelete;
use Ragnarok\Fenrir\Constants\Events;
use Ragnarok\Fenrir\Gateway\Events\Ready;

use Naneynonn\Attr\EventHandlerFor;

use Naneynonn\Language;
use Naneynonn\Embeds;
use Naneynonn\Memory;
use Naneynonn\Model;

#[EventHandlerFor(Events::GUILD_DELETE)]
class GuildDeleteEvent
{
  use Memory;

  private Discord $discord;
  private Language $lng;
  private Ready $ready;

  public function __construct(Discord $discord, Language $lng, Ready $ready)
  {
    $this->discord = $discord;
    $this->lng = $lng;
    $this->ready = $ready;
  }

  public function handle(GuildDelete $event): void
  {
    if (empty($event) || is_object_empty($event)) return;


    $existingIds = array_column($this->ready->guilds, 'id');
    $guildKey = array_search($event->id, $existingIds, true);

    if ($guildKey !== false) {
      // Удаляем гильдию из массива, если она найдена
      unset($this->ready->guilds[$guildKey]);

      // Чтобы переиндексировать массив после удаления элемента, если это необходимо
      $this->ready->guilds = array_values($this->ready->guilds);

      Embeds::errLogGuild(guild: $event);
    }


    $model = new Model();

    $model->deleteGuild(server_id: $event->id);

    $model->close();

    $this->getMemoryUsage(text: "[~] Events::GUILD_DELETE | ID: {$event->id}");
  }
}