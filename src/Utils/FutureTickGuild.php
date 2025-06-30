<?php

declare(strict_types=1);

namespace Naneynonn\Utils;

use Ragnarok\Fenrir\Buffer\BufferInterface;
use Ragnarok\Fenrir\Constants\Events;

use Clue\React\Redis\RedisClient;
use React\EventLoop\Loop;

use Closure;

class FutureTickGuild implements BufferInterface
{
  private Closure $completeHandler;
  private RedisClient $redis;
  private string $queueKey;
  private bool $guildQueueWasFlushed = false;

  private int $processedGuildEvents = 0;

  public function __construct(RedisClient $redis, string $instanceId, int $shardId = 0)
  {
    $this->completeHandler = fn() => null;
    $this->redis = $redis;
    $this->queueKey = "guild:raw:queue:{$instanceId}:shard:{$shardId}";

    Loop::get()->addPeriodicTimer(0.2, function () {
      $this->redis->llen($this->queueKey)->then(function ($remaining) {
        $this->redis->rpop($this->queueKey)->then(function ($raw) use ($remaining) {
          if ($raw === null) {
            if (!$this->guildQueueWasFlushed) {
              echo "[FutureTickGuild] {$this->processedGuildEvents} processed, 0 remaining" . PHP_EOL;
              $this->guildQueueWasFlushed = true;
            }
            return;
          }

          ($this->completeHandler)($raw);
          $this->guildQueueWasFlushed = false;
          $this->processedGuildEvents++;

          if ($this->processedGuildEvents % 250 === 0) {
            echo "[FutureTickGuild] {$this->processedGuildEvents} processed, {$remaining} remaining" . PHP_EOL;
          }
        });
      });
    });
  }

  public function partialMessage(string $partial): void
  {
    if (str_contains($partial, Events::GUILD_CREATE)) {
      $this->redis->lpush($this->queueKey, $partial);
      return;
    }

    ($this->completeHandler)($partial);
  }

  public function onCompleteMessage(Closure $handler): void
  {
    $this->completeHandler = $handler;
  }

  public function additionalQueryData(): array
  {
    return [];
  }

  public function reset(): void
  {
    $this->redis->del($this->queueKey);
    $this->guildQueueWasFlushed = false;
    $this->processedGuildEvents = 0;
  }
}
