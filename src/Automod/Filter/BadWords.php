<?php

declare(strict_types=1);

namespace Naneynonn\Automod\Filter;

use Naneynonn\Config;

use React\Promise\PromiseInterface;
use React\Http\Browser;
use thiagoalessio\TesseractOCR\TesseractOCR;

use React\Filesystem\Factory;
use React\Filesystem\AdapterInterface;

use Throwable;
use Imagick;

use function React\Async\await;
use function React\Promise\resolve;
use function Naneynonn\getIgnoredPermissions;

final class Badwords extends AbstractFilter
{
  use Config;

  protected const string TYPE                   = 'badwords';

  // Redis
  private const int     EXPIRATION_SECONDS      = 90;
  private const int     MAX_GLOBAL_MESSAGES     = 10;
  private const int     MAX_USER_MESSAGES       = 20;
  private const int     MIN_WORD_COUNT          = 3;
  private const int     MAX_MESSAGE_LENGTH      = 75;
  private const string  KEY_PREFIX              = 'messages:badwords';

  // Badwords API
  private const string  API_URL                 = 'https://api.discord.band/v1/badwords';
  private const string  API_USER_AGENT          = 'DTools-Bot/1.0 (+https://discordtools.cc)';
  private const string  API_CONTENT_TYPE        = 'application/json';
  private const int     BADWORDS_TYPE_TEXT      = 1;

  // OCR
  private const array   OCR_PSM_MODES           = [3, 6, 4, 7];
  private const string  OCR_LANGUAGES           = 'rus+ukr+eng';
  private const string  TEMP_PREFIX_IMAGE       = 'img_';
  private const string  TEMP_PREFIX_PROCESSED   = 'processed_';

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
        fn() => $this->processStickers(),
      ],
      'fallback' => [
        fn() => $this->processImage(),
      ]
    ];
  }

  private function isPremium(): bool
  {
    return $this->settings['premium'] >= gmdate('Y-m-d H:i:s.u');
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
    if (!$this->isPremium() || !$this->rule['is_enabled'] || $this->isIgnoredPerm()) {
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

  public function processStickers(): PromiseInterface
  {
    if (!$this->rule['is_enabled'] || $this->isIgnoredPerm()) {
      return $this->sendReject(type: self::TYPE, text: 'Skipped Stickers');
    }

    foreach ($this->message->sticker_items ?? [] as $sticker) {
      $text = $this->lng->trans('embed.sticker-name', ['%sticker%' => $sticker->name]) ?? '';
      if (!empty($text)) {
        return $this->analyzeText($text, 'sticker');
      }
    }

    return $this->sendReject(type: self::TYPE, text: 'No Sticker Match');
  }

  public function processImage(): PromiseInterface
  {
    if (!$this->isPremium() || !$this->rule['is_enabled'] || $this->isIgnoredPerm()) {
      return $this->sendReject(type: self::TYPE, text: 'Skipped Images');
    }

    if (!empty($this->message->attachments)) {
      foreach ($this->message->attachments as $attachment) {
        $text = $this->extractTextFromImage($attachment->url ?? '');
        if (!empty($text)) {
          return $this->analyzeText($text, 'Image');
        }
      }
    }

    if (!empty($this->message->embeds)) {
      foreach ($this->message->embeds as $embed) {
        $text = $this->extractTextFromImage($embed->thumbnail->url ?? '');
        if (!empty($text)) {
          return $this->analyzeText($text, 'Image');
        }
      }
    }

    return $this->sendReject(type: self::TYPE, text: 'No Image Match');
  }

  private function analyzeText(string $message, string $label, bool $isLazy = false, array $lazyIds = [], bool $isGlobal = false): PromiseInterface
  {
    $result = $this->fetchBadWords(
      message: $message,
      skip: $this->getSkipWords(),
      skipTypes: $this->rule['options']['exclusion_flags'] ?? 0
    );

    if (!isset($result['badwords']) || !$result['badwords']) {
      return $this->sendReject(type: self::TYPE, text: "No Badwords in $label");
    }

    if ($isLazy) $this->clearAllWords($isGlobal);

    [$reason, $reasonDel] = $this->resolveReason($result);

    return resolve([
      'module' => self::TYPE,
      'logReason' => $reason,
      'timeoutReason' => $reason,
      'deleteReason' => $reasonDel,
      'type' => strtolower($label),
      'isLazy' => $isLazy,
      'lazyIds' => $lazyIds,
      'message' => trim($result['message'] ?? $message)
    ]);
  }

  private function getMessageIds(array $data): array
  {
    return array_map(static fn($json) => json_decode($json, true)['id'], $data);
  }

  private function getAllWordsAsString(array $data): string
  {
    return implode(' ', array_map(static fn($json) => json_decode($json, true)['message'], $data));
  }

  private function getWordsData(bool $isGlobal = false): array
  {
    $key = $this->getRedisKey($isGlobal);
    return await($this->redis->lrange($key, 0, -1)) ?? [];
  }

  private function addMessageInRedis(string $message, bool $isGlobal = false): void
  {
    if (mb_strlen($message) > self::MAX_MESSAGE_LENGTH && substr_count($message, ' ') > self::MIN_WORD_COUNT) return;

    $key = $this->getRedisKey($isGlobal);

    if (await($this->redis->llen($key)) > ($isGlobal ? self::MAX_GLOBAL_MESSAGES : self::MAX_USER_MESSAGES)) $this->redis->lpop($key);

    $this->redis->rpush($key, json_encode(['message' => $message, 'id' => $this->message->id]));
    $this->redis->expire($key, self::EXPIRATION_SECONDS);
  }

  private function clearAllWords(bool $isGlobal = false): void
  {
    $this->redis->del($this->getRedisKey($isGlobal));
  }

  private function getRedisKey(bool $isGlobal): string
  {
    $base = self::KEY_PREFIX . ":{$this->channel->guild_id}:{$this->message->channel_id}";
    return $isGlobal
      ? $base
      : "{$base}:{$this->message->author->id}";
  }

  private function fetchBadWords(string $message, string $skip, ?int $skipTypes = null): ?array
  {
    $client = new Browser();
    $body = json_encode([
      'message' => $message,
      'type' => self::BADWORDS_TYPE_TEXT,
      'skip' => $skip,
      'skipTypes' => $skipTypes
    ], JSON_UNESCAPED_UNICODE);

    try {
      $response = await($client->post(
        self::API_URL,
        [
          'Content-Type' => self::API_CONTENT_TYPE,
          'Authorization' => 'Bearer ' . self::API_TOKEN,
          'User-Agent' => self::API_USER_AGENT
        ],
        $body
      ));

      return json_decode((string) $response->getBody(), true);
    } catch (Throwable $e) {
      echo 'Badwords fetch error: ' . $e->getMessage() . PHP_EOL;
      return null;
    }
  }

  private function resolveReason(array $result): array
  {
    $lng = $this->lng;

    if (!is_null($result['bad_type'])) {
      $decoded = $this->decodeConditions($result['bad_type']);
      return [$decoded['list'], $decoded['message']];
    }

    return [
      $lng->trans('embed.reason.foul-lang'),
      $lng->trans('delete.badwords.foul')
    ];
  }

  private function decodeConditions(int $bitfield): array
  {
    $lng = $this->lng;

    $map = [
      1   => ['foul-lang', 'foul'],
      2   => ['insults', 'insults'],
      4   => ['toxicity', 'toxicity'],
      8   => ['suicide', 'suicide'],
      16  => ['politics', 'politics'],
      32  => ['inadequacy', 'inadequacy'],
      64  => ['threats', 'threats'],
      128 => ['forbidden', 'forbidden'],
    ];

    $reasons = [];
    $messages = [];

    foreach ($map as $flag => [$key, $msg]) {
      if ($bitfield & $flag) {
        $reasons[] = $lng->trans("embed.reason.{$key}");
        $messages[] = $lng->trans("delete.badwords.{$msg}");
      }
    }

    return [
      'list' => implode(', ', $reasons),
      'message' => $messages[0] ?? $lng->trans('delete.badwords.foul')
    ];
  }

  private function getSkipWords(): string
  {
    $skip = $this->model->getBadWordsExeption($this->channel->guild_id);
    return empty($skip) ? '' : implode(', ', array_column($skip, 'word'));
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

  private function extractTextFromImage(string $url): string
  {
    $filesystem = Factory::create();

    $imagePath = $this->downloadImage($url, $filesystem);
    if (empty($imagePath)) {
      return '';
    }

    $processedImagePath = $this->preprocessImage($imagePath);
    $text = $this->recognizeText($processedImagePath);
    // print_r($text);

    if (file_exists($imagePath)) {
      unlink($imagePath);
    }
    if (file_exists($processedImagePath)) {
      unlink($processedImagePath);
    }

    return $text;
  }

  private function downloadImage(string $url, AdapterInterface $filesystem): string
  {
    $path = tempnam(sys_get_temp_dir(), self::TEMP_PREFIX_IMAGE) . '.png';
    $client = new Browser();

    try {
      $response = await($client->get(url: $url));
      $filesystem->file($path)->putContents((string) $response->getBody());
      return $path;
    } catch (Throwable $e) {
      echo 'Err fetch bw: ' .  $e->getMessage(), PHP_EOL;
      return '';
    }
  }

  private function preprocessImage(string $path): string
  {
    $image = new Imagick($path);

    // Преобразование изображения в оттенки серого
    $image->setImageColorspace(Imagick::COLORSPACE_GRAY);

    // Увеличение резкости
    $image->sharpenImage(2, 1);

    // Удаление крапинок и мелких артефактов
    // $image->despeckleImage();

    // Применение медианного фильтра для уменьшения шума
    // $image->statisticImage(Imagick::STATISTIC_MEDIAN, 3, 3);

    // Применение адаптивного размытия
    // $image->adaptiveBlurImage(1, 1);

    // // Дополнительный этап размытия для сглаживания оставшихся шумов
    // $image->gaussianBlurImage(0.5, 0.5);

    // // Дополнительное увеличение контраста для улучшения четкости текста
    // $image->contrastImage(true);

    // Сохранение обработанного изображения во временный файл
    $processedPath = tempnam(sys_get_temp_dir(), self::TEMP_PREFIX_PROCESSED) . '.png';
    $image->writeImage($processedPath);

    // Дополнительное сохранение обработанного изображения для отладки
    // $debugPath = '/root/bots/discordtools-dev/processed_' . uniqid() . '.png';
    // $image->writeImage($debugPath);

    $image->destroy();

    return $processedPath;
  }

  private function recognizeText(string $path): string
  {
    foreach (self::OCR_PSM_MODES as $psm) {
      try {
        $text = (new TesseractOCR($path))->lang(self::OCR_LANGUAGES)->psm($psm)->run();
        if (!empty($text)) return $text;
      } catch (\Throwable $th) {
        // echo 'Err recognize text with PSM ' . $psm . ': ' . $th->getMessage(), PHP_EOL;
      }
    }

    // echo 'Err: Tesseract did not produce any output with any PSM.' . PHP_EOL;
    return '';
  }
}
