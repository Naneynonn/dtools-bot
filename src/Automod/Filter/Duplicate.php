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

final class Duplicate
{
  private const TYPE = 'duplicate';

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

    $total_percentage = $this->getCountDuplicate(sentence: $this->message->content, percent: $this->settings[self::TYPE . '_percent'], count: $this->settings[self::TYPE . '_word_count'], min_length: $this->settings[self::TYPE . '_start_length']);
    if ($total_percentage < $this->settings[self::TYPE . '_percent']) return reject($this->info(text: 'no duplicate'));

    // вынести getIgnoredPermissions в MessageProcessor
    if (getIgnoredPermissions(perm: $this->perm, message: $this->message, member: $this->member, parent_id: $this->channel->parent_id, selection: self::TYPE)) {
      return reject($this->info(text: 'ignored perm'));
    }

    return resolve([
      'module' => self::TYPE,
      'logReason' => $this->lng->trans('embed.reason.duplicate-reason', ['%percent%' => $this->settings[self::TYPE . '_percent']]),
      'timeoutReason' => $this->lng->trans('embed.reason.duplicate'),
      'deleteReason' => $this->lng->trans('delete.' . self::TYPE)
    ]);
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

  private function info(string $text): string
  {
    return self::TYPE . ' | ' . $text;
  }
}
