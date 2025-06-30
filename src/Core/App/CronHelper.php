<?php

declare(strict_types=1);

namespace Naneynonn\Core\App;

use Carbon\Carbon;
use Ragnarok\Fenrir\Discord;
use Ragnarok\Fenrir\Gateway\Events\Ready;

use Naneynonn\Core\App\Buffer;

use Naneynonn\Core\App\Loader;
use Naneynonn\Memory;
use Naneynonn\Language;

use Clue\React\Redis\RedisClient;
use React\EventLoop\LoopInterface;

abstract class CronHelper
{
  use Memory;

  protected Discord $discord;
  protected Ready $ready;
  protected RedisClient $redis;
  protected Language $lng;

  protected LoopInterface $loop;
  protected Buffer $buffer;

  public function __construct(Loader $loader)
  {
    $this->discord = $loader->discord;
    $this->ready = $loader->ready;
    $this->lng = $loader->lng;

    $this->redis = $loader->redis;
    $this->loop = $loader->loop;
    $this->buffer = $loader->buffer;
  }

  protected function sendConsoleLog(string $info, string|float $currentTime, string|float $elapsedTime): void
  {
    $time = Carbon::now()->format('d-m-Y H:i:s');
    $elapsedTime = round($currentTime - $elapsedTime, 2);

    echo "[~] {$info}: {$time}" . PHP_EOL;
    echo "[~] Shard: {$this->getShard()}" . PHP_EOL;
    $this->getMemoryUsage(text: "[~] Elapsed Time: {$elapsedTime}s |");
  }

  protected function getShard(): int
  {
    return $this->ready->shard[0];
  }

  protected function getShards(): int
  {
    return $this->ready->shard[1];
  }
}
