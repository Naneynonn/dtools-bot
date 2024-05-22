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
use function Naneynonn\getIgnoredPermissions;

class Caps
{
  private const TYPE = 'caps';

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
    if (!$this->settings['is_' . self::TYPE . '_status'] || mb_strlen($this->message->content) <= $this->settings[self::TYPE . '_start_length']) return reject($this->info(text: 'disable or len <= min'));

    $percent = $this->getTextPercent(text: $this->message->content);
    if ($percent < $this->settings[self::TYPE . '_percent']) return reject($this->info(text: 'no caps'));

    // вынести getIgnoredPermissions в MessageProcessor
    if (getIgnoredPermissions(perm: $this->perm, message: $this->message, member: $this->member, parent_id: $this->channel->parent_id, selection: self::TYPE)) {
      return reject($this->info(text: 'ignored perm'));
    }

    return resolve([
      'module' => self::TYPE,
      'reason' => [
        'log' => $this->lng->trans('embed.reason.abuse-caps', ['%percent%' => $this->settings[self::TYPE . '_percent']]),
        'timeout' => $this->lng->trans('embed.reason.caps')
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
