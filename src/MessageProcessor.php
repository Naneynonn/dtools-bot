<?php

declare(strict_types=1);

namespace Naneynonn;

use Ragnarok\Fenrir\Discord;
use Ragnarok\Fenrir\Rest\Helpers\Channel\MessageBuilder;

use Ragnarok\Fenrir\Gateway\Events\MessageCreate;
use Ragnarok\Fenrir\Gateway\Events\MessageUpdate;
use Ragnarok\Fenrir\Parts\Message;
use Ragnarok\Fenrir\Parts\Channel;
use Ragnarok\Fenrir\Parts\GuildMember;

use Ragnarok\Fenrir\Parts\Webhook;
use Ragnarok\Fenrir\Rest\Helpers\Webhook\CreateWebhookBuilder;
use Ragnarok\Fenrir\Enums\ImageData;

use Naneynonn\Language;
use Naneynonn\Model;
use Naneynonn\Embeds;
use Naneynonn\Config;
use Naneynonn\Memory;
use Naneynonn\Core\Cache\Cache;

use Naneynonn\Filter\Caps;
use Naneynonn\Filter\BadWords;
use Naneynonn\Filter\Replace;
use Naneynonn\Filter\Zalgo;
use Naneynonn\Filter\Duplicate;
use Naneynonn\Filter\Russian;

use Carbon\Carbon;
use Clue\React\Redis\LazyClient as RedisClient;
use React\EventLoop\LoopInterface;

use function React\Promise\any;
use function React\Async\await;
use function React\Async\async;
use function Naneynonn\getIgnoredPermissions;

use Exception;
use Throwable;

final class MessageProcessor
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

  private function isEmpty(): bool
  {
    return ($this->message->author->bot ?? false) || empty($this->message) || (empty($this->message->content) && empty($this->message->sticker_items));
  }

  private function getChannel(): Channel
  {
    return Cache::request(
      redis: $this->redis,
      fn: fn () => $this->discord->rest->channel->get($this->message->channel_id),
      params: ['channel_id' => $this->message->channel_id]
    );
  }

  private function getMember(Channel $channel): GuildMember
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

  public function process(): void
  {
    if ($this->isEmpty()) return;

    async(function () {
      try {
        $channel = $this->getChannel();
        $params = [$channel];

        if (empty($this->message->member)) {
          $member = $this->getMember(channel: $channel);
          $params[] = $member;
        }

        $this->startCode(...$params);
      } catch (Throwable $th) {
        echo 'Err Message Async: ' . $th->getMessage();
      }
    })();
  }


  private function startCode(Channel $channel, ?GuildMember $member = null): void
  {
    $model = new Model();

    $settings = $model->getSettingsServer(id: $channel->guild_id);
    if (!$settings || !$settings['is_enable']) return;

    $perm = $model->getServerPerm(id: $channel->guild_id, module: 'automod');
    if (getIgnoredPermissions(perm: $perm, message: $this->message, parent_id: $channel->parent_id, selection: 'all', member: $member)) return;

    $this->lng->setLocale($settings['lang']);

    if (!empty($this->message->content)) {
      $this->processContent(model: $model, channel: $channel, settings: $settings, perm: $perm, member: $member);
    }

    if (!empty($this->message->sticker_items)) {
      $this->processStickers(model: $model, channel: $channel, settings: $settings, perm: $perm, member: $member);
    }
  }

  private function processContent(Model $model, Channel $channel, array $settings, array $perm, ?GuildMember $member = null): void
  {
    $params = [$this->message, $this->lng, $settings, $perm, $channel, $member];
    $paramsBadWords = [$this->message, $this->lng, $settings, $perm, $model, $channel, $member, $this->redis];

    $bad = new BadWords(...$paramsBadWords);
    $promises = [
      (new Caps(...$params))->process(),
      (new Replace(...$params))->process(),
      (new Zalgo(...$params))->process(),
      (new Duplicate(...$params))->process(),
      $bad->process(),
      $bad->processLazyWords(),
      (new Russian(...$params))->process()
    ];

    any($promises)->then(function ($result) use ($settings, $channel) {
      $module = $result['module'];
      $reason = $result['reason']['log'];
      $reason_timeout = $result['reason']['timeout'];
      $reason_del = isset($result['reason']['delete']) ? $result['reason']['delete'] : $this->lng->trans('delete.' . $module);

      if (!empty($result['lazy'])) {
        $this->discord->rest->channel->bulkDeleteMessages(channelId: $this->message->channel_id, messageIds: $result['lazy']['ids'], reason: 'BadWords');
        $this->message->content = $result['lazy']['message'];
      } else {
        $this->discord->rest->channel->deleteMessage(channelId: $this->message->channel_id, messageId: $this->message->id);
      }

      $text_string = outputString(settings: $settings, module: $module, message: $this->message, reason: $reason_del);
      $getDelText = MessageBuilder::new()->setContent($text_string);
      $this->discord->rest->channel->createMessage($this->message->channel_id, $getDelText)->then(function (Message $message) use ($settings, $module) {
        $this->loop->addTimer(
          $settings[$module . '_delete_after_seconds'],
          fn () => $this->discord->rest->channel->deleteMessage($message->channel_id, $message->id)
        );
      })->otherwise(function (Exception $e) {
        echo 'Error createMessage: ' . $e->getMessage() . PHP_EOL;
      });

      $this->logToChannel(settings: $settings, reason: $reason);
      $this->addUserTimeout(settings: $settings, module: $module, reason: $reason_timeout, channel: $channel);
      $this->getMemoryUsage(text: '[-] Del Message');
    })->otherwise(function (Exception $e) {
      echo 'Error any: ' . $e->getMessage() . PHP_EOL;
    });
  }

  private function processStickers(Model $model, Channel $channel, array $settings, array $perm, ?GuildMember $member = null): void
  {
    $stickers = $this->message->sticker_items;

    foreach ($stickers as $sticker) {
      $promises = [
        (new BadWords(message: $this->message, lng: $this->lng, settings: $settings, perm: $perm, model: $model, channel: $channel, member: $member))->processStickers(sticker: $sticker)
      ];

      any($promises)->then(function ($result) use ($settings, $sticker, $channel) {
        $module = $result['module'];
        $reason = $result['reason']['log'];
        $reason_timeout = $result['reason']['timeout'];
        $reason_del = isset($result['reason']['delete']) ? $result['reason']['delete'] : $this->lng->trans('delete.' . $module);
        $this->message->content = $this->lng->trans('embed.sticker-name', ['%sticker%' => $sticker->name]);

        $this->discord->rest->channel->deleteMessage(channelId: $this->message->channel_id, messageId: $this->message->id);

        $text_string = outputString(settings: $settings, module: $module, message: $this->message, reason: $reason_del);
        $getDelText = MessageBuilder::new()->setContent($text_string);
        $this->discord->rest->channel->createMessage($this->message->channel_id, $getDelText)->then(function (Message $message) use ($settings, $module) {
          $this->loop->addTimer(
            $settings[$module . '_delete_after_seconds'],
            fn () => $this->discord->rest->channel->deleteMessage($message->channel_id, $message->id)
          );
        })->otherwise(function (Exception $e) {
          echo 'Error sticker createMessage: ' . $e->getMessage() . PHP_EOL;
        });

        $this->logToChannel(settings: $settings, reason: $reason);
        $this->addUserTimeout(settings: $settings, module: $module, reason: $reason_timeout, channel: $channel);
        $this->getMemoryUsage(text: '[-] Del Sticker');
      })->otherwise(function (Exception $e) {
        echo 'Error sticker any: ' . $e->getMessage() . PHP_EOL;
      });
    }
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
}
