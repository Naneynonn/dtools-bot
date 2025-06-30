<?php

declare(strict_types=1);

namespace Naneynonn\Automod\Filter;

use React\Promise\PromiseInterface;

use function React\Promise\resolve;
use function Naneynonn\getIgnoredPermissions;

final class Replace extends AbstractFilter
{
  protected const string TYPE = 'replace';

  public function process(): PromiseInterface
  {
    if (!$this->rule['is_enabled'] || $this->isIgnoredPerm()) {
      return $this->sendReject(type: self::TYPE, text: 'Skipped');
    }

    $replace = $this->getReplaceLetters(text: $this->message->content);
    if (!$replace) return $this->sendReject(type: self::TYPE, text: 'No Replace');

    return resolve([
      'module' => self::TYPE,
      'logReason' => $this->lng->trans('embed.reason.find-replace'),
      'timeoutReason' => $this->lng->trans('embed.reason.find-replace'),
      'deleteReason' => $this->lng->trans('delete.' . self::TYPE)
    ]);
  }

  private function isIgnoredPerm(): bool
  {
    return getIgnoredPermissions(
      perm: $this->permissions,
      message: $this->message,
      parent_id: $this->channel->parent_id,
      member: $this->member,
      selection: self::TYPE
    );
  }

  private function getReplaceLetters(string $text): bool
  {
    // return preg_match('/(?=[а-яА-ЯёЁ]*[a-zA-Z])(?=[a-zA-Z]*[а-яА-ЯёЁ])[\wа-яА-ЯёЁ]+/u', $text) ? true : false;

    // LAST WORK
    // return preg_match('/[\wа-яА-ЯёЁ]+(?=[а-яА-ЯёЁ]*[a-zA-Z])(?=[a-zA-Z]*[а-яА-ЯёЁ])[\wа-яА-ЯёЁ]+/u', $text) ? true : false;
    return (bool) preg_match('/\b(?=\w*[а-яА-Я])(?=\w*[a-zA-Z])\w*\b/u', $text);
  }
}
