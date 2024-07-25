<?php

declare(strict_types=1);

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

use React\Filesystem\Factory;
use React\Filesystem\AdapterInterface;
use React\Http\Browser;

use Clue\React\Redis\LazyClient as RedisClient;
use thiagoalessio\TesseractOCR\TesseractOCR;

use function React\Async\await;
use function React\Promise\reject;
use function React\Promise\resolve;

use function Naneynonn\getIgnoredPermissions;

class BadWords
{
  use Config;

  private const TYPE = 'badwords';

  private Message|MessageCreate|MessageUpdate $message;
  private Channel $channel;
  private ?GuildMember $member;

  private Language $lng;
  private Model $model;
  private RedisClient $redis;

  private array $settings;
  private array $perm;

  public function __construct(Message|MessageCreate|MessageUpdate $message, Language $lng, array $settings, array $perm, Model $model, Channel $channel, ?GuildMember $member, RedisClient $redis)
  {
    $this->message = $message;

    $this->settings = $settings;
    $this->perm = $perm;
    $this->lng = $lng;
    $this->model = $model;
    $this->channel = $channel;
    $this->member = $member;

    $this->redis = $redis;
  }

  // TODO: Вынести как общий метод
  private function isModuleDisabled(): bool
  {
    return !$this->settings['is_' . self::TYPE . '_status'];
  }

  // TODO: Вынести как общий метод
  private function isPremium(): bool
  {
    return $this->settings['premium'] >= gmdate('Y-m-d H:i:s.u');
  }

  // TODO: Вынести как общий метод
  private function isIgnoredPerm(): bool
  {
    return getIgnoredPermissions(perm: $this->perm, message: $this->message, member: $this->member, parent_id: $this->channel->parent_id, selection: self::TYPE);
  }

  // TODO: Вынести как общий метод
  private function sendReject(string $text): PromiseInterface
  {
    return reject(ucfirst(self::TYPE) . ' | ' . $text);
  }

  public function process(): PromiseInterface
  {
    if ($this->isModuleDisabled()) return $this->sendReject(text: 'Disabled');

    $skip = $this->getSkipWords();

    $badword_check = $this->fetchBadWords(message: $this->message->content, skip: $skip, skipTypes: $this->settings['badwords_exclusion_flags']);
    if (!isset($badword_check['badwords'])) return $this->sendReject(text: 'Err API');
    if (!$badword_check['badwords']) return $this->sendReject(text: 'No Badwords');

    $reason = $this->lng->trans('embed.reason.foul-lang');
    $reason_del = $this->lng->trans('delete.badwords.foul');
    if (!is_null($badword_check['bad_type'])) {
      $reason_list = $this->decodeConditions(bitfield: $badword_check['bad_type']);
      $reason = $reason_list['list'];
      $reason_del = $reason_list['message'];
    }

    if ($this->isIgnoredPerm()) return $this->sendReject(text: 'Ignored Perm');

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
    if ($this->isModuleDisabled()) return $this->sendReject(text: 'Disabled');

    $skip = $this->getSkipWords();

    $badword_check = $this->fetchBadWords(message: $sticker->name, skip: $skip, skipTypes: $this->settings['badwords_exclusion_flags']);
    if (!isset($badword_check['badwords'])) return $this->sendReject(text: 'Err API');
    if (!$badword_check['badwords']) return $this->sendReject(text: 'No Badwords');

    $reason = $this->lng->trans('embed.reason.foul-lang');
    $reason_del = $this->lng->trans('delete.badwords.foul');
    if (!is_null($badword_check['bad_type'])) {
      $reason_list = $this->decodeConditions(bitfield: $badword_check['bad_type']);
      $reason = $reason_list['list'];
      $reason_del = $reason_list['message'];
    }

    if ($this->isIgnoredPerm()) return $this->sendReject(text: 'Ignored Perm');

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

    if ($this->isModuleDisabled()) return $this->sendReject(text: 'Disabled');
    if (!$this->isPremium()) return $this->sendReject(text: 'No Premium');

    $skip = $this->getSkipWords();

    $this->addMessageInRedis(message: $this->message->content);

    $msg_premium = $this->getAllWordsAsString(); // Выведет составленное сообщение из слов
    if (empty($msg_premium)) return $this->sendReject(text: 'No Words');

    $msg_ids = $this->getMessageIds();

    $badword_check = $this->fetchBadWords(message: $msg_premium, skip: $skip, skipTypes: $this->settings['badwords_exclusion_flags']);
    if (!isset($badword_check['badwords'])) return $this->sendReject(text: 'Err API');
    if (!$badword_check['badwords']) return $this->sendReject(text: 'No Badwords');

    $reason = $this->lng->trans('embed.reason.foul-lang');
    $reason_del = $this->lng->trans('delete.badwords.foul');
    if (!is_null($badword_check['bad_type'])) {
      $reason_list = $this->decodeConditions(bitfield: $badword_check['bad_type']);
      $reason = $reason_list['list'];
      $reason_del = $reason_list['message'];
    }

    // очищаем если что-то нашло
    $this->clearAllWords();

    if ($this->isIgnoredPerm()) return $this->sendReject(text: 'Ignored Perm');

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

  public function processImage(string $url): PromiseInterface
  {
    if ($this->isModuleDisabled()) return $this->sendReject(text: 'Disabled');
    if (!$this->isPremium()) return $this->sendReject(text: 'No Premium');

    $skip = $this->getSkipWords();

    $text = $this->extractTextFromImage(url: $url);
    if (empty($text)) return $this->sendReject(text: 'No image text');

    $badword_check = $this->fetchBadWords(message: $text, skip: $skip, skipTypes: $this->settings['badwords_exclusion_flags']);
    if (!isset($badword_check['badwords'])) return $this->sendReject(text: 'Err API');
    if (!$badword_check['badwords']) return $this->sendReject(text: 'No Badwords');

    $reason = $this->lng->trans('embed.reason.foul-lang');
    $reason_del = $this->lng->trans('delete.badwords.foul');
    if (!is_null($badword_check['bad_type'])) {
      $reason_list = $this->decodeConditions(bitfield: $badword_check['bad_type']);
      $reason = $reason_list['list'];
      $reason_del = $reason_list['message'];
    }

    if ($this->isIgnoredPerm()) return $this->sendReject(text: 'Ignored Perm');

    return resolve([
      'module' => self::TYPE,
      'reason' => [
        'log' => $reason,
        'timeout' => $reason,
        'delete' => $reason_del,
        'text' => $badword_check['message']
      ]
    ]);
  }

  private function downloadImage(string $url, AdapterInterface $filesystem): string
  {
    $path = tempnam(sys_get_temp_dir(), 'img_') . '.png';
    $client = new Browser();

    try {
      $response = await($client->get(url: $url));
      $filesystem->file($path)->putContents((string) $response->getBody());
      return $path;
    } catch (\Exception $e) {
      echo 'Err fetch bw: ' .  $e->getMessage(), PHP_EOL;
      return null;
    }

    return json_decode((string) $response->getBody(), true);
  }

  private function recognizeText(string $path): string
  {
    return (new TesseractOCR($path))->lang('rus', 'urk', 'eng')->run();
  }

  private function extractTextFromImage(string $url): string
  {
    $filesystem = Factory::create();

    $url = $this->downloadImage(url: $url, filesystem: $filesystem);
    $text = $this->recognizeText(path: $url);

    if (file_exists($url)) {
      unlink($url);
    }

    return $text;
  }

  // Добавляет одно слово в Redis с уникальным ключом, включающим messageId
  private function addMessageInRedis(string $message): void
  {
    $listKey = "messages:{$this->channel->guild_id}:{$this->message->channel_id}";

    if (mb_strlen($message) > 75 && substr_count($message, ' ') > 3) {
      return; // Игнорирование, если условия не выполняются
    }

    // Проверка общего количества слов
    if (await($this->redis->llen($listKey)) > 10) {
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
    $listKey = "messages:{$this->channel->guild_id}:{$this->message->channel_id}";
    $wordsData = await($this->redis->lrange($listKey, 0, -1)); // Получение всех элементов из списка

    $words = [];
    foreach ($wordsData as $wordData) {
      $data = json_decode($wordData, true);
      $words[] = $data['message'];
    }

    return implode(' ', $words); // Соединение слов в одну строку
  }

  private function clearAllWords(): void
  {
    $listKey = "messages:{$this->channel->guild_id}:{$this->message->channel_id}";
    $this->redis->del($listKey);
  }

  public function getMessageIds(): array
  {
    $listKey = "messages:{$this->channel->guild_id}:{$this->message->channel_id}";
    $wordsData = await($this->redis->lrange($listKey, 0, -1));

    $messageIds = [];
    foreach ($wordsData as $wordData) {
      $data = json_decode($wordData, true);
      $messageIds[] = $data['id'];
    }

    return $messageIds;
  }

  private function getSkipWords(): string
  {
    $skip = $this->model->getBadWordsExeption(id: $this->channel->guild_id);
    return $this->skipWords(skip: $skip);
  }

  private function skipWords(array $skip): string
  {
    if (!$skip) $skip = '';
    else $skip = implode(', ', array_map(function ($entry) {
      return $entry['word'];
    }, $skip));

    return $skip;
  }

  private function fetchBadWords(string $message, string $skip, ?int $skipTypes = null): ?array
  {
    $client = new Browser();

    $url = 'https://api.discord.band/v1/badwords';
    $body = json_encode([
      "message" => $message,
      "type" => 1,
      "skip" => $skip,
      "skipTypes" => $skipTypes
    ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    $headers = [
      'Content-Type' => 'application/json',
      'Authorization' => 'Bearer ' . self::API_TOKEN,
      'User-Agent' => 'DTools-Bot/1.0 (+https://discordtools.cc)'
    ];

    try {
      $response = await($client->post(url: $url, headers: $headers, body: $body));
    } catch (\Exception $e) {
      echo 'Err fetch bw: ' .  $e->getMessage(), PHP_EOL;
      return null;
    }

    return json_decode((string) $response->getBody(), true);
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
