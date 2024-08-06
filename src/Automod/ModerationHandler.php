<?php

declare(strict_types=1);

namespace Naneynonn\Automod;

use Ragnarok\Fenrir\Discord;

use Ragnarok\Fenrir\Gateway\Events\MessageCreate;
use Ragnarok\Fenrir\Gateway\Events\MessageUpdate;

use Ragnarok\Fenrir\Parts\Message;
use Ragnarok\Fenrir\Parts\Channel;
use Ragnarok\Fenrir\Parts\GuildMember;
use Ragnarok\Fenrir\Parts\Webhook;

use Ragnarok\Fenrir\Rest\Helpers\Webhook\CreateWebhookBuilder;
use Ragnarok\Fenrir\Rest\Helpers\Channel\MessageBuilder;

use Ragnarok\Fenrir\Enums\ImageData;
use Ragnarok\Fenrir\Enums\EmbedType;

use React\EventLoop\LoopInterface;
use Clue\React\Redis\LazyClient as RedisClient;
use Carbon\Carbon;

use Exception;

use Naneynonn\Language;
use Naneynonn\Model;
use Naneynonn\Embeds;
use Naneynonn\Config;
use Naneynonn\Memory;
use Naneynonn\Core\Cache\Cache;

use Naneynonn\Automod\Filter\Caps;
use Naneynonn\Automod\Filter\BadWords;
use Naneynonn\Automod\Filter\Replace;
use Naneynonn\Automod\Filter\Zalgo;
use Naneynonn\Automod\Filter\Duplicate;
use Naneynonn\Automod\Filter\Russian;

use function React\Promise\any;
use function React\Async\await;
use function React\Async\async;

use function Naneynonn\getIgnoredPermissions;
use function Naneynonn\outputString;
use function Naneynonn\getOneWebhook;

final class ModerationHandler
{
  use Config, Memory;

  private MessageCreate|MessageUpdate $message;
  private Discord $discord;
  private Language $lng;
  private RedisClient $redis;
  private LoopInterface $loop;

  public function __construct(MessageCreate|MessageUpdate $message, Discord $discord, Language $lng, RedisClient $redis, LoopInterface $loop)
  {
    $this->message = $message;
    $this->discord = $discord;
    $this->lng = clone $lng;
    $this->redis = $redis;
    $this->loop = $loop;
  }

  public function handle(): void
  {
    if ($this->isEmpty()) return;

    async(function () {
      try {
        $this->prepareContext();
      } catch (\Throwable $th) {
        echo 'automod.handle: ' . $th->getMessage() . PHP_EOL;
      }
    })();
  }

  private function prepareContext(): void
  {
    $channel = $this->getChannel();
    $params = [$channel];

    if (empty($this->message->member)) {
      $member = $this->getMember(channel: $channel);
      $params[] = $member;
    }

    $this->process(...$params);
  }

  private function process(Channel $channel, ?GuildMember $member = null): void
  {
    $promises = [];
    $model = new Model();

    $settings = $model->getSettingsServer(id: $channel->guild_id);
    if (!$settings || !$settings['is_enable']) return;

    $perm = $model->getServerPerm(id: $channel->guild_id, module: 'automod');
    if (getIgnoredPermissions(perm: $perm, message: $this->message, parent_id: $channel->parent_id, selection: 'all', member: $member)) return;

    $this->lng->setLocale($settings['lang']);

    $params = [$model, $channel, $settings, $perm, $member];

    if (!empty($this->message->content)) {
      $promises = array_merge($promises, $this->processContent(...$params));
    }

    if (!empty($this->message->sticker_items)) {
      $promises = array_merge($promises, $this->processStickers(...$params));
    }

    any($promises)->then(function ($result) use ($settings, $channel) {
      $type = !empty($result['type']) ? ' ' . $result['type'] : null;

      $this->deleteMessage(ids: $result['lazyIds'] ?? null, isLazy: $result['isLazy'] ?? null);
      if (!empty($result['message'])) $this->message->content = $result['message'];

      $text_string = outputString(settings: $settings, module: $result['module'], message: $this->message, reason: $result['deleteReason']);
      $this->createMessage(content: $text_string, settings: $settings, module: $result['module']);

      $this->logToChannel(settings: $settings, reason: $result['logReason']);
      $this->addUserTimeout(settings: $settings, module: $result['module'], reason: $result['timeoutReason'], channel: $channel);
      $this->getMemoryUsage(text: '[-] Del mod: ' . $result['module'] . $type);
    }, function () use ($params, $settings, $channel) {
      $promises = [];
      if (!empty($this->message->attachments) || !empty($this->message->embeds)) {
        $promises = array_merge($promises, $this->processImages(...$params));
      }

      any($promises)->then(function ($result) use ($settings, $channel) {
        $type = !empty($result['type']) ? ' ' . $result['type'] : null;

        $this->deleteMessage(ids: $result['lazyIds'] ?? null, isLazy: $result['isLazy'] ?? null);
        if (!empty($result['message'])) $this->message->content = $result['message'];

        $text_string = outputString(settings: $settings, module: $result['module'], message: $this->message, reason: $result['deleteReason']);
        $this->createMessage(content: $text_string, settings: $settings, module: $result['module']);

        $this->logToChannel(settings: $settings, reason: $result['logReason']);
        $this->addUserTimeout(settings: $settings, module: $result['module'], reason: $result['timeoutReason'], channel: $channel);
        $this->getMemoryUsage(text: '[-] Del mod: ' . $result['module'] . $type);
      });
    });
  }

  private function processContent(Model $model, Channel $channel, array $settings, array $perm, ?GuildMember $member = null): array
  {
    $params = [$this->message, $this->lng, $settings, $perm, $channel, $member];
    $paramsBadWords = [$this->message, $this->lng, $settings, $perm, $model, $channel, $member, $this->redis];

    $bad = new BadWords(...$paramsBadWords);
    return [
      (new Caps(...$params))->process(),
      (new Replace(...$params))->process(),
      (new Zalgo(...$params))->process(),
      (new Duplicate(...$params))->process(),
      $bad->process(),
      $bad->processLazyWords(),
      (new Russian(...$params))->process()
    ];
  }

  private function processStickers(Model $model, Channel $channel, array $settings, array $perm, ?GuildMember $member = null): array
  {
    $promises = [];
    $stickers = $this->message->sticker_items;
    $badwords = (new BadWords(message: $this->message, lng: $this->lng, settings: $settings, perm: $perm, model: $model, channel: $channel, member: $member, redis: $this->redis));

    foreach ($stickers as $sticker) {
      $promises[] = $badwords->processStickers(sticker: $sticker);
    }

    return $promises;
  }

  private function processImages(Model $model, Channel $channel, array $settings, array $perm, ?GuildMember $member = null): array
  {
    $promises = [];
    $embeds = $this->message->embeds;
    $attachments = $this->message->attachments;

    $badwords = (new BadWords(message: $this->message, lng: $this->lng, settings: $settings, perm: $perm, model: $model, channel: $channel, member: $member, redis: $this->redis));

    foreach ($embeds as $embed) {
      if ($embed->type === EmbedType::IMAGE) {
        $promises[] = $badwords->processImage(url: $embed->url);
      } elseif ($embed->type === EmbedType::GIFV) {
        $promises[] = $badwords->processImage(url: $embed->thumbnail->url);
      }
    }

    foreach ($attachments as $attachment) {
      if (strpos($attachment->content_type, 'image/') === 0) {
        $promises[] = $badwords->processImage(url: $attachment->url);
      }
    }

    return $promises;
  }

  private function logToChannel(array $settings, string $reason): void
  {
    if (empty($settings['log_channel'])) return;

    $this->validateWebhook(
      settings: $settings,
      callback: fn (Webhook $webhook) => Embeds::messageDelete(discord: $this->discord, webhook: $webhook, message: $this->message, lng: $this->lng, reason: $reason)
    );
  }

  private function addUserTimeout(array $settings, string $module, string $reason, Channel $channel): void
  {
    $warnings = $module . '_warn_count';
    $isTimeoutStatus = 'is_' . $module . '_timeout_status';
    $timeCheck = $module . '_time_check';
    $timeout = $module . '_timeout';

    $is_timeout = $this->isTimeTimeout(warnings: $settings[$warnings], status: $settings[$isTimeoutStatus], time: $settings[$timeCheck], module: $module);
    if (!$is_timeout) return;

    $this->setTimeout(settings: $settings, timeout: $timeout, channel: $channel);

    if (empty($settings['log_channel'])) return;

    $this->validateWebhook(
      settings: $settings,
      callback: fn (Webhook $webhook) => Embeds::timeoutMember(discord: $this->discord, webhook: $webhook, message: $this->message, lng: $this->lng, reason: $reason, count: $settings[$warnings], timeout: $settings[$timeout])
    );
  }

  private function isTimeTimeout(int $warnings, bool $status, int $time, string $module): bool
  {
    if (!$status) return false;

    $user_id = $this->message->author->id;
    $key = "bot:{$module}:{$user_id}";

    $count = await($this->redis->incr($key));

    if ($count >= $warnings) {
      $this->redis->del($key);
      return true;
    }

    if ($count == 1) {
      $this->redis->expire($key, $time);
    }

    return false;
  }


  private function setTimeout(array $settings, string $timeout, Channel $channel): void
  {
    $seconds = $settings[$timeout] . ' seconds';

    $this->discord->rest->guild->modifyMember(
      guildId: $channel->guild_id,
      userId: $this->message->author->id,
      params: ['communication_disabled_until' => new Carbon($seconds)],
      reason: $this->lng->trans('audit.timeout')
    );
  }

  private function validateWebhook(array $settings, callable $callback): void
  {
    $params = ['log_channel' => $settings['log_channel']];
    $webhooks = await($this->discord->rest->webhook->getChannelWebhooks(channelId: $settings['log_channel']));
    // $webhooks = Cache::request(
    //   redis: $this->redis,
    //   fn: fn () => $this->discord->rest->webhook->getChannelWebhooks(channelId: $settings['log_channel']),
    //   params: $params
    // );
    $webhook = getOneWebhook(webhooks: $webhooks);

    if (!$webhook) {
      $webhook = await($this->discord->rest->webhook->create(
        channelId: $settings['log_channel'],
        builder: CreateWebhookBuilder::new()
          ->setName($this->lng->trans('name'))
          ->setAvatar(file_get_contents(self::AVATAR), ImageData::PNG),
        reason: $this->lng->trans('audit.webhook.create')
      ));
      // Cache::del(redis: $this->redis, params: $params);
    }

    $callback($webhook);
  }

  private function getChannel(): ?Channel
  {
    return Cache::request(
      redis: $this->redis,
      fn: fn () => $this->discord->rest->channel->get($this->message->channel_id),
      params: ['channel_id' => $this->message->channel_id]
    );
  }

  private function getMember(Channel $channel): ?GuildMember
  {
    return Cache::request(
      redis: $this->redis,
      fn: fn () => $this->discord->rest->guild->getMember(guildId: $channel->guild_id, memberId: $this->message->author->id),
      params: [
        'guild_id' => $channel->guild_id,
        'member_id' => $this->message->author->id
      ]
    );
  }

  private function createMessage(string $content, array $settings, string $module): void
  {
    $interval = $settings[$module . '_delete_after_seconds'];

    $this->discord->rest->channel->createMessage(
      channelId: $this->message->channel_id,
      message: MessageBuilder::new()
        ->setContent($content)
    )->then(function (Message $message) use ($interval) {
      $this->loop->addTimer(
        $interval,
        fn () => $this->discord->rest->channel->deleteMessage(channelId: $message->channel_id, messageId: $message->id)
      );
    })->otherwise(function (Exception $e) {
      echo 'automod.handle.createMessage: ' . $e->getMessage() . PHP_EOL;
    });
  }

  private function deleteMessage(?array $ids = null, ?bool $isLazy = null): void
  {
    if (!empty($isLazy)) {
      $this->discord->rest->channel->bulkDeleteMessages(channelId: $this->message->channel_id, messageIds: $ids, reason: 'BadWords');
    } else {
      $this->discord->rest->channel->deleteMessage(channelId: $this->message->channel_id, messageId: $this->message->id);
    }
  }

  private function isEmpty(): bool
  {
    return ($this->message->author->bot ?? false) || empty($this->message) || (empty($this->message->content) && empty($this->message->sticker_items) && empty($this->message->attachments) && !empty($this->message->embeds));
  }
}
