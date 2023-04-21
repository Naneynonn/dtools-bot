<?php

namespace Naneynonn\Filter;

use React\Promise\PromiseInterface;

use Discord\Parts\Channel\Message;

use Naneynonn\Language;

use function React\Promise\reject;
use function React\Promise\resolve;

class Caps
{
  private const TYPE = 'caps';

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
    if (!$this->settings['is_' . self::TYPE . '_status'] || mb_strlen($this->message->content) <= $this->settings[self::TYPE . '_start_length']) return reject($this->info(text: 'disable or len <= min'));

    $percent = $this->getTextPercent(text: $this->message->content);
    if ($percent < $this->settings[self::TYPE . '_percent']) return reject($this->info(text: 'no caps'));

    // вынести getIgnoredPermissions в MessageProcessor
    if (getIgnoredPermissions(perm: $this->perm, message: $this->message, selection: self::TYPE)) return reject($this->info(text: 'ignored perm'));

    return resolve([
      'module' => self::TYPE,
      'reason' => [
        'log' => sprintf($this->lng->get('embeds.abuse-caps'), $this->settings['caps_percent']),
        'timeout' => $this->lng->get('embeds.caps')
      ]
    ]);
  }

  private function getTextPercent(string $text): float
  {
    preg_match_all('/[А-ЯA-Z]/u', $text, $matches);
    return round(count($matches[0]) / mb_strlen($text) * 100, 2);
  }

  private function info(string $text): string
  {
    return self::TYPE . ' | ' . $text;
  }
}
