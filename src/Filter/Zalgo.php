<?php

namespace Naneynonn\Filter;

use React\Promise\PromiseInterface;

use Discord\Parts\Channel\Message;

use Naneynonn\Language;

use function React\Promise\reject;
use function React\Promise\resolve;

class Zalgo
{
  private const TYPE = 'zalgo';

  private Message $message;
  private Language $lng;

  private array $settings;
  private array $perm;

  public function __construct(Message $message, Language $lng, array $settings, array $perm)
  {
    $this->message = $message;

    $this->settings = $settings;
    $this->perm = $perm;
    $this->lng = $lng;
  }

  public function process(): PromiseInterface
  {
    if (!$this->settings['is_' . self::TYPE . '_status']) return reject('status off');

    $zalgo = $this->isZalgo(text: $this->message->content);
    if (!$zalgo) return reject('no zalgo');

    // $percent = $this->getTextPercent(text: $this->message->content);
    // if ($percent < $this->settings[self::TYPE . '_percent']) return reject('percent zadelo');

    // вынести getIgnoredPermissions в MessageProcessor
    if (getIgnoredPermissions(perm: $this->perm, message: $this->message, selection: self::TYPE)) return reject('ignored perm');

    return resolve([
      'module' => self::TYPE,
      'reason' => [
        'log' => $this->lng->get('embeds.zalgo-text'),
        'timeout' => $this->lng->get('embeds.zalgo-text')
      ]
    ]);
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
