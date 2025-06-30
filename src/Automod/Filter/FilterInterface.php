<?php

declare(strict_types=1);

namespace Naneynonn\Automod\Filter;

use React\Promise\PromiseInterface;

interface FilterInterface
{
  public function process(): PromiseInterface;
}
