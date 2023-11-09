<?php

namespace Naneynonn\Events;

use Ragnarok\Fenrir\Discord;
use Ragnarok\Fenrir\Gateway\Events\GuildCreate;
use Ragnarok\Fenrir\Constants\Events;
use Ragnarok\Fenrir\Gateway\Events\Ready;

use Naneynonn\Attr\EventHandlerFor;

use Naneynonn\Language;
use Naneynonn\Memory;
use Naneynonn\Model;
use Naneynonn\Embeds;

use Ragnarok\Fenrir\Parts\UnavailableGuild;

#[EventHandlerFor(Events::GUILD_CREATE)]
class GuildCreateEvent
{
  use Memory;

  private Discord $discord;
  private Language $lng;
  private Ready $ready;

  private const LOCALE = ['en', 'uk', 'ru'];

  public function __construct(Discord $discord, Language $lng, Ready $ready)
  {
    $this->discord = $discord;
    $this->lng = $lng;
    $this->ready = $ready;
  }

  public function handle(GuildCreate $event): void
  {
    // if (empty($event->id)) {
    //   print_r($event);
    //   die();
    // }

    if ($this->isStart($event->id)) {
      $this->handleStart($event);
    } else {
      $this->handleRegular($event);
    }
  }

  private function isStart(string $guildId): bool
  {
    // Ваша логика проверки: является ли это запуском для данной гильдии.
    // Возможно, проверить присутствие гильдии в списке гильдий из $this->ready.
    return in_array($guildId, array_map(fn ($guild) => $guild->id, $this->ready->guilds));
  }

  private function handleStart(GuildCreate $event): void
  {
    // Логика, которая выполняется при первом запуске бота для данной гильдии.

    $this->addGuild(event: $event);

    // $this->getMemoryUsage(text: "[~] STARTUP::GUILD_CREATE | ID: {$event->id}");
  }

  private function handleRegular(GuildCreate $event): void
  {
    // Обычная логика для события GUILD_CREATE.

    $this->addGuild(event: $event);
    Embeds::sendLogGuild(guild: $event);

    $existingIds = array_column($this->ready->guilds, 'id');
    if (!in_array($event->id, $existingIds, true)) {
      $newGuild = new UnavailableGuild();
      $newGuild->id = $event->id;
      $newGuild->unavailable = true; // или false, в зависимости от вашей логики

      $this->ready->guilds[] = $newGuild;
    }

    $this->getMemoryUsage(text: "[~] Events::GUILD_CREATE | ID: {$event->id}");
  }

  private function getLocale(GuildCreate $event): string
  {
    $sort = $event->preferred_locale = substr($event->preferred_locale, 0, 2);
    $locale = in_array($sort, self::LOCALE) ? $sort : self::LOCALE[0];

    return $locale;
  }

  private function addGuild(GuildCreate $event): void
  {
    $locale = $this->getLocale(event: $event);

    $model = new Model();
    $model->createGuildInfo(name: $event->name, lang: $locale, icon: $event->icon ?? null, members_online: 0, members_all: $event->member_count, server_id: $event->id);
    $model->close();
  }
}
