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

final class Zalgo
{
  private const TYPE = 'zalgo';

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

    $zalgo = $this->isZalgo(text: $this->message->content);
    if (!$zalgo) return reject($this->info(text: 'no zalgo'));

    // $percent = $this->getTextPercent(text: $this->message->content);
    // if ($percent < $this->settings[self::TYPE . '_percent']) return reject('percent zadelo');

    // вынести getIgnoredPermissions в MessageProcessor
    if (getIgnoredPermissions(perm: $this->perm, message: $this->message, member: $this->member, parent_id: $this->channel->parent_id, selection: self::TYPE)) {
      return reject($this->info(text: 'ignored perm'));
    }

    return resolve([
      'module' => self::TYPE,
      'logReason' => $this->lng->trans('embed.reason.zalgo-text'),
      'timeoutReason' => $this->lng->trans('embed.reason.zalgo-text'),
      'deleteReason' => $this->lng->trans('delete.' . self::TYPE)
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

  private function info(string $text): string
  {
    return self::TYPE . ' | ' . $text;
  }
}
