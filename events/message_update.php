<?php

use Discord\Discord;
use Discord\WebSockets\Event;
use Discord\Parts\Channel\Message;

$discord->on(Event::MESSAGE_UPDATE, function (Message $message, Discord $discord, ?Message $oldMessage) {
  require 'components/message_prev.php';
});
