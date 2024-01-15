<?php

namespace Naneynonn;

use Ragnarok\Fenrir\Gateway\Events\MessageCreate;
use Ragnarok\Fenrir\Parts\Message;
use Ragnarok\Fenrir\Parts\Webhook;

use Ragnarok\Fenrir\Gateway\Events\GuildCreate;
use Ragnarok\Fenrir\Gateway\Events\GuildDelete;

use Ragnarok\Fenrir\Rest\Helpers\Channel\EmbedBuilder;
use Ragnarok\Fenrir\Rest\Helpers\Channel\MessageBuilder;

use Woeler\DiscordPhp\Message\DiscordEmbedMessage;
use Woeler\DiscordPhp\Webhook\DiscordWebhook;

use Naneynonn\Language;
use Naneynonn\Config;

final class Embeds
{
  use Config;

  public static function messageDelete(Webhook $webhook, MessageCreate|Message $message, Language $lng, string $reason): void
  {
    $icon = 'https://media.discordapp.net/attachments/686585233339842572/708784170729472080/message_gray_minus_red.png';
    $color = 13974845;

    $channel = "<#{$message->channel_id}>";
    $author = '<@' . $message->author->id . '>';

    $embed = (new DiscordEmbedMessage())
      ->setUsername($lng->trans('name'))
      ->setAvatar(self::AVATAR)
      ->setAuthorName(author_name: $lng->trans('embed.message.delete'))
      ->setAuthorIcon(author_icon: $icon)
      ->addField(title: $lng->trans('embed.channel'), value: $channel, inLine: true)
      ->addField(title: $lng->trans('embed.author'), value: "{$author} | `@{$message->author->username}`", inLine: true)
      ->addField(title: $lng->trans('embed.message.content'), value: ">>> {$message->content}", inLine: false)
      ->addField(title: $lng->trans('embed.reason.name'), value: "> {$reason}", inLine: false)
      ->setFooterText(footer_text: $lng->trans('embed.message.id') . ": {$message->id}")
      ->setColor(color: $color)
      ->setTimestamp(timestamp: new \DateTime());

    $wh = new DiscordWebhook(webhookUrl: $webhook->url);
    $wh->send(message: $embed);
  }

  public static function timeoutMember(Webhook $webhook, MessageCreate|Message $message, Language $lng, string $reason, int $count, int $timeout): void
  {
    $icon = 'https://media.discordapp.net/attachments/686585233339842572/708784067684073492/member_gray_ban_red.png';
    $color = 13974845;

    $author = '<@' . $message->author->id . '>';
    $v = $lng->trans('embed.violations', ['%count%' => $count, '%text%' => wordEnd(num: $count, name: 'violations', lng: $lng)]);

    if ($timeout >= 86400) {
      $days = floor($timeout / 86400);
      $remaining_seconds = $timeout % 86400;
      $time = $days . 'd ' . gmdate("H:i:s", $remaining_seconds);
    } else {
      $time = gmdate("H:i:s", $timeout);
    }

    $embed = (new DiscordEmbedMessage())
      ->setUsername($lng->trans('name'))
      ->setAvatar(self::AVATAR)
      ->setAuthorName(author_name: $lng->trans('embed.user.mute'))
      ->setAuthorIcon(author_icon: $icon)
      ->addField(title: $lng->trans('embed.author'), value: "{$author} | `@{$message->author->username}`", inLine: true)
      ->addField(title: $lng->trans('embed.duration'), value: $time, inLine: true)
      ->addField(title: $lng->trans('embed.reason.name'), value: "> {$reason}, {$v}", inLine: false)
      ->setFooterText(footer_text: $lng->trans('embed.user.id') . ": {$message->author->id}")
      ->setColor(color: $color)
      ->setTimestamp(timestamp: new \DateTime());

    $wh = new DiscordWebhook(webhookUrl: $webhook->url);
    $wh->send(message: $embed);
  }

  public static function sendLogGuild(GuildCreate|GuildDelete $guild, bool $new = true): void
  {
    if ($new) {
      $title = 'Новый сервер';
      $color = 5763720;
    } else {
      $title = 'Сервер удалён';
      $color = 15548997;
    }

    $embed = (new DiscordEmbedMessage())
      ->setTitle(title: $title)
      ->addField(title: 'Название', value: "{$guild->name}", inLine: true)
      ->addField(title: 'ID', value: "{$guild->id}", inLine: true)
      ->addField(title: 'Участников', value: "<:online:581922333493559308> 0 <:all:581922333569318923> {$guild->member_count}", inLine: true)
      ->setColor(color: $color);

    $webhook = new DiscordWebhook(webhookUrl: self::WEBHOOK_ADD);
    $webhook->send(message: $embed);
  }

  public static function errLogGuild(object $event, array $guild): void
  {
    $embed = (new DiscordEmbedMessage())
      ->setTitle(title: 'Гильдия недоступна или удалена')
      ->addField(title: 'Название', value: "{$guild['name']}", inLine: true)
      ->addField(title: 'ID', value: "{$event->id}", inLine: true)
      ->addField(title: 'Участников', value: "<:online:581922333493559308> 0 <:all:581922333569318923> {$guild['members_all']}", inLine: true)
      ->setColor(color: 15548997);

    $webhook = new DiscordWebhook(webhookUrl: self::WEBHOOK_ADD);
    $webhook->send(message: $embed);
  }

  public static function commandHelp(Language $lng): EmbedBuilder
  {
    $color = hexdec(trim($lng->trans('color.default'), '#'));

    return (new EmbedBuilder())
      ->setTitle($lng->trans('embed.help.title', ['%name%' => $lng->trans('name')]))
      ->setColor($color)
      ->setDescription($lng->trans('embed.help.description', ['%web%' => $lng->trans('site'), '%sup%' => $lng->trans('support')]))
      ->addField($lng->trans('embed.commands'), $lng->trans('commands.main'), false)
      ->addField($lng->trans('embed.help.settings'), $lng->trans('commands.automod'), false)
      ->addField($lng->trans('embed.filters'), $lng->trans('commands.filters'), false)
      ->addField($lng->trans('embed.info'), $lng->trans('embed.help.info'), false)
      ->setThumbnail('https://cdn.discordapp.com/attachments/525360788924399637/1099658819023421470/favicon.png');
  }

  public static function noPerm(Language $lng): string
  {
    return $lng->trans('no-perm', ['%perm%' => 'Administrator, Owner']);
  }

  public static function getLang(Language $lng, string $lang): EmbedBuilder
  {
    $color = hexdec(trim('#4f545c', '#'));

    return (new EmbedBuilder())
      ->setDescription($lng->trans('embed.lang.server', ['%lang%' => $lang]))
      ->setColor($color);
  }

  public static function response(string $color, string $title): EmbedBuilder
  {
    $set_color = hexdec(trim($color, '#'));

    return (new EmbedBuilder())
      ->setDescription($title)
      ->setColor($set_color);
  }
}
