<?php

use Discord\Builders\MessageBuilder;
use Discord\Repository\Channel\WebhookRepository;

use Discord\Parts\Channel\Channel;
use Discord\Parts\Channel\Message;

use Carbon\Carbon;

if (!$settings['is_bw_status']) return;

// Load BadWords Exceptions
$skip = $model->getBadWordsExeption(id: $message->guild->id);
if (!$skip) $skip = '';
else $skip = implode(', ', array_map(function ($entry) {
  return $entry['word'];
}, $skip));


$badword = checkBadWords(message: $message->content, skip: $skip)['badwords'];
if (!$badword) return;

if (!empty($settings['ignored_roles'])) {
  $roles = false;
  $settings['ignored_roles'] = json_decode($settings['ignored_roles']);

  if ($message->member->roles) {
    foreach ($settings['ignored_roles'] as $role) {
      if ($message->member->roles->has($role)) {
        $roles = true;
      }
    }
  }

  if ($roles) return;
}

if (!empty($settings['ignored_channels'])) {
  $settings['ignored_channels'] = json_decode($settings['ignored_channels']);

  if (in_array($message->channel->id, $settings['ignored_channels'])) return;
}

if (!empty($settings['log_channel'])) {
  $message->guild->channels->fetch($settings['log_channel'])->done(function (Channel $channel) use ($message, $discord, $lng) {
    $channel->webhooks->freshen()->done(function (WebhookRepository $webhooks) use ($message, $discord, $lng) {

      $webhook = getOneWebhook(webhooks: $webhooks);

      if (!$webhook) {
        $create = $message->channel->webhooks->create([
          'name' => $lng['wh_log_name'],
          'avatar' => getDecodeImage(url: $discord->user->getAvatarAttribute(format: 'png', size: 1024))
        ]);

        $message->channel->webhooks->save($create)->done(function ($webhook) use ($message, $lng) {
          whLog(webhook: $webhook, message: $message, lng: $lng, reason: $lng['embeds']['foul-lang']);
        });
      } else {
        whLog(webhook: $webhook, message: $message, lng: $lng, reason: $lng['embeds']['foul-lang']);
      }
    });
  });
}

try {
  $is_timeout = isTimeTimeout(user_id: $message->author->id, warnings: $settings['bw_warn_count'], status: $settings['is_bw_timeout_status'], time: $settings['bw_time_check'], module: 'badwords');

  if ($is_timeout) {
    $message->member->timeoutMember(new Carbon($settings['bw_timeout'] . ' seconds'), $lng['embeds']['foul-lang'])->done(function () use ($message, $settings, $discord, $lng) {

      if (!empty($settings['log_channel'])) {
        $message->guild->channels->fetch($settings['log_channel'])->done(function (Channel $channel) use ($message, $discord, $settings, $lng) {
          $channel->webhooks->freshen()->done(function (WebhookRepository $webhooks) use ($message, $discord, $settings, $lng) {

            $webhook = getOneWebhook(webhooks: $webhooks);

            if (!$webhook) {
              $create = $message->channel->webhooks->create([
                'name' => $lng['wh_log_name'],
                'avatar' => getDecodeImage(url: $discord->user->getAvatarAttribute(format: 'png', size: 1024))
              ]);

              $message->channel->webhooks->save($create)->done(function ($webhook) use ($message, $settings, $lng) {
                whLogTimeout(webhook: $webhook, message: $message, lng: $lng, reason: $lng['embeds']['foul-lang'], count: $settings['bw_warn_count'], timeout: $settings['bw_timeout']);
              });
            } else {
              whLogTimeout(webhook: $webhook, message: $message, lng: $lng, reason: $lng['embeds']['foul-lang'], count: $settings['bw_warn_count'], timeout: $settings['bw_timeout']);
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

$message->delete()->done(function () use ($message) {
  echo "[-] BadWords | Удалено: {$message->content}";
});

$del_msg = MessageBuilder::new()
  ->setContent(sprintf($lng['badwords']['delete'], $message->author));

$message->channel->sendMessage($del_msg)->done(function (Message $message) {
  $message->delayedDelete(2500)->done(function () {
  });
});
