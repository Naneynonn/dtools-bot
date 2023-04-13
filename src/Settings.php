<?php

namespace Naneynonn;

use Discord\Discord;
use Discord\WebSockets\Intents;

use Monolog\Logger;
use Monolog\Handler\StreamHandler;
use Monolog\Level;

class Settings extends Config
{
  private Discord $discord;
  private $starttime;

  private ?int $shard;
  private ?int $shards;

  public function __construct(?int $shard = null, ?int $shards = null)
  {
    $this->shard = $shard;
    $this->shards = $shards;

    $this->starttime = microtime(true);
    $this->sharding();
    $this->setDiscordSettings();
  }

  private function sharding(): void
  {
    $this->shard = $this->shard ?? self::SHARD;
    $this->shards = $this->shards ?? self::SHARDS;
  }

  private function setDiscordSettings(): void
  {
    $this->discord = new Discord([
      'token' => self::TOKEN,
      'logger' => (new Logger('DiscordPHP'))->pushHandler(new StreamHandler("logs/bot-{$this->shard}.log", Level::Info)),
      'intents' => Intents::getDefaultIntents() | Intents::MESSAGE_CONTENT,
      'shardId' => $this->shard,
      'shardCount' => $this->shards,
      // 'cache' => $this->cache // Need in dphp v10
    ]);
  }

  public function getDiscordSettings(): Discord
  {
    return $this->discord;
  }

  public function getShard(): int
  {
    return $this->shard;
  }

  public function getTimeElapsed(): string
  {
    return number_format(microtime(true) - $this->starttime, 2) . 's';
  }
}
