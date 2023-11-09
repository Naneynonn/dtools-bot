<?php

namespace Naneynonn\Filter;

use React\Promise\PromiseInterface;

use Ragnarok\Fenrir\Gateway\Events\MessageCreate;
use Ragnarok\Fenrir\Gateway\Events\MessageUpdate;
use Ragnarok\Fenrir\Parts\Message;
use Ragnarok\Fenrir\Parts\Channel;
use Ragnarok\Fenrir\Parts\GuildMember;

use Naneynonn\Language;
use Naneynonn\Model;
use Naneynonn\Config;

use function React\Promise\reject;
use function React\Promise\resolve;

class BadWords
{
  use Config;

  private const TYPE = 'badwords';

  private Message|MessageCreate|MessageUpdate $message;
  private Channel $channel;
  private ?GuildMember $member;

  private Language $lng;
  private Model $model;

  private array $settings;
  private array $perm;

  public function __construct(Message|MessageCreate|MessageUpdate $message, Language $lng, array $settings, array $perm, Model $model, Channel $channel, ?GuildMember $member)
  {
    $this->message = $message;

    $this->settings = $settings;
    $this->perm = $perm;
    $this->lng = $lng;
    $this->model = $model;
    $this->channel = $channel;
    $this->member = $member;
  }

  public function process(): PromiseInterface
  {
    if (!$this->settings['is_' . self::TYPE . '_status']) return reject($this->info(text: 'disable'));

    // Load BadWords Exceptions
    $skip = $this->model->getBadWordsExeption(id: $this->channel->guild_id);
    $skip = $this->skipWords(skip: $skip);

    $badword_check = $this->checkBadWords(message: $this->message->content, skip: $skip);
    if (!isset($badword_check['badwords'])) return reject($this->info(text: 'error api'));

    $reason = $this->lng->trans('embed.reason.foul-lang');
    $reason_del = $this->lng->trans('delete.badwords.foul');
    if (!is_null($badword_check['bad_type'])) {
      $reason_list = $this->decodeConditions(bitfield: $badword_check['bad_type']);
      $reason = $reason_list['list'];
      $reason_del = $reason_list['message'];
    }

    $badword = $badword_check['badwords'];
    if (!$badword) return reject($this->info(text: 'no badwords'));

    // вынести getIgnoredPermissions в MessageProcessor
    if (getIgnoredPermissions(perm: $this->perm, message: $this->message, member: $this->member, parent_id: $this->channel->parent_id, selection: self::TYPE)) {
      return reject($this->info(text: 'ignored perm'));
    }

    return resolve([
      'module' => self::TYPE,
      'reason' => [
        'log' => $reason,
        'timeout' => $reason,
        'delete' => $reason_del
      ]
    ]);
  }

  // TODO: проверять будет каждый стикер и вызывать
  public function processStickers(object $sticker): PromiseInterface
  {
    if (!$this->settings['is_' . self::TYPE . '_status']) return reject($this->info(text: 'disable'));

    // Load BadWords Exceptions
    $skip = $this->model->getBadWordsExeption(id: $this->channel->guild_id);
    $skip = $this->skipWords(skip: $skip);

    $badword_check = $this->checkBadWords(message: $sticker->name, skip: $skip);
    if (!isset($badword_check['badwords'])) return reject($this->info(text: 'error api'));

    $reason = $this->lng->trans('embed.reason.foul-lang');
    $reason_del = $this->lng->trans('delete.badwords.foul');
    if (!is_null($badword_check['bad_type'])) {
      $reason_list = $this->decodeConditions(bitfield: $badword_check['bad_type']);
      $reason = $reason_list['list'];
      $reason_del = $reason_list['message'];
    }

    $badword = $badword_check['badwords'];
    if (!$badword) return reject($this->info(text: 'no badwords'));

    // вынести getIgnoredPermissions в MessageProcessor
    if (getIgnoredPermissions(perm: $this->perm, message: $this->message, member: $this->member, parent_id: $this->channel->parent_id, selection: self::TYPE)) return reject($this->info(text: 'ignored perm'), member: $this->member);

    // if (getIgnoredPermissions(perm: $this->perm, message: $this->message, parent_id: $this->channel->parent_id, selection: self::TYPE)) return reject($this->info(text: 'ignored perm'));

    return resolve([
      'module' => self::TYPE,
      'reason' => [
        'log' => $reason,
        'timeout' => $reason,
        'delete' => $reason_del
      ]
    ]);
  }

  private function skipWords(array $skip): string
  {
    if (!$skip) $skip = '';
    else $skip = implode(', ', array_map(function ($entry) {
      return $entry['word'];
    }, $skip));

    return $skip;
  }

  private function checkBadWords(string $message, string $skip): ?array
  {
    $url = 'https://api.discord.band/v1/badwords';

    $body = [
      "message" => $message,
      "type" => 1,
      "skip" => $skip
    ];
    $response_json = json_encode($body, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "POST");
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json', 'Authorization: Bearer ' . self::API_TOKEN]);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 2);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 1);
    curl_setopt($ch, CURLOPT_USERAGENT, "DTools-Bot/1.0 (+https://discordtools.cc)");
    curl_setopt($ch, CURLOPT_POSTFIELDS, $response_json);
    $response = curl_exec($ch);
    curl_close($ch);
    $results = json_decode($response, true);

    return $results;
  }

  private function info(string $text): string
  {
    return self::TYPE . ' | ' . $text;
  }

  private function decodeConditions(int $bitfield): array
  {
    $conditions = [
      1 => [$this->lng->trans('embed.reason.foul-lang'), $this->lng->trans('delete.badwords.foul')],
      2 => [$this->lng->trans('embed.reason.insults'), $this->lng->trans('delete.badwords.insults')],
      4 => [$this->lng->trans('embed.reason.toxicity'), $this->lng->trans('delete.badwords.toxicity')],
      8 => [$this->lng->trans('embed.reason.suicide'), $this->lng->trans('delete.badwords.suicide')],
      16 => [$this->lng->trans('embed.reason.politics'), $this->lng->trans('delete.badwords.politics')],
      32 => [$this->lng->trans('embed.reason.inadequacy'), $this->lng->trans('delete.badwords.inadequacy')],
      64 => [$this->lng->trans('embed.reason.threats'), $this->lng->trans('delete.badwords.threats')],
      128 => [$this->lng->trans('embed.reason.forbidden'), $this->lng->trans('delete.badwords.forbidden')]
    ];

    $resultNames = [];
    $resultMessages = [];

    foreach ($conditions as $value => $namesAndMessages) {
      if ($bitfield & $value) {
        $resultNames[] = $namesAndMessages[0];
        $resultMessages[] = $namesAndMessages[1];
      }
    }

    return [
      'list' => implode(', ', $resultNames),
      'message' => $resultMessages[0],
    ];
  }
}
