<?php

use Discord\Discord;
use Discord\WebSockets\Event;
use Discord\Parts\Channel\Message;

$discord->on(Event::MESSAGE_CREATE, function (Message $message, Discord $discord) {
  if ($message->author?->bot) return;

  require 'components/message_prev.php';
  require 'components/message_stickers.php';
});
