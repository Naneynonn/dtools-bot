<?php

ini_set('memory_limit', '-1');

require_once 'vendor/autoload.php';

require_once 'const/const_static.php';

foreach (glob("functions/*.php") as $filename) {
  require_once $filename;
}

require_once 'model.php';

use Discord\Discord;

use Naneynonn\Settings;
use Naneynonn\Init;

$cfg = new Settings();
$discord = $cfg->getDiscordSettings();

$discord->on('ready', function (Discord $discord) use ($cfg) {
  $init = new Init(discord: $discord, load_time: $cfg->getTimeElapsed());
  $init->getActivity();

  echo $init->getLoadInfo();

  $lng = require 'lang/global.php';

  // Load Events
  foreach (glob("events/*.php") as $filename) {
    require_once $filename;
  }

  require 'setup.php';
});

$discord->on('reconnected', function () {
  echo "\n------ \nReconnected \n------";
});

$discord->run();
