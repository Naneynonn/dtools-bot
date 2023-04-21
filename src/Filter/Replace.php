<?php

namespace Naneynonn\Filter;

use React\Promise\PromiseInterface;

use Discord\Parts\Channel\Message;

use Naneynonn\Language;

use function React\Promise\reject;
use function React\Promise\resolve;

class Replace
{
  private const TYPE = 'replace';

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
    if (!$this->settings['is_' . self::TYPE . '_status']) return reject($this->info(text: 'disable'));

    $replace = $this->getReplaceLetters(text: $this->message->content);
    if (!$replace) return reject($this->info(text: 'no replace'));

    // $percent = $this->getTextPercent(text: $this->message->content);
    // if ($percent < $this->settings[self::TYPE . '_percent']) return reject('percent zadelo');

    // вынести getIgnoredPermissions в MessageProcessor
    if (getIgnoredPermissions(perm: $this->perm, message: $this->message, selection: self::TYPE)) return reject($this->info(text: 'ignored perm'));

    return resolve([
      'module' => self::TYPE,
      'reason' => [
        'log' => $this->lng->get('embeds.find-replace'),
        'timeout' => $this->lng->get('embeds.find-replace')
      ]
    ]);
  }

  private function getReplaceLetters(string $text): bool
  {
    // return preg_match('/(?=[а-яА-ЯёЁ]*[a-zA-Z])(?=[a-zA-Z]*[а-яА-ЯёЁ])[\wа-яА-ЯёЁ]+/u', $text) ? true : false;

    // LAST WORK
    // return preg_match('/[\wа-яА-ЯёЁ]+(?=[а-яА-ЯёЁ]*[a-zA-Z])(?=[a-zA-Z]*[а-яА-ЯёЁ])[\wа-яА-ЯёЁ]+/u', $text) ? true : false;
    return preg_match('/\b(?=\w*[а-яА-Я])(?=\w*[a-zA-Z])\w*\b/u', $text) ? true : false;
  }

  private function info(string $text): string
  {
    return self::TYPE . ' | ' . $text;
  }
}
