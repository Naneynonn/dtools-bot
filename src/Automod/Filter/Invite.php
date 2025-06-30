<?php

declare(strict_types=1);

namespace Naneynonn\Automod\Filter;

use React\Promise\PromiseInterface;

use function React\Promise\resolve;
use function Naneynonn\getIgnoredPermissions;

final class Invite extends AbstractFilter
{
  protected const string TYPE = 'invite';

  public function process(): PromiseInterface
  {
    if (!$this->rule['is_enabled'] || $this->isIgnoredPerm()) {
      return $this->sendReject(type: self::TYPE, text: 'Skipped');
    }

    $check = $this->checkInvite(sentence: $this->message->content);
    if (!$check) return $this->sendReject(type: self::TYPE, text: 'No Invite');

    return resolve([
      'module' => self::TYPE,
      'logReason' => $this->lng->trans('embed.reason.invite'),
      'timeoutReason' => $this->lng->trans('embed.reason.invite'),
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

  private function checkInvite(string $sentence): bool
  {
    $normalized = strtolower($sentence);

    // Убираем пробелы, точки, подчёркивания, дефисы, слэши — чтобы словить обходы
    $compressed = preg_replace('/[\s\.\-_\/\\\]+/', '', $normalized);

    $patterns = [
      '~discord(gg|cominvite|appcominvite)[a-z0-9]{2,32}~i',
      '~\.gg\/?[a-z0-9-]{2,32}~i',
    ];

    foreach ($patterns as $pattern) {
      if (preg_match($pattern, $normalized) || preg_match($pattern, $compressed)) {
        return true;
      }
    }

    return false;
  }
}
