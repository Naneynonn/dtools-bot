<?php

use Discord\Discord;
use Discord\WebSockets\Event;
use Discord\Parts\Channel\Message;

$discord->on(Event::MESSAGE_CREATE, function (Message $message, Discord $discord) {
  require 'components/message_badwords.php';
});
