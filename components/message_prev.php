<?php

use Naneynonn\Language;
use Naneynonn\Model;

use Naneynonn\Filter\Caps;
use Naneynonn\Filter\BadWords;
use Naneynonn\Filter\Replace;
use Naneynonn\Filter\Zalgo;

use React\Promise\Promise;
use function React\Promise\any;

use Discord\Parts\Channel\Message;
use Discord\Builders\MessageBuilder;

use ByteUnits\Metric;

$stop = false;

if ($message->author?->bot || !$message->content) return;

$model = new Model();

try {
  $settings = $model->getSettingsServer(id: $message->guild->id);
  if (!$settings || !$settings['is_enable']) return;

  $perm = $model->getServerPerm(id: $message->guild->id, module: 'automod');
  if (getIgnoredPermissions(perm: $perm, message: $message, selection: 'all')) return;

  // Load Lang
  $lng = new Language(lang: $settings['lang']);

  $promises = [
    (new Caps(message: $message, lng: $lng, settings: $settings, perm: $perm))->process(),
    (new Replace(message: $message, lng: $lng, settings: $settings, perm: $perm))->process(),
    (new Zalgo(message: $message, lng: $lng, settings: $settings, perm: $perm))->process(),
    (new BadWords(message: $message, lng: $lng, settings: $settings, perm: $perm, model: $model))->process()
  ];

  any($promises)->then(
    function ($result) use ($lng, $discord, $settings, $message) {
      $module = $result['module'];
      $reason = $result['reason']['log'];
      $reason_timeout = $result['reason']['timeout'];

      $message->delete();

      logToChannel(message: $message, lng: $lng, discord: $discord, log_channel: $settings['log_channel'], reason: $reason);
      addUserTimeout(message: $message, lng: $lng, discord: $discord, settings: $settings, module: $module, reason: $reason_timeout);

      $del_msg = MessageBuilder::new()
        ->setContent(sprintf($lng->get($module . '.delete'), $message->author));

      $message->channel->sendMessage($del_msg)->then(function (Message $message) {
        $message->delayedDelete(2500);
      });

      echo "[-] NEW | {$module}: " . Metric::bytes(memory_get_usage())->format();
    },
    function ($reason) {
      echo "[-] NEW | REJECT: " . Metric::bytes(memory_get_usage())->format();
    }
  );

  // $settings = null;
  // $perm = null;
  // $lng = null;
} finally {
  $settings = null;
  $perm = null;
  $lng = null;
  $message = null;
  $discord = null;

  $model->close();
}
