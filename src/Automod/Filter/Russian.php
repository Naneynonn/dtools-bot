<?php

declare(strict_types=1);

namespace Naneynonn\Automod\Filter;

use React\Promise\PromiseInterface;

use function React\Promise\resolve;
use function Naneynonn\getIgnoredPermissions;

final class Russian extends AbstractFilter
{
  protected const string TYPE = 'russian';

  public function process(): PromiseInterface
  {
    if (!$this->rule['is_enabled'] || $this->isIgnoredPerm()) {
      return $this->sendReject(type: self::TYPE, text: 'Skipped');
    }

    $check = $this->checkIfRussian(sentence: $this->message->content);
    if (!$check) return $this->sendReject(type: self::TYPE, text: 'No Russian');

    return resolve([
      'module' => self::TYPE,
      'logReason' => $this->lng->trans('embed.reason.russian'),
      'timeoutReason' => $this->lng->trans('embed.reason.russian'),
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

  private function checkIfRussian(string $sentence): bool
  {
    // Уникальные буквы для каждого языка
    $rusUniqueChars = ['ё', 'ы', 'э', 'ъ'];
    $ukrUniqueChars = ['є', 'і', 'ї', 'ґ'];

    $sentenceChars = mb_str_split(mb_strtolower($sentence));

    foreach ($sentenceChars as $char) {
      // Если находим уникальную букву русского языка - сразу считаем предложение русским
      if (in_array($char, $rusUniqueChars)) {
        return true;
      }

      // Если находим уникальную букву украинского языка - сразу считаем предложение не русским
      if (in_array($char, $ukrUniqueChars)) {
        return false;
      }
    }

    // Если не нашли ни одной уникальной буквы - считаем предложение русским
    return false;
  }
}
