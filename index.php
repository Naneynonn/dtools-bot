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

use function React\Promise\set_rejection_handler;

require './vendor/autoload.php';

// 1️⃣ Необработанные Promise rejections (async/then)
set_rejection_handler(static function (Throwable $e): void {
  echo PHP_EOL;
  echo "[PROMISE][UNHANDLED] " . $e::class . PHP_EOL;
  echo $e->getMessage() . PHP_EOL;
  echo $e->getTraceAsString() . PHP_EOL;
  echo str_repeat("-", 80) . PHP_EOL;
});

// 2️⃣ Необработанные синхронные исключения
set_exception_handler(static function (Throwable $e): void {
  echo PHP_EOL;
  echo "[EXCEPTION][FATAL] " . $e::class . PHP_EOL;
  echo $e->getMessage() . PHP_EOL;
  echo $e->getTraceAsString() . PHP_EOL;
  echo str_repeat("=", 80) . PHP_EOL;

  Loop::stop();
});

// 3️⃣ Warnings / Notices
set_error_handler(static function (int $severity, string $message, string $file, int $line): bool {
  if (!(error_reporting() & $severity)) {
    return false;
  }

  echo PHP_EOL;
  echo "[PHP ERROR][{$severity}] {$message}" . PHP_EOL;
  echo "File: {$file}:{$line}" . PHP_EOL;
  echo str_repeat("-", 80) . PHP_EOL;

  return false; // пусть PHP продолжит стандартное поведение
});

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
