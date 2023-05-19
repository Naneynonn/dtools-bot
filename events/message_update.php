<?php

use Discord\Discord;
use Discord\WebSockets\Event;
use Discord\Parts\Channel\Message;

use ByteUnits\Metric;

$discord->on(Event::MESSAGE_UPDATE, function (Message $message, Discord $discord, ?Message $oldMessage) {
  if ($message->author?->bot) return;

  require 'components/message_prev.php';
  require 'components/message_stickers.php';
});
