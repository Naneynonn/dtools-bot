<?php

ini_set('memory_limit', '-1');
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

use Ragnarok\Fenrir\Constants\Events;
use Ragnarok\Fenrir\Gateway\Events\Ready;
use Ragnarok\Fenrir\InteractionHandler;

use Naneynonn\Init;
use Naneynonn\Loader;
use Naneynonn\Language;
use Naneynonn\CacheHelper;

require './vendor/autoload.php';

$shardId = $argv[1] ?? null;
$numShards = $argv[2] ?? null;

$init = new Init(shardId: $shardId, numShards: $numShards);
$discord = $init->getDiscord();

$lng = new Language();
$cache = new CacheHelper();

$interactionHandler = new InteractionHandler();
$discord->registerExtension($interactionHandler);

// $init->loadCommands($interactionHandler, $lng, $discord);

$discord->gateway->events->once(Events::READY, function (Ready $event) use ($discord, $init, $lng, $cache) {
  $init->setPresence(discord: $discord);

  $loader = new Loader($discord, $lng, $event, $cache);
  $loader->loadEvents();
  $loader->loadCommands();

  $init->getBotInfo(event: $event);
});

$discord->gateway->open();
