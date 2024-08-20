<?php

declare(strict_types=1);

namespace Naneynonn;

use Ragnarok\Fenrir\Gateway\Events\MessageCreate;
use Ragnarok\Fenrir\Gateway\Events\MessageUpdate;

use Ragnarok\Fenrir\Parts\Message;
use Ragnarok\Fenrir\Parts\GuildMember;
use Ragnarok\Fenrir\Parts\Webhook;

use Ragnarok\Fenrir\Enums\Permission;

function getIgnoredPermissions(?array $perm, MessageUpdate|MessageCreate $message, string $selection, ?GuildMember $member = null, ?string $parent_id = null): bool
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

  // Использование ?-> для безопасного обращения к свойству member
  $currentMember = $member ?? $message->member ?? null;

  if (!empty($ids['role']) && !empty($currentMember?->roles) && array_intersect($ids['role'], $currentMember->roles)) {
    return true;
  }

  if (!empty($ids['channel']) && in_array($message->channel_id, $ids['channel'], true)) {
    return true;
  }

  if (!empty($ids['user']) && in_array($message->author->id, $ids['user'], true)) {
    return true;
  }

  if (!empty($ids['category']) && $parent_id && in_array($parent_id, $ids['category'], true)) {
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

function hasPermission(int $bitmask, Permission $permission): bool
{
  return ($bitmask & $permission->value) === $permission->value;
}

function isObjectEmpty(object $object): bool
{
  return empty((array) $object);
}
