<?php

use Discord\Discord;
use Discord\WebSockets\Event;
use Discord\Parts\Guild\Guild;

use Naneynonn\Model;

use ByteUnits\Metric;

$discord->on(Event::GUILD_UPDATE, function (Guild $guild, Discord $discord, ?Guild $oldGuild) {
  $model = new Model();
  $model->updateGuildInfo(name: $guild->name, is_active: true, icon: $guild->icon_hash, members_online: 0, members_all: $guild->member_count, server_id: $guild->id);

  $model->close();
  $guild = null;
  $oldGuild = null;
  $discord = null;

  echo 'Guild Update: ' . Metric::bytes(memory_get_usage())->format();
});
