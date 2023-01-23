<?php

use Discord\Discord;
use Discord\WebSockets\Event;

$discord->on(Event::GUILD_DELETE, function (object $guild, Discord $discord, bool $unavailable) {
  $model = new DB();

  if ($unavailable) {
    $model->deleteGuild(server_id: $guild->id);
    whErrLogServer(guild: $guild);
  } else {
    $model->updateGuildInfo(name: $guild->name, is_active: false, icon: $guild->icon_hash, members_online: 0, members_all: $guild->member_count, server_id: $guild->id);
    whLogServer(guild: $guild, new: false);
  }

  unset($model);
});
