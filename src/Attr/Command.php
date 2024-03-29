<?php

namespace Naneynonn\Attr;

use Attribute;

#[Attribute(Attribute::TARGET_CLASS)]
class Command
{
  public function __construct(
    public string $name
  ) {
  }
}
