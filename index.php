<?php

ini_set('memory_limit', '-1');

require_once 'vendor/autoload.php';
require_once 'functions/functions.php';

use Discord\Discord;

use Naneynonn\Settings;
use Naneynonn\Init;

$shard = isset($argv[1]) ? $argv[1] : null;
$shards = isset($argv[2]) ? $argv[2] : null;

$cfg = new Settings(shard: $shard, shards: $shards);
$discord = $cfg->getDiscordSettings();

$discord->on('ready', function (Discord $discord) use ($cfg) {
  $init = new Init(discord: $discord, load_time: $cfg->getTimeElapsed(), shard: $cfg->getShard());
  $init->getActivity();

  echo $init->getLoadInfo();

  // Load Events
  foreach (glob("events/*.php") as $filename) {
    require_once $filename;
  }

  // require 'setup.php';
});

$discord->on('reconnected', function () {
  echo "\n------ \nReconnected \n------";
});

$discord->run();
