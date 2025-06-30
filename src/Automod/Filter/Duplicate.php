<?php

declare(strict_types=1);

namespace Naneynonn\Automod\Filter;

use React\Promise\PromiseInterface;

use function React\Promise\resolve;
use function React\Async\await;
use function Naneynonn\getIgnoredPermissions;

final class Duplicate extends AbstractFilter
{
  protected const string TYPE                   = 'duplicate';

  // Redis
  private const int     EXPIRATION_SECONDS      = 90;
  private const int     MAX_GLOBAL_MESSAGES     = 10;
  private const int     MAX_USER_MESSAGES       = 20;
  private const int     MAX_MESSAGE_LENGTH      = 75;
  private const string  KEY_PREFIX              = 'messages:duplicate';

  public function process(): PromiseInterface
  {
    if (!$this->rule['is_enabled'] || $this->isIgnoredPerm()) {
      return $this->sendReject(type: self::TYPE, text: 'Skipped');
    }

    if (empty($this->message->content)) return $this->sendReject(type: self::TYPE, text: "No text content");

    return $this->analyzeText($this->message->content, 'Initial');
  }

  public function filters(): array
  {
    return [
      'main' => [
        fn() => $this->processLazyWords(),
        fn() => $this->processLazyGlobalWords(),
      ],
    ];
  }

  public function processLazyWords(): PromiseInterface
  {
    return $this->processLazy(isGlobal: false, label: 'Lazy Words');
  }

  public function processLazyGlobalWords(): PromiseInterface
  {
    return $this->processLazy(isGlobal: true, label: 'Lazy Global Words');
  }

  private function processLazy(bool $isGlobal, string $label): PromiseInterface
  {
    if (!$this->rule['is_enabled'] || $this->isIgnoredPerm()) {
      return $this->sendReject(type: self::TYPE, text: "Skipped {$label}");
    }

    $this->addMessageInRedis($this->message->content, $isGlobal);
    $data = $this->getWordsData($isGlobal);
    $text = $this->getAllWordsAsString($data);

    if (empty($text)) return $this->sendReject(type: self::TYPE, text: "No {$label} Text");

    return $this->analyzeText(
      message: $text,
      label: $label,
      isLazy: true,
      lazyIds: $this->getMessageIds($data),
      isGlobal: $isGlobal
    );
  }

  private function isIgnoredPerm(): bool
  {
    return getIgnoredPermissions(
      perm: $this->permissions,
      message: $this->message,
      parent_id: $this->channel->parent_id,
      member: $this->member,
      selection: self::TYPE
    );
  }

  private function findWordDuplicates(string $sentence, int $start_count): float
  {
    $words = preg_split('/\s+/', strtolower($sentence));
    $word_count = count($words);

    $duplicates = array_count_values($words);

    $duplicates_count = 0;
    foreach ($duplicates as $word => $count) {
      if ($count > $start_count) {
        $duplicates_count += $count;
      }
    }


    if ($duplicates_count == 0) {
      return 0;
    }

    $percentage = ($duplicates_count / $word_count) * 100;

    return round($percentage, 2);
  }

  private function findSymbolsDuplicates(string $sentence, int $minRepeats = 3)
  {
    // Используем регулярное выражение для поиска повторяющихся символов
    preg_match_all('/(.)(\1{' . ($minRepeats) . ',})/su', $sentence, $matches);

    // Проверяем, есть ли совпадения
    if (count($matches[0]) > 0) {
      // Подсчитываем общее количество повторяющихся символов
      $totalRepeats = mb_strlen(implode($matches[0]));

      // Подсчитываем процент повторяющихся символов от общего числа символов в тексте
      $percentage = ($totalRepeats / mb_strlen($sentence)) * 100;

      return $percentage;
    }

    // Если нет повторяющихся символов, возвращаем 0
    return 0;
  }

  private function getCountDuplicate(string $sentence, int $percent, int $count, $min_length): float
  {
    $words = $this->findWordDuplicates(sentence: $sentence, start_count: $count);

    if ($words >= $percent) {
      return $words;
    }

    $symbols = $this->findSymbolsDuplicates(sentence: $sentence, minRepeats: $min_length);

    return $symbols;
  }

  private function addMessageInRedis(string $message, bool $isGlobal = false): void
  {
    if (mb_strlen($message) > self::MAX_MESSAGE_LENGTH) return;

    $key = $this->getRedisKey($isGlobal);

    if (await($this->redis->llen($key)) > ($isGlobal ? self::MAX_GLOBAL_MESSAGES : self::MAX_USER_MESSAGES)) $this->redis->lpop($key);

    $this->redis->rpush($key, json_encode(['message' => $message, 'id' => $this->message->id]));
    $this->redis->expire($key, self::EXPIRATION_SECONDS);
  }

  private function getRedisKey(bool $isGlobal): string
  {
    $base = self::KEY_PREFIX . ":{$this->channel->guild_id}:{$this->message->channel_id}";
    return $isGlobal
      ? $base
      : "{$base}:{$this->message->author->id}";
  }

  private function getWordsData(bool $isGlobal = false): array
  {
    $key = $this->getRedisKey($isGlobal);
    return await($this->redis->lrange($key, 0, -1)) ?? [];
  }

  private function getAllWordsAsString(array $data): string
  {
    return implode(' ', array_map(static fn($json) => json_decode($json, true)['message'], $data));
  }

  private function getMessageIds(array $data): array
  {
    return array_map(static fn($json) => json_decode($json, true)['id'], $data);
  }

  private function analyzeText(string $message, string $label, bool $isLazy = false, array $lazyIds = [], bool $isGlobal = false): PromiseInterface
  {
    $total_percentage = $this->getCountDuplicate(
      sentence: $message,
      percent: $this->rule['options']['percent'],
      count: $this->rule['options']['word_count'],
      min_length: $this->rule['options']['min_length']
    );
    if ($total_percentage < $this->rule['options']['percent']) return $this->sendReject(type: self::TYPE, text: "No Duplicate in {$label}");

    if ($isLazy) $this->clearAllWords($isGlobal);

    return resolve([
      'module' => self::TYPE,
      'logReason' => $this->lng->trans('embed.reason.duplicate-reason', ['%percent%' => $this->rule['options']['percent']]),
      'timeoutReason' => $this->lng->trans('embed.reason.duplicate'),
      'deleteReason' =>  $this->lng->trans('delete.' . self::TYPE),
      'type' => strtolower($label),
      'isLazy' => $isLazy,
      'lazyIds' => $lazyIds,
      'message' => $message
    ]);
  }

  private function clearAllWords(bool $isGlobal = false): void
  {
    $this->redis->del($this->getRedisKey($isGlobal));
  }
}
