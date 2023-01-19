<?php

$stop = false;

if ($message->author?->bot || !$message->content) return;

$model = new DB();
$settings = $model->getSettingsServer(id: $message->guild->id);
if (!$settings || !$settings['is_enable']) return;

$settings['ignored_roles'] = json_decode($settings['ignored_roles']);
$settings['ignored_channels'] = json_decode($settings['ignored_channels']);

// Load Lang
$lng = getLang(lang: $settings['lang']);

require 'components/caps.php';
require 'components/replace.php';
require 'components/message_badwords.php';
