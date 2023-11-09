<?php

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

use Naneynonn\Filter\Caps;
use Naneynonn\Filter\BadWords;
use Naneynonn\Filter\Replace;
use Naneynonn\Filter\Zalgo;
use Naneynonn\Filter\Duplicate;
use Naneynonn\Filter\Russian;

use Predis\Client;
use React\EventLoop\Loop;
use Carbon\Carbon;

use function React\Promise\any;

final class MessageProcessor
{
  use Config;
  use Memory;

  private MessageCreate|MessageUpdate $message;
  private Discord $discord;
  private Language $lng;

  public function __construct(MessageCreate|MessageUpdate $message, Discord $discord, Language $lng)
  {
    $this->message = $message;
    $this->discord = $discord;
    $this->lng = clone $lng;
  }

  public function process(): void
  {
    if (($this->message->author->bot ?? false) || empty($this->message)) return;
    if (empty($this->message->content) && empty($this->message->sticker_items)) return;

    $this->discord->rest->channel->get($this->message->channel_id)->then(function (Channel $channel) {

      if (empty($this->message->member)) {
        $this->discord->rest->guild->getMember(guildId: $channel->guild_id, memberId: $this->message->author->id)->then(function (GuildMember $member) use ($channel) {
          $this->startCode(channel: $channel, member: $member);
        });
      } else {
        $this->startCode(channel: $channel);
      }
    });
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

  private function processContent(Model $model, Channel $channel, ?GuildMember $member = null, array $settings, array $perm): void
  {

    $promises = [
      (new Caps(message: $this->message, lng: $this->lng, settings: $settings, perm: $perm, channel: $channel, member: $member))->process(),
      (new Replace(message: $this->message, lng: $this->lng, settings: $settings, perm: $perm, channel: $channel, member: $member))->process(),
      (new Zalgo(message: $this->message, lng: $this->lng, settings: $settings, perm: $perm, channel: $channel, member: $member))->process(),
      (new Duplicate(message: $this->message, lng: $this->lng, settings: $settings, perm: $perm, channel: $channel, member: $member))->process(),
      (new BadWords(message: $this->message, lng: $this->lng, settings: $settings, perm: $perm, model: $model, channel: $channel, member: $member))->process(),
      (new Russian(message: $this->message, lng: $this->lng, settings: $settings, perm: $perm, channel: $channel, member: $member))->process()
    ];

    any($promises)->then(function ($result) use ($settings) {
      $module = $result['module'];
      $reason = $result['reason']['log'];
      $reason_timeout = $result['reason']['timeout'];
      $reason_del = isset($result['reason']['delete']) ? $result['reason']['delete'] : $this->lng->trans('delete.' . $module);

      $this->discord->rest->channel->deleteMessage(channelId: $this->message->channel_id, messageId: $this->message->id);

      $text_string = outputString(settings: $settings, module: $module, message: $this->message, reason: $reason_del);
      $getDelText = MessageBuilder::new()->setContent($text_string);
      $this->discord->rest->channel->createMessage($this->message->channel_id, $getDelText)->then(function (Message $message) use ($settings, $module) {
        Loop::get()->addTimer($settings[$module . '_delete_after_seconds'], fn () => $this->discord->rest->channel->deleteMessage($message->channel_id, $message->id));
      });

      $this->logToChannel(settings: $settings, reason: $reason);
      $this->addUserTimeout(settings: $settings, module: $module, reason: $reason_timeout);
      $this->getMemoryUsage(text: '[-] Del Message');
    });
  }

  private function processStickers(Model $model, Channel $channel, ?GuildMember $member = null, array $settings, array $perm): void
  {
    $stickers = $this->message->sticker_items;

    foreach ($stickers as $sticker) {
      $promises = [
        (new BadWords(message: $this->message, lng: $this->lng, settings: $settings, perm: $perm, model: $model, channel: $channel, member: $member))->processStickers(sticker: $sticker)
      ];

      any($promises)->then(function ($result) use ($settings, $sticker) {
        $module = $result['module'];
        $reason = $result['reason']['log'];
        $reason_timeout = $result['reason']['timeout'];
        $reason_del = isset($result['reason']['delete']) ? $result['reason']['delete'] : $this->lng->trans('delete.' . $module);
        $this->message->content = $this->lng->trans('embed.sticker-name', ['%sticker%' => $sticker->name]);

        $this->discord->rest->channel->deleteMessage(channelId: $this->message->channel_id, messageId: $this->message->id);

        $text_string = outputString(settings: $settings, module: $module, message: $this->message, reason: $reason_del);
        $getDelText = MessageBuilder::new()->setContent($text_string);
        $this->discord->rest->channel->createMessage($this->message->channel_id, $getDelText)->then(function (Message $message) {
          Loop::get()->addTimer(6.5, fn () => $this->discord->rest->channel->deleteMessage($message->channel_id, $message->id));
        });

        $this->logToChannel(settings: $settings, reason: $reason);
        $this->addUserTimeout(settings: $settings, module: $module, reason: $reason_timeout);
        $this->getMemoryUsage(text: '[-] Del Sticker');
      });
    }
  }

  private function logToChannel(array $settings, string $reason): void
  {
    if (empty($settings['log_channel'])) return;

    $this->validateWebhook(settings: $settings, callback: function (Webhook $webhook) use ($reason) {
      Embeds::messageDelete(webhook: $webhook, message: $this->message, lng: $this->lng, reason: $reason);
    });
  }

  private function addUserTimeout(array $settings, string $module, string $reason): void
  {
    $warnings = $module . '_warn_count';
    $isTimeoutStatus = 'is_' . $module . '_timeout_status';
    $timeCheck = $module . '_time_check';
    $timeout = $module . '_timeout';

    $is_timeout = $this->isTimeTimeout(warnings: $settings[$warnings], status: $settings[$isTimeoutStatus], time: $settings[$timeCheck], module: $module);
    if (!$is_timeout) return;

    $this->setTimeout(settings: $settings, timeout: $timeout);

    if (empty($settings['log_channel'])) return;

    $this->validateWebhook(settings: $settings, callback: function (Webhook $webhook) use ($reason, $warnings, $timeout, $settings) {
      Embeds::timeoutMember(webhook: $webhook, message: $this->message, lng: $this->lng, reason: $reason, count: $settings[$warnings], timeout: $settings[$timeout]);
    });
  }

  private function isTimeTimeout(int $warnings, bool $status, int $time, string $module): bool
  {
    if (!$status) return false;

    $user_id = $this->message->author->id;

    $client = new Client();
    $key = "bot:{$module}:{$user_id}";

    if ($client->exists(key: $key)) {
      $count = $client->incr(key: $key);
      if ($count >= $warnings) {
        $client->del(key: $key);
        return true;
      }
    } else {
      $client->setex(key: $key, seconds: $time, value: 1);
    }

    return false;
  }

  private function setTimeout(array $settings, string $timeout): void
  {
    $seconds = $settings[$timeout] . ' seconds';

    $this->discord->rest->guild->modifyMember(
      guildId: $this->message->guild_id,
      userId: $this->message->author->id,
      params: ['communication_disabled_until' => new Carbon($seconds)],
      reason: $this->lng->trans('audit.timeout')
    );
  }

  private function validateWebhook(array $settings, callable $callback): void
  {
    $this->discord->rest->webhook->getChannelWebhooks(channelId: $settings['log_channel'])->then(function (array $webhooks) use ($settings, $callback) {
      $webhook = getOneWebhook(webhooks: $webhooks);

      if (!$webhook) {
        $data = CreateWebhookBuilder::new()
          ->setName($this->lng->trans('name'))
          ->setAvatar(file_get_contents(self::AVATAR), ImageData::PNG);

        $this->discord->rest->webhook->create(
          channelId: $settings['log_channel'],
          builder: $data,
          reason: $this->lng->trans('audit.webhook.create')
        )->then(function (Webhook $webhook) use ($callback) {
          $callback($webhook);
        });
      }

      $callback($webhook);
    });
  }
}
