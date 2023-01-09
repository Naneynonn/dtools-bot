<?php

use Discord\Discord;
use Discord\WebSockets\Event;
use Discord\Parts\Channel\Message;

$discord->on(Event::MESSAGE_UPDATE, function (Message $message, Discord $discord, ?Message $oldMessage) {
  if ($message->author?->bot) return;

  $model = new DB();
  $settings = $model->getSettingsServer(id: $message->guild->id);
  if (!$settings || !$settings['is_enable']) return;

  // Load Lang
  $lng = getLang(lang: $settings['lang']);

  if (!$message->content) return;

  require 'components/caps.php';
  require 'components/message_badwords.php';
});
