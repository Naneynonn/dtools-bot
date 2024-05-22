<?php

declare(strict_types=1);

namespace Naneynonn\Core\App;

use React\EventLoop\LoopInterface;

final class Buffer
{
  private const float TIMER = 30.0;
  private const int LIMIT = 10000;

  private array $buffers = [];
  private array $handlers = [];
  private LoopInterface $loop;

  public function __construct(LoopInterface $loop)
  {
    $this->loop = $loop;
    $this->loop->addPeriodicTimer(self::TIMER, function () {
      $this->flushAll(true);
    });
  }

  public function add(string $key, array $data): void
  {
    $this->buffers[$key][] = $data;
    if (count($this->buffers[$key]) >= self::LIMIT) {
      $this->flush($key, false); // Сброс при достижении лимита
    }
  }

  public function setHandler(string $key, callable $handler): void
  {
    $this->handlers[$key] = $handler;
  }

  private function flush(string $key, bool $timer = false): void
  {
    if (empty($this->buffers[$key]) || !isset($this->handlers[$key]) || (!$timer && count($this->buffers[$key]) < self::LIMIT)) return;

    call_user_func($this->handlers[$key], $this->buffers[$key]);
    $this->buffers[$key] = [];  // Очищаем буфер после обработки
  }

  private function flushAll(bool $timer): void
  {
    foreach ($this->buffers as $key => $_) {
      $this->flush($key, $timer);
    }
  }

  // Методы get/set для проверки или модификации состояния внутри класса
  // Можно добавить, если потребуется
}
