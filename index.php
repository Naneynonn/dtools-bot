<?php

use Ragnarok\Fenrir\Constants\Events;
use Ragnarok\Fenrir\Gateway\Events\Ready;
use Ragnarok\Fenrir\InteractionHandler;

use Naneynonn\Init;
use Naneynonn\Loader;
use Naneynonn\Language;

require './vendor/autoload.php';

$shardId = $argv[1] ?? null;
$numShards = $argv[2] ?? null;

$init = new Init(shardId: $shardId, numShards: $numShards);
$discord = $init->getDiscord();

$lng = new Language();


$interactionHandler = new InteractionHandler();
$discord->registerExtension($interactionHandler);

$init->loadCommands($interactionHandler, $lng, $discord);

$discord->gateway->events->once(Events::READY, function (Ready $event) use ($discord, $init, $lng) {
  $init->setPresence(discord: $discord);

  $loader = new Loader($discord, $lng, $event);
  $loader->loadEvents();

  $init->getBotInfo(event: $event);
});

$discord->gateway->open();
