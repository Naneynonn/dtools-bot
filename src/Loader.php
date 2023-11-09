<?php

namespace Naneynonn;

use Ragnarok\Fenrir\Discord;
use Ragnarok\Fenrir\Gateway\Events\Ready;

use Naneynonn\Attr\EventHandlerFor;
use Naneynonn\Language;

use DirectoryIterator;
use ReflectionClass;

class Loader
{
  use Memory;

  private Discord $discord;
  private Language $lng;
  private Ready $ready;

  public function __construct(Discord $discord, Language $lng, Ready $ready)
  {
    $this->discord = $discord;
    $this->lng = $lng;
    $this->ready = $ready;
  }

  public function loadEvents(): void
  {
    $directory = __DIR__ . '/Events';
    $loadedCount = 0;

    foreach (new DirectoryIterator($directory) as $file) {
      if (!$file->isDot() && $file->isFile() && $file->getExtension() === 'php') {
        $className = "Naneynonn\\Events\\" . $file->getBasename('.php');
        $reflection = new ReflectionClass($className);

        $attributes = $reflection->getAttributes(EventHandlerFor::class);
        foreach ($attributes as $attribute) {
          $eventName = $attribute->newInstance()->eventName;

          $instance = new $className($this->discord, $this->lng, $this->ready);
          $this->discord->gateway->events->on($eventName, function (...$args) use ($instance) {
            call_user_func([$instance, 'handle'], ...$args);
          });

          $loadedCount++;
        }
      }
    }

    $this->getMemoryUsage(text: "[~] Total loaded events: {$loadedCount} |");
  }
}
