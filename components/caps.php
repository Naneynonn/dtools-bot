<?php

use Discord\Builders\MessageBuilder;
use Discord\Repository\Channel\WebhookRepository;

use Discord\Parts\Channel\Channel;
use Discord\Parts\Channel\Message;

use Carbon\Carbon;

use Naneynonn\Embeds;

use ByteUnits\Metric;

if (!$settings['is_caps_status'] || $stop || mb_strlen($message->content) <= $settings['caps_start_length']) return;

$percent = getTextPercent(text: $message->content);
if ($percent < $settings['caps_percent']) return;
if (getIgnoredPermissions(perm: $perm, message: $message, selection: 'caps')) return;

if (!empty($settings['log_channel'])) {
  $message->guild->channels->fetch($settings['log_channel'])->done(function (Channel $channel) use ($message, $discord, $lng, $settings) {
    $channel->webhooks->freshen()->done(function (WebhookRepository $webhooks) use ($message, $discord, $lng, $settings, $channel) {

      $webhook = getOneWebhook(webhooks: $webhooks);

      if (!$webhook) {
        $create = $channel->webhooks->create([
          'name' => $lng->get('wh_log_name'),
          'avatar' => getDecodeImage(url: $discord->user->getAvatarAttribute(format: 'png', size: 1024))
        ]);

        $channel->webhooks->save($create)->done(function ($webhook) use ($message, $lng, $settings) {
          Embeds::message_delete(webhook: $webhook, message: $message, lng: $lng, reason: sprintf($lng->get('embeds.abuse-caps'), $settings['caps_percent']));
        });
      } else {
        Embeds::message_delete(webhook: $webhook, message: $message, lng: $lng, reason: sprintf($lng->get('embeds.abuse-caps'), $settings['caps_percent']));
      }
    });
  });
}


try {
  $is_timeout = isTimeTimeout(user_id: $message->author->id, warnings: $settings['caps_warn_count'], status: $settings['is_caps_timeout_status'], time: $settings['caps_time_check'], module: 'caps');

  if ($is_timeout) {
    $message->member->timeoutMember(new Carbon($settings['caps_timeout'] . ' seconds'), $lng->get('embeds.caps'))->done(function () use ($message, $settings, $discord, $lng) {

      if (!empty($settings['log_channel'])) {
        $message->guild->channels->fetch($settings['log_channel'])->done(function (Channel $channel) use ($message, $discord, $settings, $lng) {
          $channel->webhooks->freshen()->done(function (WebhookRepository $webhooks) use ($message, $discord, $settings, $lng, $channel) {

            $webhook = getOneWebhook(webhooks: $webhooks);

            if (!$webhook) {
              $create = $channel->webhooks->create([
                'name' => $lng->get('wh_log_name'),
                'avatar' => getDecodeImage(url: $discord->user->getAvatarAttribute(format: 'png', size: 1024))
              ]);

              $channel->webhooks->save($create)->done(function ($webhook) use ($message, $settings, $lng) {
                Embeds::timeout_member(webhook: $webhook, message: $message, lng: $lng, reason: $lng->get('embeds.caps'), count: $settings['caps_warn_count'], timeout: $settings['caps_timeout']);
              });
            } else {
              Embeds::timeout_member(webhook: $webhook, message: $message, lng: $lng, reason: $lng->get('embeds.caps'), count: $settings['caps_warn_count'], timeout: $settings['caps_timeout']);
            }
          });
        });
      }

      echo "[-] Caps Timeout | " . Metric::bytes(memory_get_usage())->format();
    });
  }
} catch (\Throwable $th) {
  echo 'Err: ' . $th->getMessage();
  // throw new ErrorException($th);
}

$message->delete();

$del_msg = MessageBuilder::new()
  ->setContent(sprintf($lng->get('caps.delete'), $message->author));

$message->channel->sendMessage($del_msg)->then(function (Message $message) {
  $message->delayedDelete(2500);
});

$stop = true;
echo '[-] Caps: ' . Metric::bytes(memory_get_usage())->format();
