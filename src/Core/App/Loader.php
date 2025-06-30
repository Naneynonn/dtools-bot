<?php

declare(strict_types=1);

namespace Naneynonn\Core\App;

use Ragnarok\Fenrir\Discord;
use Ragnarok\Fenrir\Gateway\Events\Ready;
use Ragnarok\Fenrir\Command\GlobalCommandExtension;

use Naneynonn\Attr\CronExpression;
use Naneynonn\Attr\EventHandlerFor;
use Naneynonn\Attr\Command;
use Naneynonn\Attr\SubCommand;

use Naneynonn\Language;
use Naneynonn\Memory;
use Naneynonn\Core\App\Buffer;

use React\EventLoop\Loop;
use React\EventLoop\LoopInterface;
use React\Promise\PromiseInterface;

use Clue\React\Redis\RedisClient;
use WyriHaximus\React\Cron;
use WyriHaximus\React\Cron\Action;

use function React\Promise\resolve;

use DirectoryIterator;
use ReflectionClass;

class Loader
{
  use Memory;

  public Discord $discord;
  public Language $lng;
  public Ready $ready;
  public RedisClient $redis;
  public LoopInterface $loop;
  public Buffer $buffer;

  public function __construct(Discord $discord, Language $lng, Ready $ready, RedisClient $redis)
  {
    $this->discord = $discord;
    $this->lng = $lng;
    $this->ready = $ready;
    $this->redis = $redis;

    $this->loop = Loop::get();
    $this->buffer = new Buffer($this->loop);
  }

  private function setName(string $name): array
  {
    return [
      'directory' => __DIR__ . "/../../{$name}",
      'namespace' => "\\Naneynonn\\{$name}\\"
    ];
  }

  private function isFile(DirectoryIterator $file): bool
  {
    return !$file->isDot() && $file->isFile() && $file->getExtension() === 'php';
  }

  private function loadFromDirectory(string $directory, string $namespace, callable $registrationCallback): void
  {
    $loadedCount = 0;

    foreach (new DirectoryIterator($directory) as $file) {
      if (!$this->isFile(file: $file)) continue;

      $className = $namespace . $file->getBasename('.php');
      $instance = new $className($this);

      $loadedCount += $registrationCallback($instance, new ReflectionClass($className));
    }

    $this->getMemoryUsage(text: "[~] Total loaded: {$loadedCount} |");
  }

  public function loadEvents(): void
  {
    $details = $this->setName(name: 'Events');
    $loadedCount = 0;

    foreach (new DirectoryIterator($details['directory']) as $file) {
      if (!$this->isFile(file: $file)) continue;

      $className = $details['namespace'] . $file->getBasename('.php');
      $reflection = new ReflectionClass($className);

      $attributes = $reflection->getAttributes(EventHandlerFor::class);
      foreach ($attributes as $attribute) {
        $eventName = $attribute->newInstance()->eventName;

        $this->discord->gateway->events->on($eventName, function (...$args) use ($className) {
          if (empty($args) || !isset($args[0])) return;
          (new $className($this))->handle(...$args);
        });

        $loadedCount++;
      }
    }

    $this->getMemoryUsage(text: "[~] Total loaded events: {$loadedCount} |");
  }

  public function loadCommands(): void
  {
    $details = $this->setName(name: 'Commands');
    $loadedCount = 0;

    $commandExtension = new GlobalCommandExtension();
    $this->discord->registerExtension($commandExtension);

    foreach (new DirectoryIterator($details['directory']) as $file) {
      if (!$this->isFile(file: $file)) continue;

      $className = $details['namespace'] . $file->getBasename('.php');
      $reflection = new ReflectionClass($className);

      // Проверка наличия глобального атрибута команды
      $globalCommandAttributes = $reflection->getAttributes(Command::class);
      $globalCommandName = $globalCommandAttributes ? $globalCommandAttributes[0]->newInstance()->name : null;

      $hasSubCommands = false;

      foreach ($reflection->getMethods() as $method) {
        $subCommandAttributes = $method->getAttributes(SubCommand::class);
        foreach ($subCommandAttributes as $attribute) {
          $hasSubCommands = true;
          $subCommandData = $attribute->newInstance();
          $eventName = $globalCommandName . '.' . $subCommandData->name;
          $methodName = $method->getName();

          $commandExtension->on($eventName, function (...$args) use ($className, $methodName) {
            (new $className($this))->$methodName(...$args);
          });

          $loadedCount++;
        }
      }

      // Для классов, представляющих одну глобальную команду без подкоманд
      if ($globalCommandName && !$hasSubCommands) {
        $commandExtension->on($globalCommandName, function (...$args) use ($className) {
          if (empty($args) || !isset($args[0])) return;
          (new $className($this))->handle(...$args); // Предполагается, что метод handle() существует для обработки команды
        });

        $loadedCount++;
      }
    }

    $this->getMemoryUsage(text: "[~] Total loaded commands: {$loadedCount} |");
  }

  public function loadCron(): void
  {
    $details = $this->setName(name: 'Cron');
    $loadedCount = 0;
    $actions = [];

    foreach (new DirectoryIterator($details['directory']) as $file) {
      if (!$this->isFile(file: $file)) continue;

      $className = $details['namespace'] . $file->getBasename('.php');
      $reflection = new ReflectionClass($className);

      $attributes = $reflection->getAttributes(CronExpression::class);
      foreach ($attributes as $attribute) {
        $expression = $attribute->newInstance()->expression;
        $ttl = $attribute->newInstance()->ttl;

        $actions[] = new Action(
          key: $className, // Используйте имя класса как идентификатор
          mutexTtl: $ttl,
          expression: $expression,
          performer: function () use ($className): PromiseInterface {
            $instance = new $className($this);
            $instance->handle();
            return resolve(true);
          }
        );
      }

      $loadedCount++;
    }

    Cron::create(...$actions);

    $this->getMemoryUsage(text: "[~] Total loaded cron jobs: {$loadedCount} |");
  }
}
