<?php

namespace Naneynonn;

use Ragnarok\Fenrir\Discord;
use Ragnarok\Fenrir\Bitwise\Bitwise;
use Ragnarok\Fenrir\InteractionHandler;

use Ragnarok\Fenrir\Gateway\Helpers\PresenceUpdateBuilder;
use Ragnarok\Fenrir\Gateway\Helpers\ActivityBuilder;
use Ragnarok\Fenrir\Gateway\Shard;
use Ragnarok\Fenrir\Gateway\Events\Ready;

use Ragnarok\Fenrir\Enums\Intent;
use Ragnarok\Fenrir\Enums\StatusType;
use Ragnarok\Fenrir\Enums\ActivityType;

use Naneynonn\Config;
use Naneynonn\Language;
use Naneynonn\Core\App\MonologTrim;

use Monolog\Logger;
use Monolog\Handler\RotatingFileHandler;
use Monolog\Level;

use DirectoryIterator;

class Init
{
  use Config;
  use Memory;

  private int $shardId = 0;
  private int $numShards = 1;

  public function __construct(?int $shardId = null, ?int $numShards = null)
  {
    $this->shardId = $shardId ?? $this->shardId;
    $this->numShards = $numShards ?? $this->numShards;
  }

  private function setDiscord(): Discord
  {
    // $log = (new Logger('Fenrir'))->pushHandler(new RotatingFileHandler("logs/fnrr-{$this->shardId}.log", 2, Level::Info));
    $log = (new Logger('Fenrir'))
      ->pushHandler(new RotatingFileHandler("logs/fnrr-{$this->shardId}.log", 2, Level::Info))
      ->pushProcessor(new MonologTrim());

    $discord = (new Discord(
      token: self::TOKEN,
      logger: $log,
    ))->withGateway(Bitwise::from(
      Intent::GUILD_MESSAGES,
      Intent::DIRECT_MESSAGES,
      Intent::MESSAGE_CONTENT,
      Intent::GUILDS
    ))->withRest();

    $discord->gateway->shard(new Shard($this->shardId, $this->numShards));

    return $discord;
  }

  public function getDiscord(): Discord
  {
    return $this->setDiscord();
  }

  public function getBotInfo(Ready $event): void
  {
    $guilds = count($event->guilds);

    $this->getMemoryUsage(text: "    ------

    Logged in as 
    {$event->user->username}
    {$event->user->id}
    
    ------

    Guilds: {$guilds}
    Memory use:");

    echo "    
    ------
    
    Shard: {$event->shard[0]}
    Started in -
    
    ------" . PHP_EOL;
  }

  public function loadCommands(InteractionHandler $interactionHandler, Language $lng, Discord $discord): void
  {
    $directory = __DIR__ . '/Commands';
    $loadedCount = 0;

    foreach (new DirectoryIterator($directory) as $file) {
      if (!$file->isDot() && $file->isFile() && $file->getExtension() === 'php') {
        $className = "Naneynonn\\Commands\\" . $file->getBasename('.php');
        $instance = new $className($discord, $lng);

        $interactionHandler->registerCommand($instance->register(), [$instance, 'handle']);

        $loadedCount++;
      }
    }

    // Вывод или логирование количества загруженных команд
    $this->getMemoryUsage(text: "[~] Total loaded commands: {$loadedCount} |");
  }

  public function setPresence(Discord $discord): void
  {
    $discord->gateway->updatePresence(
      PresenceUpdateBuilder::new()
        ->setStatus(StatusType::ONLINE)
        ->addActivity(
          ActivityBuilder::new()
            ->setName('discordtools.cc')
            ->setType(ActivityType::WATCHING)
        )
    );
  }
}
