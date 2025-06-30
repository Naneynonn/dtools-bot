<?php

declare(strict_types=1);

namespace Naneynonn\Automod;

use Ragnarok\Fenrir\Parts\Message;
use Ragnarok\Fenrir\Parts\Webhook;

use Ragnarok\Fenrir\Enums\ImageData;

use Ragnarok\Fenrir\Rest\Helpers\Webhook\CreateWebhookBuilder;
use Ragnarok\Fenrir\Rest\Helpers\Channel\MessageBuilder;

use Carbon\Carbon;
use Throwable;

use Naneynonn\Embeds;
use Naneynonn\Config;
use React\EventLoop\Loop;
use Naneynonn\Core\Cache\Cache;

use function React\Async\await;
use function Naneynonn\getOneWebhook;

final class ResultHandler
{
  use Config;

  public function __construct(private Context $context) {}

  public function handle(array $result): void
  {
    // $start = microtime(true);

    $module = $result['module'];
    $settings = $this->context->settings;

    $rule = current(array_filter(
      $this->context->automod,
      static fn($r) => $r['type'] === $module
    ));

    $this->deleteMessage($result);

    $finalMessage = $result['message'] ?? $this->context->message->content;
    $responseText = $this->outputString(rule: $rule, reason: $result['deleteReason']);

    // $t1 = microtime(true);
    // echo "⏱ Deleted + prepared text: " . round(($t1 - $start) * 1000, 2) . "ms" . PHP_EOL;

    $this->sendResponse(text: $responseText, rule: $rule);

    // $t2 = microtime(true);
    // echo "⏱ Sent response: " . round(($t2 - $t1) * 1000, 2) . "ms" . PHP_EOL;

    $this->logToChannel(finalMessage: $finalMessage, settings: $settings, reason: $result['logReason']);

    // $t3 = microtime(true);
    // echo "⏱ Logged to channel: " . round(($t3 - $t2) * 1000, 2) . "ms" . PHP_EOL;

    $this->addUserTimeout(rule: $rule, settings: $settings, reason: $result['timeoutReason']);

    // $t4 = microtime(true);
    // echo "⏱ Timeout logic: " . round(($t4 - $t3) * 1000, 2) . "ms" . PHP_EOL;
  }


  private function deleteMessage(array $result): void
  {
    $discord = $this->context->discord;
    $message = $this->context->message;

    if (!empty($result['isLazy'])) {
      $discord->rest->channel->bulkDeleteMessages(
        channelId: $message->channel_id,
        messageIds: $result['lazyIds'] ?? [],
        reason: 'Automod'
      );
    } else {
      $discord->rest->channel->deleteMessage(
        channelId: $message->channel_id,
        messageId: $message->id
      );
    }
  }

  private function outputString(array $rule, string $reason): string
  {
    $nowUtc = Carbon::now('UTC')->format('Y-m-d H:i:s.u');
    $message =  $this->context->message;
    $textTemplate = $rule['delete_text'] ?? '';
    $userId = '<@' . $message->author->id . '>';

    if (($this->context->settings['premium'] ?? '') >= $nowUtc && $textTemplate !== '') {
      $reason = strtr($textTemplate, ['{user}' => '%name%']);
    }

    return str_replace('%name%', $userId, $reason);
  }

  private function sendResponse(string $text, array $rule): void
  {
    $interval = $rule['delete_after'];
    $discord = $this->context->discord;

    $discord->rest->channel->createMessage(
      channelId: $this->context->message->channel_id,
      message: MessageBuilder::new()->setContent($text)
    )->then(function (?Message $message) use ($discord, $interval) {
      if (empty($message)) return;

      Loop::get()->addTimer(
        $interval,
        fn() => $discord->rest->channel->deleteMessage(channelId: $message->channel_id, messageId: $message->id)
      );
    })->catch(static function (Throwable $e) {
      echo 'Automod: failed to create response message: ' . $e->getMessage() . PHP_EOL;
    });
  }

  private function logToChannel(string $finalMessage, array $settings, string $reason): void
  {
    $logChannel = $settings['log_channel'] ?? null;
    if (empty($logChannel)) return;

    $discord = $this->context->discord;
    $message = $this->context->message;
    $lng = $this->context->lng;

    $webhooks = $this->getChannelWebhooks(channel_id: $logChannel);
    $webhook = getOneWebhook($webhooks ?? []);

    if (!$webhook) {
      $webhook = await($discord->rest->webhook->create(
        channelId: $logChannel,
        builder: CreateWebhookBuilder::new()
          ->setName($lng->trans('name'))
          ->setAvatar(file_get_contents(self::AVATAR), ImageData::PNG),
        reason: $lng->trans('audit.webhook.create')
      ));
      Cache::del(redis: $this->context->redis, params: [
        'channel_id' => $logChannel,
        'key' => 'automod.getChannelWebhooks'
      ]);
    }

    if ($webhook) {
      Embeds::messageDelete(discord: $discord, webhook: $webhook, message: $message, text: $finalMessage, lng: $lng, reason: $reason);
    }
  }

  private function addUserTimeout(array $rule, array $settings, string $reason): void
  {
    $warnings = $rule['warn_count'];
    $isTimeoutStatus = $rule['is_timeout'];
    $timeCheck = $rule['timeout_check'];
    $timeout = $rule['timeout_seconds'];
    $module = $rule['type'];

    $is_timeout = $this->isTimeTimeout(warnings: $warnings, status: $isTimeoutStatus, time: $timeCheck, module: $module);
    if (!$is_timeout) return;

    $this->setTimeout(rule: $rule);

    if (empty($settings['log_channel'])) return;

    $this->validateWebhook(
      settings: $settings,
      callback: fn(Webhook $webhook) => Embeds::timeoutMember(discord: $this->context->discord, webhook: $webhook, message: $this->context->message, lng: $this->context->lng, reason: $reason, count: $warnings, timeout: $timeout)
    );
  }

  private function isTimeTimeout(int $warnings, bool $status, int $time, string $module): bool
  {
    if (!$status) return false;

    $user_id = $this->context->message->author->id;
    $key = "bot:{$module}:{$user_id}";

    $count = await($this->context->redis->incr($key));

    if ($count >= $warnings) {
      $this->context->redis->del($key);
      return true;
    }

    if ($count == 1) {
      $this->context->redis->expire($key, $time);
    }

    return false;
  }

  private function setTimeout(array $rule): void
  {
    $seconds = $rule['timeout_seconds'] . ' seconds';

    $this->context->discord->rest->guild->modifyMember(
      guildId: $this->context->channel->guild_id,
      userId: $this->context->message->author->id,
      params: ['communication_disabled_until' => new Carbon($seconds)],
      reason: $this->context->lng->trans('audit.timeout')
    );
  }

  private function validateWebhook(array $settings, callable $callback): void
  {
    // $webhooks = await($this->context->discord->rest->webhook->getChannelWebhooks(channelId: $settings['log_channel']));
    $webhooks = $this->getChannelWebhooks(channel_id: $settings['log_channel']);
    $webhook = getOneWebhook(webhooks: $webhooks ?? []);

    if (empty($webhook)) {
      $webhook = await($this->context->discord->rest->webhook->create(
        channelId: $settings['log_channel'],
        builder: CreateWebhookBuilder::new()
          ->setName($this->context->lng->trans('name'))
          ->setAvatar(file_get_contents(self::AVATAR), ImageData::PNG),
        reason: $this->context->lng->trans('audit.webhook.create')
      ));
      Cache::del(redis: $this->context->redis, params: [
        'channel_id' => $settings['log_channel'],
        'key' => 'automod.getChannelWebhooks'
      ]);

      if (empty($webhook)) return;
    }

    $callback($webhook);
  }

  private function getChannelWebhooks(string $channel_id): array
  {
    return Cache::request(
      redis: $this->context->redis,
      fn: fn() => $this->context->discord->rest->webhook->getChannelWebhooks(channelId: $channel_id),
      params: [
        'channel_id' => $channel_id,
        'key' => 'automod.getChannelWebhooks'
      ]
    );
  }
}
