<?php

declare(strict_types=1);

namespace Naneynonn\Attr;

use Attribute;

#[Attribute]
class CronExpression
{
  public function __construct(
    public string $expression,
    public int|float $ttl = 60
  ) {}
}
