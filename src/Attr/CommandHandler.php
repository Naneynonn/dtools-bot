<?php

namespace Naneynonn\Attr;

use Attribute;

#[Attribute]
class CommandHandler
{
  public function __construct(public string $commandName)
  {
  }
}
