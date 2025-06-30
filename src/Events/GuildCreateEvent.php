<?php

declare(strict_types=1);

namespace Naneynonn\Events;

use Ragnarok\Fenrir\Gateway\Events\GuildCreate;
use Ragnarok\Fenrir\Constants\Events;

use Naneynonn\Attr\EventHandlerFor;

use Naneynonn\Model;
use Naneynonn\Embeds;
use Naneynonn\Core\App\EventHelper;
use Ragnarok\Fenrir\Parts\UnavailableGuild;

#[EventHandlerFor(Events::GUILD_CREATE)]
class GuildCreateEvent extends EventHelper
{
  private const LOCALE = ['en', 'uk', 'ru'];

  public function handle(GuildCreate $event): void
  {
    $this->isStart($event->id)
      ? $this->handleStart($event)
      : $this->handleRegular($event);
  }

  private function isStart(string $guildId): bool
  {
    // Первый запуск бота
    // Возможно, проверить присутствие гильдии в списке гильдий из $this->ready.
    return in_array($guildId, array_map(fn($guild) => $guild->id, $this->ready->guilds));
  }

  private function handleStart(GuildCreate $event): void
  {
    // Логика, которая выполняется при первом запуске бота для данной гильдии.
    $this->addGuild(event: $event);
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
