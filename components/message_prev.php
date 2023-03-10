<?php

$stop = false;

if ($message->author?->bot || !$message->content) return;

$model = new DB();
$settings = $model->getSettingsServer(id: $message->guild->id);
if (!$settings || !$settings['is_enable']) return;

$perm = $model->getServerPerm(id: $message->guild->id, module: 'automod');
if (getIgnoredPermissions(perm: $perm, message: $message, selection: 'all')) return;

// Load Lang
$lng = getLang(lang: $settings['lang']);

require 'components/caps.php';
require 'components/replace.php';
require 'components/message_badwords.php';

unset($model);
