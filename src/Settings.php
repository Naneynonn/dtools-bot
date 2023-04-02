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

  public function __construct()
  {
    $this->starttime = microtime(true);
    $this->setDiscordSettings();
  }

  private function setDiscordSettings(): void
  {
    $this->discord = new Discord([
      'token' => self::TOKEN,
      'logger' => (new Logger('DiscordPHP'))->pushHandler(new StreamHandler('logs/bot.log', Level::Info)),
      'intents' => Intents::getDefaultIntents() | Intents::MESSAGE_CONTENT,
      'shardId' => self::getShardNumber(),
      'shardCount' => self::SHARDS,
      // 'cache' => $this->cache // Need in dphp v10
    ]);
  }

  public function getDiscordSettings(): Discord
  {
    return $this->discord;
  }

  public function getShard(): int
  {
    return self::getShardNumber();
  }

  public function getTimeElapsed(): string
  {
    return number_format(microtime(true) - $this->starttime, 2) . 's';
  }
}
