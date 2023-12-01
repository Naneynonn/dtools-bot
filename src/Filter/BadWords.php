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

use Predis\Client;

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

  private Client $redis;

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
    if (getIgnoredPermissions(perm: $this->perm, message: $this->message, member: $this->member, parent_id: $this->channel->parent_id, selection: self::TYPE)) return reject($this->info(text: 'ignored perm'));

    return resolve([
      'module' => self::TYPE,
      'reason' => [
        'log' => $reason,
        'timeout' => $reason,
        'delete' => $reason_del
      ]
    ]);
  }

  // TODO Принимает по 1 слову и проверяет
  public function processLazyWords(): PromiseInterface
  {
    $msg_premium = '';

    if (!$this->settings['is_' . self::TYPE . '_status']) return reject($this->info(text: 'disable'));
    if ($this->settings['premium'] <= gmdate('Y-m-d H:i:s.u')) return reject($this->info(text: 'no premium'));

    // Load BadWords Exceptions
    $skip = $this->model->getBadWordsExeption(id: $this->channel->guild_id);
    $skip = $this->skipWords(skip: $skip);


    $this->redis = new Client();
    $this->addMessageInRedis(message: $this->message->content);
    $msg_premium = $this->getAllWordsAsString(); // Выведет составленное сообщение из слов
    $msg_ids = $this->getMessageIds();

    $badword_check = $this->checkBadWords(message: $msg_premium, skip: $skip);
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

    // очищаем если что-то нашло
    $this->clearAllWords();

    // вынести getIgnoredPermissions в MessageProcessor
    if (getIgnoredPermissions(perm: $this->perm, message: $this->message, member: $this->member, parent_id: $this->channel->parent_id, selection: self::TYPE)) {
      return reject($this->info(text: 'ignored perm'));
    }

    $result = [
      'module' => self::TYPE,
      'reason' => [
        'log' => $reason,
        'timeout' => $reason,
        'delete' => $reason_del
      ]
    ];

    // Добавляем 'lazy', если $msg_premium не пуст
    if (!empty($msg_premium)) {
      $result['lazy'] = [
        'message' => $msg_premium,
        'ids' => $msg_ids
      ];
    }

    return resolve($result);
  }

  // Добавляет одно слово в Redis с уникальным ключом, включающим messageId
  private function addMessageInRedis(string $message): void
  {
    $listKey = "messages:{$this->message->guild_id}:{$this->message->channel_id}:{$this->message->author->id}";

    if (mb_strlen($message) > 45 && substr_count($message, ' ') > 3) {
      return; // Игнорирование, если условия не выполняются
    }

    // Проверка общего количества слов
    if ($this->redis->llen($listKey) > 10) {
      $this->redis->del($listKey); // Удаление списка, если слов больше 10
    }

    // Создание JSON-объекта с word и message_id
    $wordData = json_encode([
      'message' => $message,
      'id' => $this->message->id
    ]);

    $this->redis->rpush($listKey, $wordData); // Добавление JSON-строки в конец списка
    $this->redis->expire($listKey, 30); // Установка TTL для списка
  }

  // Возвращает все слова в виде строки для указанного guildId и channelId
  public function getAllWordsAsString(): string
  {
    $listKey = "messages:{$this->message->guild_id}:{$this->message->channel_id}:{$this->message->author->id}";
    $wordsData = $this->redis->lrange($listKey, 0, -1); // Получение всех элементов из списка

    $words = [];
    foreach ($wordsData as $wordData) {
      $data = json_decode($wordData, true);
      $words[] = $data['message'];
    }

    return implode(' ', $words); // Соединение слов в одну строку
  }

  private function clearAllWords(): void
  {
    $listKey = "messages:{$this->message->guild_id}:{$this->message->channel_id}:{$this->message->author->id}";
    $this->redis->del($listKey);
  }

  public function getMessageIds(): array
  {
    $listKey = "messages:{$this->message->guild_id}:{$this->message->channel_id}:{$this->message->author->id}";
    $wordsData = $this->redis->lrange($listKey, 0, -1);

    $messageIds = [];
    foreach ($wordsData as $wordData) {
      $data = json_decode($wordData, true);
      $messageIds[] = $data['id'];
    }

    return $messageIds;
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
