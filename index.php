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
  echo "Logged in as \n{$discord->user->username} \n{$discord->user->id} \nStarted in {$endtime}sec \n------";

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

$discord->run();
