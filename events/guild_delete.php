<?php

use Discord\Discord;
use Discord\Parts\Embed\Embed;
use Discord\WebSockets\Event;

use Naneynonn\Embeds;
use Naneynonn\Model;

$discord->on(Event::GUILD_DELETE, function (object $guild, Discord $discord, bool $unavailable) {
  $model = new Model();

  if ($unavailable) {
    $model->deleteGuild(server_id: $guild->id);
    Embeds::err_log_servers(guild: $guild);
  } else {
    $model->updateGuildInfo(name: $guild->name, is_active: false, icon: $guild->icon_hash, members_online: 0, members_all: $guild->member_count, server_id: $guild->id);
    Embeds::log_servers(guild: $guild, new: false);
  }

  $model->close();
});
