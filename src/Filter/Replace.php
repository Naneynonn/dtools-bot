<?php

namespace Naneynonn\Filter;

use React\Promise\PromiseInterface;

use Ragnarok\Fenrir\Gateway\Events\MessageCreate;
use Ragnarok\Fenrir\Gateway\Events\MessageUpdate;
use Ragnarok\Fenrir\Parts\Message;
use Ragnarok\Fenrir\Parts\Channel;
use Ragnarok\Fenrir\Parts\GuildMember;

use Naneynonn\Language;

use function React\Promise\reject;
use function React\Promise\resolve;

class Replace
{
  private const TYPE = 'replace';

  private Message|MessageCreate $message;
  private Channel $channel;
  private ?GuildMember $member;

  private Language $lng;

  private array $settings;
  private array $perm;

  public function __construct(Message|MessageCreate|MessageUpdate $message, Language $lng, array $settings, array $perm, Channel $channel, ?GuildMember $member)
  {
    $this->message = $message;

    $this->settings = $settings;
    $this->perm = $perm;
    $this->lng = $lng;
    $this->channel = $channel;
    $this->member = $member;
  }

  public function process(): PromiseInterface
  {
    if (!$this->settings['is_' . self::TYPE . '_status']) return reject($this->info(text: 'disable'));

    $replace = $this->getReplaceLetters(text: $this->message->content);
    if (!$replace) return reject($this->info(text: 'no replace'));

    // $percent = $this->getTextPercent(text: $this->message->content);
    // if ($percent < $this->settings[self::TYPE . '_percent']) return reject('percent zadelo');

    // вынести getIgnoredPermissions в MessageProcessor
    if (getIgnoredPermissions(perm: $this->perm, message: $this->message, member: $this->member, parent_id: $this->channel->parent_id, selection: self::TYPE)) {
      return reject($this->info(text: 'ignored perm'));
    }

    return resolve([
      'module' => self::TYPE,
      'reason' => [
        'log' => $this->lng->trans('embed.reason.find-replace'),
        'timeout' => $this->lng->trans('embed.reason.find-replace')
      ]
    ]);
  }

  private function getReplaceLetters(string $text): bool
  {
    // return preg_match('/(?=[а-яА-ЯёЁ]*[a-zA-Z])(?=[a-zA-Z]*[а-яА-ЯёЁ])[\wа-яА-ЯёЁ]+/u', $text) ? true : false;

    // LAST WORK
    // return preg_match('/[\wа-яА-ЯёЁ]+(?=[а-яА-ЯёЁ]*[a-zA-Z])(?=[a-zA-Z]*[а-яА-ЯёЁ])[\wа-яА-ЯёЁ]+/u', $text) ? true : false;
    return (bool) preg_match('/\b(?=\w*[а-яА-Я])(?=\w*[a-zA-Z])\w*\b/u', $text);
  }

  private function info(string $text): string
  {
    return self::TYPE . ' | ' . $text;
  }
}
