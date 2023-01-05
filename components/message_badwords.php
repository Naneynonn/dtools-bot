<?php

use Discord\Builders\MessageBuilder;
use Discord\Repository\Channel\WebhookRepository;

use Discord\Parts\Channel\Channel;
use Discord\Parts\Channel\Message;

// use Discord\Parts\User\Member;
use Discord\Parts\User\Member;

use Carbon\Carbon;

if ($message->author?->bot) return;

$model = new DB();
$settings = $model->getSettingsServer(id: $message->guild->id);
if (!$settings) return;

$lng = array_merge_recursive(require 'lang/global.php', require 'lang/' . $settings['lang'] . '.php');


if (!$message->content) return;
$badword = checkBadWords(message: $message->content)['badwords'];
if (!$badword) return;

if (!empty($settings['ignored_roles'])) {
  $roles = false;
  $settings['ignored_roles'] = json_decode($settings['ignored_roles']);

  if ($message->author->roles) {
    foreach ($settings['ignored_roles'] as $role) {
      if ($message->author->roles->has($role)) {
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

      $wh = '';
      foreach ($webhooks as $webhook) {
        if ($webhook->url) {
          $wh = $webhook->url;
          break;
        }
      }

      if (empty($wh)) {
        $newwebhook = $message->channel->webhooks->create([
          'name' => 'DTools Logs',
          'avatar' => getDecodeImage(url: $discord->user->getAvatarAttribute(format: 'png', size: 1024))
        ]);

        $message->channel->webhooks->save($newwebhook)->done(function ($webhook) use ($message, $lng) {
          whBadWords(webhook: $webhook, message: $message, lng: $lng);
        });
      }

      whBadWords(webhook: $webhook, message: $message, lng: $lng);
    });
  });
}


if (isTimeTimeout(user_id: $message->author->id, warnings: $settings['automod_count'])) {
  $message->member->timeoutMember(new Carbon($settings['automod_timeout'] . ' seconds'), 'Нецензурная брань')->done(function () use ($message, $settings, $discord, $lng) {

    if (!empty($settings['log_channel'])) {
      $message->guild->channels->fetch($settings['log_channel'])->done(function (Channel $channel) use ($message, $discord, $settings, $lng) {
        $channel->webhooks->freshen()->done(function (WebhookRepository $webhooks) use ($message, $discord, $settings, $lng) {

          $wh = '';
          foreach ($webhooks as $webhook) {
            if ($webhook->url) {
              $wh = $webhook->url;
              break;
            }
          }

          if (empty($wh)) {
            $newwebhook = $message->channel->webhooks->create([
              'name' => 'DTools Logs',
              'avatar' => getDecodeImage(url: $discord->user->getAvatarAttribute(format: 'png', size: 1024))
            ]);

            $message->channel->webhooks->save($newwebhook)->done(function ($webhook) use ($message, $settings, $lng) {
              whBadwordsTimeout(webhook: $webhook, message: $message, settings: $settings, lng: $lng);
            });
          }

          whBadwordsTimeout(webhook: $webhook, message: $message, settings: $settings, lng: $lng);
        });
      });
    }

    echo "[-] Таймаут: {$message->author->username}";
  });
}

$message->delete()->done(function () use ($message) {
  echo "[-] Удалено: {$message->content}";
});

$del_msg = MessageBuilder::new()
  ->setContent(sprintf($lng['badwords']['delete'], $message->author));

$message->channel->sendMessage($del_msg)->done(function (Message $message) {
  $message->delayedDelete(2500)->done(function () {
  });
});
