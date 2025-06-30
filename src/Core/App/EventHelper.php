<?php

declare(strict_types=1);

namespace Naneynonn\Core\App;

use Ragnarok\Fenrir\Discord;
use Ragnarok\Fenrir\Gateway\Events\Ready;

use Naneynonn\Core\App\Buffer;

use Naneynonn\Core\App\Loader;
use Naneynonn\Memory;
use Naneynonn\Language;

use Clue\React\Redis\RedisClient;
use React\EventLoop\LoopInterface;

abstract class EventHelper
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
}
