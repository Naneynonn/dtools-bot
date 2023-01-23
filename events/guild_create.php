<?php

use Discord\Discord;
use Discord\WebSockets\Event;
use Discord\Parts\Guild\Guild;

$discord->on(Event::GUILD_CREATE, function (Guild $guild, Discord $discord) {
  $locale = ['en', 'uk', 'en'];

  $guild->preferred_locale = substr($guild->preferred_locale, 0, 2);
  $guild->preferred_locale = in_array($guild->preferred_locale, $locale) ? $guild->preferred_locale : $locale[0];

  $model = new DB();
  $model->createGuildInfo(name: $guild->name, lang: $guild->preferred_locale, icon: $guild->icon_hash, members_online: 0, members_all: $guild->member_count, server_id: $guild->id);

  whLogServer(guild: $guild);

  unset($model);
});
