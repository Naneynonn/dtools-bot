<?php

ini_set('memory_limit', '-1');
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

use Ragnarok\Fenrir\Constants\Events;
use Ragnarok\Fenrir\Gateway\Events\Ready;
use Ragnarok\Fenrir\InteractionHandler;

use Naneynonn\Init;
use Naneynonn\Core\App\Loader;
use Naneynonn\Language;

use Clue\React\Redis\RedisClient;
use React\EventLoop\Loop;

require './vendor/autoload.php';

$shardId = isset($argv[1]) ? (int)$argv[1] : null;
$numShards = isset($argv[2]) ? (int)$argv[2] : null;

try {
  $redis = new RedisClient('localhost:6379');
} catch (Throwable $e) {
  error_log('[Redis] Ошибка подключения: ' . $e->getMessage());
  exit(1);
}

$redis->on('error', static function (Throwable $e) {
  echo '[Redis] Error: ' . $e->getMessage() . PHP_EOL;
});

$redis->del("guild:raw:queue:shard:{$shardId}");
$lng = new Language();

$init = new Init(shardId: $shardId, numShards: $numShards, redis: $redis);
$discord = $init->getDiscord();

$interactionHandler = new InteractionHandler();
$discord->registerExtension($interactionHandler);

// require_once 'setup.php';

$discord->gateway->events->once(Events::READY, function (Ready $event) use ($discord, $init, $lng, $redis) {
  $init->setPresence(discord: $discord);

  $loader = new Loader(discord: $discord, lng: $lng, ready: $event, redis: $redis);
  $loader->loadEvents();
  $loader->loadCommands();
  $loader->loadCron();

  $init->getBotInfo(event: $event);
});

pcntl_async_signals(true);

pcntl_signal(SIGINT, static function () {
  echo "[BOT] Завершаем работу..." . PHP_EOL;
  Loop::stop();
  exit(0);
});

pcntl_signal(SIGTERM, static function () {
  echo "[BOT] Остановлен через SIGTERM" . PHP_EOL;
  Loop::stop();
  exit(0);
});


$discord->gateway->open();
