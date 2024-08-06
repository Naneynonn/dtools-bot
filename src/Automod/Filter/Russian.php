<?php

declare(strict_types=1);

namespace Naneynonn\Automod\Filter;

use Ragnarok\Fenrir\Gateway\Events\MessageCreate;
use Ragnarok\Fenrir\Gateway\Events\MessageUpdate;

use Ragnarok\Fenrir\Parts\Message;
use Ragnarok\Fenrir\Parts\Channel;
use Ragnarok\Fenrir\Parts\GuildMember;

use React\Promise\PromiseInterface;
use Naneynonn\Language;

use function React\Promise\reject;
use function React\Promise\resolve;
use function Naneynonn\getIgnoredPermissions;

final class Russian
{
  private const TYPE = 'russian';

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

    $check = $this->checkIfRussian(sentence: $this->message->content);
    if (!$check) return reject($this->info(text: 'no russian'));

    // вынести getIgnoredPermissions в MessageProcessor
    if (getIgnoredPermissions(perm: $this->perm, message: $this->message, member: $this->member, parent_id: $this->channel->parent_id, selection: self::TYPE)) {
      return reject($this->info(text: 'ignored perm'));
    }

    return resolve([
      'module' => self::TYPE,
      'logReason' => $this->lng->trans('embed.reason.russian'),
      'timeoutReason' => $this->lng->trans('embed.reason.russian'),
      'deleteReason' => $this->lng->trans('delete.' . self::TYPE)
    ]);
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

  private function info(string $text): string
  {
    return self::TYPE . ' | ' . $text;
  }
}
