<?php

declare(strict_types=1);

namespace Naneynonn\Core\App;

use Monolog\Level;
use Monolog\LogRecord;

final class MonologTrim
{
  public function __invoke(LogRecord $record): LogRecord
  {
    $message = $record->message;

    if ($record->level === Level::Warning) {
      $message = preg_replace('/} in \/.*$/s', '}', $message);
    }

    return $record->with(message: $message);
  }
}
