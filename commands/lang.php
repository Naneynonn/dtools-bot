<?php

use Discord\Parts\Interactions\Interaction;

use Naneynonn\Model;
use Naneynonn\Language;
use Naneynonn\Embeds;

use ByteUnits\Metric;

$discord->listenCommand('lang', function (Interaction $interaction) use ($discord) {
  $lng = new Language(lang: $interaction->locale);
  $iso = new Matriphe\ISO639\ISO639;

  if (!$interaction->member->getPermissions()->administrator && $interaction->guild->owner_id != $interaction->member->id) return $interaction->respondWithMessage(builder: Embeds::no_perm(lng: $lng), ephemeral: true);

  $model = new Model();
  $server = $model->getServerLang(id: $interaction->guild->id);

  $interaction->respondWithMessage(builder: Embeds::get_lang(lng: $lng, discord: $discord, lang: $iso->nativeByCode1(code: $server['lang'])), ephemeral: true);
  $model->close();

  echo "[-] Command | LANG: " . Metric::bytes(memory_get_usage())->format();
});

$discord->listenCommand(['lang', 'set'], function (Interaction $interaction) use ($discord) {
  $lng = new Language(lang: $interaction->locale);
  $iso = new Matriphe\ISO639\ISO639;

  if (!$interaction->member->getPermissions()->administrator && $interaction->guild->owner_id != $interaction->member->id) return $interaction->respondWithMessage(builder: Embeds::no_perm(lng: $lng), ephemeral: true);

  $lang = $interaction->data->options['set']->value;

  if (empty($iso->nativeByCode1(code: $lang))) return $interaction->respondWithMessage(builder: Embeds::error(text: 'Данного языка не существует'), ephemeral: true);

  $model = new Model();
  $model->setServerLang(server_id: $interaction->guild->id, lang: $lang);
  $model->close();

  $interaction->respondWithMessage(builder: Embeds::response(discord: $discord, color: $lng->get('color.success'), title: $lng->get('embeds.lang-set')), ephemeral: true);

  echo "[-] Command | LANG SET: " . Metric::bytes(memory_get_usage())->format();
});
