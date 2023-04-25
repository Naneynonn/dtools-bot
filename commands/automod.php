<?php

use Discord\Parts\Interactions\Interaction;

use Naneynonn\Model;
use Naneynonn\Language;
use Naneynonn\Embeds;

use ByteUnits\Metric;

$discord->listenCommand(['automod', 'badwords'], function (Interaction $interaction) use ($discord) {
  $lng = new Language(lang: $interaction->locale);

  if (!$interaction->member->getPermissions()->administrator && $interaction->guild->owner_id != $interaction->member->id) return $interaction->respondWithMessage(builder: Embeds::no_perm(lng: $lng), ephemeral: true);

  toggleCommand(interaction: $interaction, lng: $lng, discord: $discord, type: 'badwords');
});

$discord->listenCommand(['automod', 'caps'], function (Interaction $interaction) use ($discord) {
  $lng = new Language(lang: $interaction->locale);

  if (!$interaction->member->getPermissions()->administrator && $interaction->guild->owner_id != $interaction->member->id) return $interaction->respondWithMessage(builder: Embeds::no_perm(lng: $lng), ephemeral: true);

  toggleCommand(interaction: $interaction, lng: $lng, discord: $discord, type: 'caps');
});

$discord->listenCommand(['automod', 'replace'], function (Interaction $interaction) use ($discord) {
  $lng = new Language(lang: $interaction->locale);

  if (!$interaction->member->getPermissions()->administrator && $interaction->guild->owner_id != $interaction->member->id) return $interaction->respondWithMessage(builder: Embeds::no_perm(lng: $lng), ephemeral: true);

  toggleCommand(interaction: $interaction, lng: $lng, discord: $discord, type: 'replace');
});

$discord->listenCommand(['automod', 'zalgo'], function (Interaction $interaction) use ($discord) {
  $lng = new Language(lang: $interaction->locale);

  if (!$interaction->member->getPermissions()->administrator && $interaction->guild->owner_id != $interaction->member->id) return $interaction->respondWithMessage(builder: Embeds::no_perm(lng: $lng), ephemeral: true);

  toggleCommand(interaction: $interaction, lng: $lng, discord: $discord, type: 'zalgo');
});

$discord->listenCommand(['automod', 'filter'], function (Interaction $interaction) use ($discord) {
  $lng = new Language(lang: $interaction->locale);

  if (!$interaction->member->getPermissions()->administrator && $interaction->guild->owner_id != $interaction->member->id) return $interaction->respondWithMessage(builder: Embeds::no_perm(lng: $lng), ephemeral: true);

  $is_enable = $interaction->data->options['filter']->options['enable']->value;

  if ($is_enable) {
    $status = $lng->get('embeds.automod-filter-on');
    $color = $lng->get('color.success');
  } else {
    $status = $lng->get('embeds.automod-filter-off');
    $color = $lng->get('color.grey');
  }

  $model = new Model();
  $model->automodToggle(server_id: $interaction->guild->id, is_enable: $is_enable);
  $model->close();

  $interaction->respondWithMessage(builder: Embeds::response(discord: $discord, color: $color, title: $status), ephemeral: true);

  echo "[-] Command | automod filter: " . Metric::bytes(memory_get_usage())->format();
});

$discord->listenCommand(['automod', 'log'], function (Interaction $interaction) use ($discord) {
  $lng = new Language(lang: $interaction->locale);

  if (!$interaction->member->getPermissions()->administrator && $interaction->guild->owner_id != $interaction->member->id) return $interaction->respondWithMessage(builder: Embeds::no_perm(lng: $lng), ephemeral: true);

  $id = $interaction->data->options['log']->options['channel']->value;

  $model = new Model();
  $model->updateAutomodLogChannel(server_id: $interaction->guild->id, log_channel: $id);
  $model->close();

  $interaction->respondWithMessage(builder: Embeds::response(discord: $discord, color: $lng->get('color.success'), title: $lng->get('embeds.automod-log')), ephemeral: true);

  echo "[-] Command | automod log: " . Metric::bytes(memory_get_usage())->format();
});
