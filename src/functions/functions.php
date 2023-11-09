<?php

use Ragnarok\Fenrir\Gateway\Events\MessageCreate;
use Ragnarok\Fenrir\Gateway\Events\MessageUpdate;
use Ragnarok\Fenrir\Parts\Message;

use Ragnarok\Fenrir\Parts\GuildMember;

use Ragnarok\Fenrir\Parts\Webhook;

use Ragnarok\Fenrir\Enums\Permission;

use Naneynonn\Language;

function getIgnoredPermissions(?array $perm, MessageUpdate|MessageCreate $message, ?GuildMember $member = null, ?string $parent_id = null, string $selection): bool
{
  if (!$perm) return false;

  $type = [
    'channel' => 1,
    'role' => 2,
    'user' => 3,
    'category' => 4
  ];

  $ids = [
    'role' => [],
    'channel' => [],
    'user' => [],
    'category' => []
  ];

  // Один проход по $perm для фильтрации и извлечения ID
  foreach ($perm as $item) {
    $entityType = array_search($item['type'], $type);
    if ($entityType && $item['selection'] === $selection) {
      $ids[$entityType][] = $item['entity_id'];
    }
  }

  // Проверки
  if (!empty($member)) {
    if (!empty($ids['role']) && $member->roles && array_intersect($ids['role'], $member->roles)) {
      return true;
    }
  } else {
    if (!empty($ids['role']) && $message->member->roles && array_intersect($ids['role'], $message->member->roles)) {
      return true;
    }
  }

  if (!empty($ids['channel']) && in_array($message->channel_id, $ids['channel'])) {
    return true;
  }

  if (!empty($ids['user']) && in_array($message->author->id, $ids['user'])) {
    return true;
  }

  if (!empty($ids['category']) && $parent_id && in_array($parent_id, $ids['category'])) {
    return true;
  }

  return false;
}


function has(array $array, array|string $values): bool
{
  // Если передано одно значение (строка), оборачиваем его в массив
  if (is_string($values)) {
    $values = [$values];
  }

  // Проверяем, есть ли все значения из $values в $array
  foreach ($values as $value) {
    if (!in_array($value, $array, true)) {
      return false;
    }
  }

  return true;
}

function getOneWebhook(array $webhooks): Webhook|false
{
  $wh = false;

  foreach ($webhooks as $webhook) {
    if (!empty($webhook->url)) {
      $wh = $webhook;
      break;
    }
  }

  return $wh;
}

function outputString(array $settings, string $module, Message|MessageCreate|MessageUpdate $message, string $reason): string
{
  $date = gmdate('Y-m-d H:i:s.u');
  $text = $settings[$module . '_delete_text'];
  $replacePairs = [
    "{user}" => '%name%'
  ];
  $user_id = '<@' . $message->author->id . '>';

  if ($settings['premium'] >= $date && !empty($text)) {
    $reason = str_replace(array_keys($replacePairs), array_values($replacePairs), $text);
  }

  return str_replace('%name%', $user_id, $reason);
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

  if ($num > 10 && $num < 20) return $lng->trans("count.{$name}.5"); // если число принадлежит отрезку [11;19] 
  if ($num_x > 1 && $num_x < 5) return $lng->trans("count.{$name}.2"); // иначе если число оканчивается на 2,3,4
  if ($num_x == 1) return $lng->trans("count.{$name}.1"); // иначе если оканчивается на 1

  return $lng->trans("count.{$name}.5");
}

function is_object_empty(object $object): bool
{
  return empty((array) $object);
}

function hasPermission(int $bitmask, Permission $permission): bool
{
  return ($bitmask & $permission->value) === $permission->value;
}
