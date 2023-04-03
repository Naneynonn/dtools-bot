<?php

use Discord\Builders\MessageBuilder;
use Discord\Repository\Channel\WebhookRepository;

use Discord\Parts\Channel\Channel;
use Discord\Parts\Channel\Message;

use Carbon\Carbon;

use Naneynonn\Embeds;

if (!$settings['is_bw_status']) return;
if ($stop) return;

// Load BadWords Exceptions
$skip = $model->getBadWordsExeption(id: $message->guild->id);
if (!$skip) $skip = '';
else $skip = implode(', ', array_map(function ($entry) {
  return $entry['word'];
}, $skip));


$badword_check = checkBadWords(message: $message->content, skip: $skip);
if (!isset($badword_check['badwords'])) return;

$badword = $badword_check['badwords'];
if (!$badword) return;

if (getIgnoredPermissions(perm: $perm, message: $message, selection: 'badwords')) return;

if (!empty($settings['log_channel'])) {
  $message->guild->channels->fetch($settings['log_channel'])->done(function (Channel $channel) use ($message, $discord, $lng) {
    $channel->webhooks->freshen()->done(function (WebhookRepository $webhooks) use ($message, $discord, $lng, $channel) {

      $webhook = getOneWebhook(webhooks: $webhooks);

      if (!$webhook) {
        $create = $channel->webhooks->create([
          'name' => $lng->get('wh_log_name'),
          'avatar' => getDecodeImage(url: $discord->user->getAvatarAttribute(format: 'png', size: 1024))
        ]);

        $channel->webhooks->save($create)->done(function ($webhook) use ($message, $lng) {
          Embeds::message_delete(webhook: $webhook, message: $message, lng: $lng, reason: $lng->get('embeds.foul-lang'));
        });
      } else {
        Embeds::message_delete(webhook: $webhook, message: $message, lng: $lng, reason: $lng->get('embeds.foul-lang'));
      }
    });
  });
}

try {
  $is_timeout = isTimeTimeout(user_id: $message->author->id, warnings: $settings['bw_warn_count'], status: $settings['is_bw_timeout_status'], time: $settings['bw_time_check'], module: 'badwords');

  if ($is_timeout) {
    $message->member->timeoutMember(new Carbon($settings['bw_timeout'] . ' seconds'), $lng->get('embeds.foul-lang'))->done(function () use ($message, $settings, $discord, $lng) {

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
                Embeds::timeout_member(webhook: $webhook, message: $message, lng: $lng, reason: $lng->get('embeds.foul-lang'), count: $settings['bw_warn_count'], timeout: $settings['bw_timeout']);
              });
            } else {
              Embeds::timeout_member(webhook: $webhook, message: $message, lng: $lng, reason: $lng->get('embeds.foul-lang'), count: $settings['bw_warn_count'], timeout: $settings['bw_timeout']);
            }
          });
        });
      }

      echo "[-] BadWords Timeout | " . convert(memory_get_peak_usage(true));
    });
  }
} catch (\Throwable $th) {
  echo 'Err: ' . $th->getMessage();
  // throw new ErrorException($th);
}

$message->delete()->done(function () {
  echo "[-] BadWords | " . convert(memory_get_usage(true)) . " | " . convert(memory_get_peak_usage(true));
});

$del_msg = MessageBuilder::new()
  ->setContent(sprintf($lng->get('badwords.delete'), $message->author));

$message->channel->sendMessage($del_msg)->done(function (Message $message) {
  $message->delayedDelete(2500)->done(function () {
  });
});

$stop = true;
