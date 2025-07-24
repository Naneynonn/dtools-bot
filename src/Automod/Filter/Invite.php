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
    $texts = [
      $normalized = strtolower($sentence),
      preg_replace('/[\s.\-_\/\\\\]+/', '', $normalized)
    ];

    $allowed = $this->rule['options']['allowed'] ?? [];

    foreach ($texts as $text) {
      if (preg_match('~discord(?:gg|cominvite|appcominvite)([a-z0-9]{2,32})~i', $text, $m)) {
        if (!in_array(strtolower($m[1]), $allowed, true)) {
          return true;
        }
      }

      if (preg_match('~(?:^|[^a-z0-9])\.gg/([a-z0-9-]{2,32})~i', $text, $m)) {
        if (!in_array(strtolower($m[1]), $allowed, true)) {
          return true;
        }
      }
    }

    return false;
  }
}
