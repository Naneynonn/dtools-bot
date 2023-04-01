<?php

use Discord\Builders\MessageBuilder;
use Discord\Repository\Channel\WebhookRepository;

use Discord\Parts\Channel\Channel;
use Discord\Parts\Channel\Message;

use Carbon\Carbon;

if (!$settings['is_caps_status']) return;
if ($stop) return;

if (mb_strlen($message->content) <= 3) return;

$percent = getTextPercent(text: $message->content);

if ($percent < $settings['caps_percent']) return;

if (getIgnoredPermissions(perm: $perm, message: $message, selection: 'caps')) return;

if (!empty($settings['log_channel'])) {
  $message->guild->channels->fetch($settings['log_channel'])->done(function (Channel $channel) use ($message, $discord, $lng, $settings) {
    $channel->webhooks->freshen()->done(function (WebhookRepository $webhooks) use ($message, $discord, $lng, $settings, $channel) {

      $webhook = getOneWebhook(webhooks: $webhooks);

      if (!$webhook) {
        $create = $channel->webhooks->create([
          'name' => $lng['wh_log_name'],
          'avatar' => getDecodeImage(url: $discord->user->getAvatarAttribute(format: 'png', size: 1024))
        ]);

        $channel->webhooks->save($create)->done(function ($webhook) use ($message, $lng, $settings) {
          whLog(webhook: $webhook, message: $message, lng: $lng, reason: sprintf($lng['embeds']['abuse-caps'], $settings['caps_percent']));
        });
      } else {
        whLog(webhook: $webhook, message: $message, lng: $lng, reason: sprintf($lng['embeds']['abuse-caps'], $settings['caps_percent']));
      }
    });
  });
}


try {
  $is_timeout = isTimeTimeout(user_id: $message->author->id, warnings: $settings['caps_warn_count'], status: $settings['is_caps_timeout_status'], time: $settings['caps_time_check'], module: 'caps');

  if ($is_timeout) {
    $message->member->timeoutMember(new Carbon($settings['caps_timeout'] . ' seconds'), $lng['embeds']['caps'])->done(function () use ($message, $settings, $discord, $lng) {

      if (!empty($settings['log_channel'])) {
        $message->guild->channels->fetch($settings['log_channel'])->done(function (Channel $channel) use ($message, $discord, $settings, $lng) {
          $channel->webhooks->freshen()->done(function (WebhookRepository $webhooks) use ($message, $discord, $settings, $lng, $channel) {

            $webhook = getOneWebhook(webhooks: $webhooks);

            if (!$webhook) {
              $create = $channel->webhooks->create([
                'name' => $lng['wh_log_name'],
                'avatar' => getDecodeImage(url: $discord->user->getAvatarAttribute(format: 'png', size: 1024))
              ]);

              $channel->webhooks->save($create)->done(function ($webhook) use ($message, $settings, $lng) {
                whLogTimeout(webhook: $webhook, message: $message, lng: $lng, reason: $lng['embeds']['caps'], count: $settings['caps_warn_count'], timeout: $settings['caps_timeout']);
              });
            } else {
              whLogTimeout(webhook: $webhook, message: $message, lng: $lng, reason: $lng['embeds']['caps'], count: $settings['caps_warn_count'], timeout: $settings['caps_timeout']);
            }
          });
        });
      }

      echo "[-] Caps | Таймаут: {$message->author->username}";
    });
  }
} catch (\Throwable $th) {
  echo 'Err: ' . $th->getMessage();
  // throw new ErrorException($th);
}

$message->delete()->done(function () {
  echo "[-] Caps | " . convert(memory_get_usage(true));
});

$del_msg = MessageBuilder::new()
  ->setContent(sprintf($lng['caps']['delete'], $message->author));

$message->channel->sendMessage($del_msg)->done(function (Message $message) {
  $message->delayedDelete(2500)->done(function () {
  });
});

$stop = true;
