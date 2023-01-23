<?php

require_once 'vendor/autoload.php';

require_once 'const/const_static.php';

foreach (glob("functions/*.php") as $filename) {
  require_once $filename;
}

// require_once 'functions.php';
require_once 'model.php';

use Discord\Discord;
use Discord\WebSockets\Intents;
use Discord\Parts\User\Activity;

$starttime = microtime(true);

$discord = new Discord([
  'token' => CONFIG['bot']['token'],
  'logger' => new \Monolog\Logger('New logger'),
  'intents' => Intents::getDefaultIntents() | Intents::MESSAGE_CONTENT,
]);

$discord->on('ready', function (Discord $discord) use ($starttime) {
  $lng = require 'lang/global.php';

  $endtime = number_format(microtime(true) - $starttime, 2);
  $guilds_count = $discord->guilds->count();
  $memory_use = convert(memory_get_usage(true));
  $users_count = $discord->users->count();
  $channels_count = getGuildsChannels(discord: $discord);

  echo "\n------ \nLogged in as \n{$discord->user->username} \n{$discord->user->id} \n------ \nGuilds: {$guilds_count} \nAll channels: {$channels_count} \nUsers: {$users_count} \nMemory use: {$memory_use} \n------ \nStarted in {$endtime}s \n------";

  $activity = $discord->factory(Activity::class, [
    'name' => $lng['activity'],
    'type' => Activity::TYPE_WATCHING
  ]);
  $discord->updatePresence($activity);

  // Load Events
  foreach (glob("events/*.php") as $filename) {
    require_once $filename;
  }
});

$discord->on('reconnected', function () {
  echo "\n------ \nReconnected \n------";
});

$discord->run();
