<?php

use Discord\Discord;
use Discord\Parts\Channel\Message;
use Discord\Repository\Channel\WebhookRepository;

use Naneynonn\Language;
use Naneynonn\Embeds;

use Carbon\Carbon;
use ByteUnits\Metric;

function getDecodeImage(string $url): string
{
  $path = $url;
  $type = pathinfo($path, PATHINFO_EXTENSION);
  $data = file_get_contents($path);
  return 'data:image/' . $type . ';base64,' . base64_encode($data);
}

function isTimeTimeout(string $user_id, int $warnings, bool $status, int $time, string $module): bool
{
  if (!$status) return false;

  $client = new Predis\Client();
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

function getNormalEnd(int $num, string $for_1, string $for_2, string $for_5): string
{
  $num = abs($num) % 100; // берем число по модулю и сбрасываем сотни (делим на 100, а остаток присваиваем переменной $num)
  $num_x = $num % 10; // сбрасываем десятки и записываем в новую переменную

  if ($num > 10 && $num < 20) return $for_5; // если число принадлежит отрезку [11;19]
  if ($num_x > 1 && $num_x < 5) return $for_2; // иначе если число оканчивается на 2,3,4
  if ($num_x == 1) return $for_1; // иначе если оканчивается на 1

  return $for_5;
}

function wordEnd(int $num, string $name, Language $lng): string
{
  $num = abs($num) % 100; // берем число по модулю и сбрасываем сотни (делим на 100, а остаток присваиваем переменной $num)
  $num_x = $num % 10; // сбрасываем десятки и записываем в новую переменную

  if ($num > 10 && $num < 20) return $lng->get("count.{$name}.5"); // если число принадлежит отрезку [11;19] 
  if ($num_x > 1 && $num_x < 5) return $lng->get("count.{$name}.2"); // иначе если число оканчивается на 2,3,4
  if ($num_x == 1) return $lng->get("count.{$name}.1"); // иначе если оканчивается на 1

  return $lng->get("count.{$name}.5");
}

function getOneWebhook(object $webhooks): object|false
{
  $wh = false;

  foreach ($webhooks as $webhook) {
    if ($webhook->url) {
      // $wh = $webhook->url;
      $wh = $webhook;
      break;
    }
  }

  return $wh;
}

function getIgnoredPermissions(?array $perm, Message $message, string $selection): bool
{
  $type = [
    'channel' => 1,
    'role' => 2,
    'user' => 3,
    'category' => 4
  ];

  if (!$perm) return false;

  $roles = array_filter($perm, fn ($item) => $item['type'] === $type['role']);
  $channels = array_filter($perm, fn ($item) => $item['type'] === $type['channel']);
  $users = array_filter($perm, fn ($item) => $item['type'] === $type['user']);
  $category = array_filter($perm, fn ($item) => $item['type'] === $type['category']);

  $roleIds = array_map(
    fn ($item) => $item['entity_id'],
    array_filter($roles, fn ($item) => $item['selection'] === $selection)
  );
  $channelIds = array_map(
    fn ($item) => $item['entity_id'],
    array_filter($channels, fn ($item) => $item['selection'] === $selection)
  );
  $userIds = array_map(
    fn ($item) => $item['entity_id'],
    array_filter($users, fn ($item) => $item['selection'] === $selection)
  );
  $categoryIds = array_map(
    fn ($item) => $item['entity_id'],
    array_filter($category, fn ($item) => $item['selection'] === $selection)
  );

  if (!empty($roles)) {
    $check_roles = false;

    if (!$message->member->roles) return false;

    foreach ($roleIds as $role) {
      if ($message->member->roles->has($role)) $check_roles = true;
    }

    if ($check_roles) return true;
  }

  if (!empty($channels)) {
    if (in_array($message->channel->id, $channelIds)) return true;
  }

  if (!empty($users)) {
    if (in_array($message->member->id, $userIds)) return true;
  }

  if (!empty($category)) {
    if (in_array($message->channel->parent_id, $categoryIds)) return true;
  }

  return false;
}

function logToChannel(Message $message, Language $lng, Discord $discord, ?string $log_channel, string $reason): void
{
  if (empty($log_channel)) return;

  $channel = $message->guild->channels->get('id', $log_channel);
  $channel->webhooks->freshen()->done(function (WebhookRepository $webhooks) use ($message, $discord, $lng, $reason, $channel) {

    $webhook = getOneWebhook(webhooks: $webhooks);

    if (!$webhook) {
      $create = $channel->webhooks->create([
        'name' => $lng->get('wh_log_name'),
        'avatar' => getDecodeImage(url: $discord->user->getAvatarAttribute(format: 'png', size: 1024))
      ]);

      $channel->webhooks->save($create)->done(function ($webhook) use ($message, $lng, $reason) {
        Embeds::message_delete(webhook: $webhook, message: $message, lng: $lng, reason: $reason);
      });
    } else {
      Embeds::message_delete(webhook: $webhook, message: $message, lng: $lng, reason: $reason);
    }
  });
}

function addUserTimeout(Message $message, Language $lng, Discord $discord, array $settings, string $module, string $reason): void
{
  try {
    $is_timeout = isTimeTimeout(user_id: $message->author->id, warnings: $settings[$module . '_warn_count'], status: $settings['is_' . $module . '_timeout_status'], time: $settings[$module . '_time_check'], module: $module);

    if (!$is_timeout) return;

    $message->member->timeoutMember(new Carbon($settings[$module . '_timeout'] . ' seconds'), $reason)->done(function () use ($message, $settings, $discord, $lng, $module, $reason) {

      if (empty($settings['log_channel'])) return;

      $channel = $message->guild->channels->get('id', $settings['log_channel']);
      $channel->webhooks->freshen()->done(function (WebhookRepository $webhooks) use ($message, $discord, $settings, $lng, $channel, $module, $reason) {

        $webhook = getOneWebhook(webhooks: $webhooks);

        if (!$webhook) {
          $create = $channel->webhooks->create([
            'name' => $lng->get('wh_log_name'),
            'avatar' => getDecodeImage(url: $discord->user->getAvatarAttribute(format: 'png', size: 1024))
          ]);

          $channel->webhooks->save($create)->done(function ($webhook) use ($message, $settings, $lng, $module, $reason) {
            Embeds::timeout_member(webhook: $webhook, message: $message, lng: $lng, reason: $reason, count: $settings[$module . '_warn_count'], timeout: $settings[$module . '_timeout']);
          });
        } else {
          Embeds::timeout_member(webhook: $webhook, message: $message, lng: $lng, reason: $reason, count: $settings[$module . '_warn_count'], timeout: $settings[$module . '_timeout']);
        }
      });



      echo "[-] NEW | {$module} Timeout | " . Metric::bytes(memory_get_usage())->format();
    });
  } catch (\Throwable $th) {
    echo 'Err: ' . $th->getMessage();
    // throw new ErrorException($th);
  }
}
