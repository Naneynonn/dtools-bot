<?php

use Discord\Builders\MessageBuilder;
use Discord\Repository\Channel\WebhookRepository;

use Discord\Parts\Channel\Channel;
use Discord\Parts\Channel\Message;

use Carbon\Carbon;

use Naneynonn\Embeds;

use ByteUnits\Metric;

if (!$settings['is_zalgo_status'] || $stop) return;

$zalgo = isZalgo(text: $message->content);
if (!$zalgo) return;
if (getIgnoredPermissions(perm: $perm, message: $message, selection: 'zalgo')) return;

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
          Embeds::message_delete(webhook: $webhook, message: $message, lng: $lng, reason: $lng->get('embeds.zalgo-text'));
        });
      } else {
        Embeds::message_delete(webhook: $webhook, message: $message, lng: $lng, reason: $lng->get('embeds.zalgo-text'));
      }
    });
  });
}


try {
  $is_timeout = isTimeTimeout(user_id: $message->author->id, warnings: $settings['zalgo_warn_count'], status: $settings['is_zalgo_timeout_status'], time: $settings['zalgo_time_check'], module: 'zalgo');

  if ($is_timeout) {
    $message->member->timeoutMember(new Carbon($settings['zalgo_timeout'] . ' seconds'), $lng->get('embeds.zalgo-text'))->done(function () use ($message, $settings, $discord, $lng) {

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
                Embeds::timeout_member(webhook: $webhook, message: $message, lng: $lng, reason: $lng->get('embeds.zalgo-text'), count: $settings['zalgo_warn_count'], timeout: $settings['zalgo_timeout']);
              });
            } else {
              Embeds::timeout_member(webhook: $webhook, message: $message, lng: $lng, reason: $lng->get('embeds.zalgo-text'), count: $settings['zalgo_warn_count'], timeout: $settings['zalgo_timeout']);
            }
          });
        });
      }

      echo "[-] Zalgo Timeout | " . Metric::bytes(memory_get_usage())->format();
    });
  }
} catch (\Throwable $th) {
  echo 'Err: ' . $th->getMessage();
  // throw new ErrorException($th);
}

$message->delete();

$del_msg = MessageBuilder::new()
  ->setContent(sprintf($lng->get('zalgo.delete'), $message->author));

$message->channel->sendMessage($del_msg)->then(function (Message $message) {
  $message->delayedDelete(2500);
});

$stop = true;
echo '[-] Zalgo: ' . Metric::bytes(memory_get_usage())->format();
