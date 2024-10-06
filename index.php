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

use Clue\React\Redis\Factory;

require './vendor/autoload.php';

$shardId = isset($argv[1]) ? (int)$argv[1] : null;
$numShards = isset($argv[2]) ? (int)$argv[2] : null;

$init = new Init(shardId: $shardId, numShards: $numShards);
$discord = $init->getDiscord();

$lng = new Language();

$factory = new Factory();
$redis = $factory->createLazyClient('localhost:6379');

$interactionHandler = new InteractionHandler();
$discord->registerExtension($interactionHandler);

// require_once 'setup.php';

$discord->gateway->events->once(Events::READY, function (Ready $event) use ($discord, $init, $lng, $redis) {
  $init->setPresence(discord: $discord);

  $loader = new Loader($discord, $lng, $event, $redis);

  $loader->loadCron();
  $loader->loadEvents();
  $loader->loadCommands();

  $init->getBotInfo(event: $event);
});

$discord->gateway->open();
