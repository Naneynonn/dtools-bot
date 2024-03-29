<?php

namespace Naneynonn;

use Ragnarok\Fenrir\Discord;
use Ragnarok\Fenrir\Gateway\Events\Ready;
use Ragnarok\Fenrir\Command\GlobalCommandExtension;

use Naneynonn\Attr\EventHandlerFor;
use Naneynonn\Attr\Command;
use Naneynonn\Attr\SubCommand;

use Naneynonn\Language;
use Naneynonn\CacheHelper;
use Naneynonn\Memory;

use DirectoryIterator;
use ReflectionClass;

class Loader
{
  use Memory;

  private Discord $discord;
  private Language $lng;
  private Ready $ready;
  private CacheHelper $cache;

  public function __construct(Discord $discord, Language $lng, Ready $ready, CacheHelper $cache)
  {
    $this->discord = $discord;
    $this->lng = $lng;
    $this->ready = $ready;
    $this->cache = $cache;
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
      $instance = new $className($this->discord, $this->lng, $this->ready, $this->cache);

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

        $instance = new $className($this->discord, $this->lng, $this->ready, $this->cache);
        $this->discord->gateway->events->on($eventName, function (...$args) use ($instance) {
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

      $instance = new $className($this->discord, $this->lng, $this->ready, $this->cache);

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
          $instance->handle(...$args); // Предполагается, что метод handle() существует для обработки команды
        });

        $loadedCount++;
      }
    }

    $this->getMemoryUsage(text: "[~] Total loaded commands: {$loadedCount} |");
  }
}
