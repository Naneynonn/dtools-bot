<?php

namespace Naneynonn\Attr;

use Attribute;

#[Attribute(Attribute::TARGET_METHOD)]
class SubCommand
{
  public function __construct(
    public string $name
  ) {
  }
}
