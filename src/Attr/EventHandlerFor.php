<?php

namespace Naneynonn\Attr;

use Attribute;

#[Attribute(Attribute::TARGET_CLASS)]
class EventHandlerFor
{
  public function __construct(public string $eventName)
  {
  }
}
