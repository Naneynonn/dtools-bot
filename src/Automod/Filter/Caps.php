<?php

declare(strict_types=1);

namespace Naneynonn\Automod\Filter;

use React\Promise\PromiseInterface;

use function React\Promise\resolve;
use function Naneynonn\getIgnoredPermissions;

final class Caps extends AbstractFilter
{
  protected const string TYPE = 'caps';

  public function process(): PromiseInterface
  {
    if (mb_strlen($this->message->content) <= $this->rule['options']['min_length'] || !$this->rule['is_enabled'] || $this->isIgnoredPerm()) {
      return $this->sendReject(type: self::TYPE, text: 'Skipped');
    }

    $percent = $this->getTextPercent(text: $this->message->content);
    if ($percent < $this->rule['options']['percent']) return $this->sendReject(type: self::TYPE, text: 'No Caps');

    return resolve([
      'module' => self::TYPE,
      'logReason' => $this->lng->trans('embed.reason.abuse-caps', ['%percent%' => $this->rule['options']['percent']]),
      'timeoutReason' => $this->lng->trans('embed.reason.caps'),
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

  private function getTextPercent(string $text): float
  {
    preg_match_all('/[А-ЯA-Z]/u', $text, $matches);
    return round(count($matches[0]) / mb_strlen($text) * 100, 2);
  }
}
