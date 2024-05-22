<?php

declare(strict_types=1);

namespace Naneynonn;

use Ragnarok\Fenrir\Discord;
use Ragnarok\Fenrir\Gateway\Events\Ready;
use Ragnarok\Fenrir\Command\GlobalCommandExtension;

use Naneynonn\Attr\EventHandlerFor;
use Naneynonn\Attr\Command;
use Naneynonn\Attr\SubCommand;

use Naneynonn\Language;
use Naneynonn\Memory;
use Naneynonn\Core\App\Buffer;

use Clue\React\Redis\LazyClient as RedisClient;
use React\EventLoop\Loop;
use React\EventLoop\LoopInterface;

use function Naneynonn\isObjectEmpty;

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
      'directory' => __DIR__ . "/{$name}",
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

        $instance = new $className($this);
        $this->discord->gateway->events->on($eventName, function (...$args) use ($instance) {
          if (empty($args) || isObjectEmpty($args[0])) return;
          $instance->handle(...$args);
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

      $instance = new $className($this);

      $hasSubCommands = false;

      foreach ($reflection->getMethods() as $method) {
        $subCommandAttributes = $method->getAttributes(SubCommand::class);
        foreach ($subCommandAttributes as $attribute) {
          $hasSubCommands = true;
          $subCommandData = $attribute->newInstance();
          $eventName = $globalCommandName . '.' . $subCommandData->name;
          $methodName = $method->getName();

          $commandExtension->on($eventName, function (...$args) use ($instance, $methodName) {
            $instance->$methodName(...$args);
          });

          $loadedCount++;
        }
      }

      // Для классов, представляющих одну глобальную команду без подкоманд
      if ($globalCommandName && !$hasSubCommands) {
        $commandExtension->on($globalCommandName, function (...$args) use ($instance) {
          if (empty($args) || isObjectEmpty($args[0])) return;
          $instance->handle(...$args); // Предполагается, что метод handle() существует для обработки команды
        });

        $loadedCount++;
      }
    }

    $this->getMemoryUsage(text: "[~] Total loaded commands: {$loadedCount} |");
  }
}
