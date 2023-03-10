<?php

use Discord\Discord;
use Discord\Parts\Channel\Message;

function getDecodeImage(string $url): string
{
  $path = $url;
  $type = pathinfo($path, PATHINFO_EXTENSION);
  $data = file_get_contents($path);
  return 'data:image/' . $type . ';base64,' . base64_encode($data);
}

function checkBadWords(string $message, string $skip): ?array
{
  $url = 'https://api.discord.band/v1/badwords';

  $body = [
    "message" => $message,
    "type" => 1,
    "skip" => $skip
  ];
  $response_json = json_encode($body, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

  $ch = curl_init();
  curl_setopt($ch, CURLOPT_URL, $url);
  curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "POST");
  curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json', 'Authorization: Bearer ' . CONFIG['api']['token']]);
  curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);
  curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 2);
  curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 1);
  curl_setopt($ch, CURLOPT_POSTFIELDS, $response_json);
  $response = curl_exec($ch);
  curl_close($ch);
  $results = json_decode($response, true);

  return $results;
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

function getNormalEndByLang(int $num, string $name, array $lng): string
{
  $num = abs($num) % 100; // берем число по модулю и сбрасываем сотни (делим на 100, а остаток присваиваем переменной $num)
  $num_x = $num % 10; // сбрасываем десятки и записываем в новую переменную

  if ($num > 10 && $num < 20) return $lng['count'][$name][5]; // если число принадлежит отрезку [11;19] 
  if ($num_x > 1 && $num_x < 5) return $lng['count'][$name][2]; // иначе если число оканчивается на 2,3,4
  if ($num_x == 1) return $lng['count'][$name][1]; // иначе если оканчивается на 1

  return $lng['count'][$name][5];
}

function getLang(string $lang): array
{
  if (file_exists('lang/' . $lang . '.php')) {
    $lang = array_merge_recursive(require 'lang/global.php', require 'lang/' . $lang . '.php');
  } else {
    $lang = array_merge_recursive(require 'lang/global.php', require 'lang/en.php');
  }

  return $lang;
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

function getTextPercent(string $text): float
{
  preg_match_all('/[А-ЯA-Z]/u', $text, $matches);
  return round(count($matches[0]) / mb_strlen($text) * 100, 2);
}

function getReplaceLetters(string $text): bool
{
  // return preg_match('/(?=[а-яА-ЯёЁ]*[a-zA-Z])(?=[a-zA-Z]*[а-яА-ЯёЁ])[\wа-яА-ЯёЁ]+/u', $text) ? true : false;

  // LAST WORK
  // return preg_match('/[\wа-яА-ЯёЁ]+(?=[а-яА-ЯёЁ]*[a-zA-Z])(?=[a-zA-Z]*[а-яА-ЯёЁ])[\wа-яА-ЯёЁ]+/u', $text) ? true : false;
  return preg_match('/\b(?=\w*[а-яА-Я])(?=\w*[a-zA-Z])\w*\b/u', $text) ? true : false;
}

function convert($size)
{
  $unit = ['B', 'KB', 'MB', 'GB', 'TB', 'PB'];
  return @round($size / pow(1024, ($i = floor(log($size, 1024)))), 2) . ' ' . $unit[$i];
}

function getGuildsChannels(Discord $discord): string
{
  $channels_count = 0;
  foreach ($discord->guilds as $guild) {
    $channels_count += $guild->channels->count();
  }

  return $channels_count;
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
