<?php

declare(strict_types=1);

namespace Naneynonn\Automod\Filter;

use React\Promise\PromiseInterface;

use function React\Promise\resolve;
use function Naneynonn\getIgnoredPermissions;

final class Zalgo extends AbstractFilter
{
  protected const string TYPE = 'zalgo';

  public function process(): PromiseInterface
  {
    if (!$this->rule['is_enabled'] || $this->isIgnoredPerm()) {
      return $this->sendReject(type: self::TYPE, text: 'Skipped');
    }

    $zalgo = $this->isZalgo(text: $this->message->content);
    if (!$zalgo) return $this->sendReject(type: self::TYPE, text: 'No Zalgo');

    return resolve([
      'module' => self::TYPE,
      'logReason' => $this->lng->trans('embed.reason.zalgo-text'),
      'timeoutReason' => $this->lng->trans('embed.reason.zalgo-text'),
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

  private function isZalgo(string $text): bool
  {
    // $zalgoRegex = '/[\p{Mn}\p{Me}]/u'; // Регулярное выражение для символов Zalgo
    // return preg_match($zalgoRegex, $text) === 1;

    // Регулярное выражение для поиска залго текста
    $zalgo_pattern = '/[\p{Mn}\p{Me}\p{Cf}]/u';

    // Регулярное выражение для поиска эмодзи
    $emoji_pattern = '/[\p{Emoji}]/u';

    // Ищем залго текст в тексте
    preg_match($zalgo_pattern, $text, $zalgo_match);

    // Ищем эмодзи в тексте
    preg_match($emoji_pattern, $text, $emoji_match);

    // Если найден залго текст и не найдено эмодзи, то возвращаем true
    if (isset($zalgo_match[0]) && !isset($emoji_match[0])) return true;

    // Если залго текста нет или он является частью эмодзи, то возвращаем false
    return false;
  }
}
