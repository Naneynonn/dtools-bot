<?php

declare(strict_types=1);

namespace Naneynonn\Core\Enums;

enum Emoji: string
{
  case ERROR = '<:disable_danger:1264570171339837600>';
  case ERROR_DISABLED = '<:disable_info:1264570205582000223>';

  case INFO = '<:info_info:1264577718482112535>';
  case INFO_DANGER = '<:info_danger:1264577592648798299>';
  case INFO_SUCCESS = '<:info_success:1264577730666692739>';
  case INFO_WARNING = '<:info_warning:1264577739407622247>';

  case ENABLE = '<:enable_success:1264581172348977193>';
  case ENABLE_INFO = '<:enable_info:1264581182365106236>';
}
