<?php

use Woeler\DiscordPhp\Message\DiscordEmbedMessage;
use Woeler\DiscordPhp\Webhook\DiscordWebhook;

function whBadWords(object $webhook, object $message, array $lng): void
{
  $lng = $lng['embeds'];

  $embed = (new DiscordEmbedMessage())
    ->setAuthorName($lng['msg-delete'])
    ->setAuthorIcon('https://media.discordapp.net/attachments/686585233339842572/708784170729472080/message_gray_minus_red.png')
    ->addField($lng['channel'], "{$message->channel}", true)
    ->addField($lng['author'], "{$message->author} | `{$message->author->username}#{$message->author->discriminator}`", true)
    ->addField($lng['msg-content'], ">>> {$message->content}", false)
    ->addField($lng['reason'], '> ' . $lng['foul-lang'], false)
    ->setFooterText($lng['msg-id'] . ": {$message->id}")
    ->setColor(13974845)
    ->setTimestamp(new DateTime());

  $webhook = new DiscordWebhook($webhook->url);
  $webhook->send($embed);
}

function whLogServer(object $guild, bool $new = true): void
{
  if ($new) {
    $title = 'Новый сервер';
    $color = 5763720;
  } else {
    $title = 'Сервер удалён';
    $color = 15548997;
  }

  $embed = (new DiscordEmbedMessage())
    ->setTitle($title)
    ->addField('Название', "{$guild->name}", true)
    ->addField('ID', "{$guild->id}", true)
    ->addField('Участников', "<:online:581922333493559308> 0 <:all:581922333569318923> {$guild->member_count}", true)
    ->setColor($color);

  $webhook = new DiscordWebhook(CONFIG['webhook']['add']);
  $webhook->send($embed);
}

function whErrLogServer(object $guild): void
{

  $embed = (new DiscordEmbedMessage())
    ->setTitle('Гильдия недоступна или удалена')
    ->addField('ID', "{$guild->id}", true)
    ->setColor(15548997);

  $webhook = new DiscordWebhook(CONFIG['webhook']['add']);
  $webhook->send($embed);
}

function whBadwordsTimeout(object $webhook, object $message, array $settings, array $lng): void
{
  $lng_all = $lng;
  $lng = $lng['embeds'];

  $embed = (new DiscordEmbedMessage())
    ->setAuthorName($lng['mute-user'])
    ->setAuthorIcon('https://media.discordapp.net/attachments/686585233339842572/708784067684073492/member_gray_ban_red.png')
    ->addField($lng['author'], "{$message->author} | `{$message->author->username}#{$message->author->discriminator}`", true)
    ->addField($lng['duration'], gmdate("H:i:s", $settings['automod_timeout']), true)
    ->addField($lng['reason'], sprintf('> ' . $lng['foul-lang'] . ', ' . $lng['violations'], $settings['automod_count'], getNormalEndByLang(num: $settings['automod_count'], name: 'violations', lng: $lng_all)), false)
    ->setFooterText($lng['user-id'] . ": {$message->author->id}")
    ->setColor(13974845)
    ->setTimestamp(new DateTime());

  $webhook = new DiscordWebhook($webhook->url);
  $webhook->send($embed);
}
