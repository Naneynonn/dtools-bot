<?php

declare(strict_types=1);

namespace Naneynonn;

use ByteUnits\Metric;

trait Memory
{
  protected function getMemoryUsage(?string $text = null): void
  {
    if (!is_null($text)) $text .= ' ';
    echo $text . ' | Used: ' . Metric::bytes(memory_get_usage())->format() . ' Allocated: ' . Metric::bytes(memory_get_usage(true))->format() . PHP_EOL;
  }
}
