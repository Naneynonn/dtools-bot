<?php

namespace Naneynonn;

use Woeler\DiscordPhp\Message\DiscordEmbedMessage;
use Woeler\DiscordPhp\Webhook\DiscordWebhook;

use Discord\Discord;
use Discord\Parts\Embed\Embed;
use Discord\Parts\Guild\Guild;
use Discord\Parts\Channel\Message;
use Discord\Builders\MessageBuilder;

use Naneynonn\Language;

use DateTime;

class Embeds extends Config
{
  public static function message_delete(object $webhook, Message $message, Language $lng, string $reason): void
  {
    $icon = 'https://media.discordapp.net/attachments/686585233339842572/708784170729472080/message_gray_minus_red.png';
    $color = 13974845;

    $embed = (new DiscordEmbedMessage())
      ->setAuthorName(author_name: $lng->get('embeds.msg-delete'))
      ->setAuthorIcon(author_icon: $icon)
      ->addField(title: $lng->get('embeds.channel'), value: "{$message->channel}", inLine: true)
      ->addField(title: $lng->get('embeds.author'), value: "{$message->author} | `{$message->author->username}#{$message->author->discriminator}`", inLine: true)
      ->addField(title: $lng->get('embeds.msg-content'), value: ">>> {$message->content}", inLine: false)
      ->addField(title: $lng->get('embeds.reason'), value: "> {$reason}", inLine: false)
      ->setFooterText(footer_text: $lng->get('embeds.msg-id') . ": {$message->id}")
      ->setColor(color: $color)
      ->setTimestamp(timestamp: new DateTime());

    $webhook = new DiscordWebhook(webhookUrl: $webhook->url);
    $webhook->send(message: $embed);
  }

  public static function log_servers(Guild $guild, bool $new = true): void
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

  public static function err_log_servers(Guild $guild): void
  {
    $color = 15548997;

    $embed = (new DiscordEmbedMessage())
      ->setTitle(title: 'Гильдия недоступна или удалена')
      ->addField(title: 'ID', value: "{$guild->id}", inLine: true)
      ->setColor(color: $color);

    $webhook = new DiscordWebhook(webhookUrl: self::WEBHOOK_ADD);
    $webhook->send(message: $embed);
  }

  public static function timeout_member(object $webhook, Message $message, Language $lng, string $reason, int $count, int $timeout): void
  {
    $icon = 'https://media.discordapp.net/attachments/686585233339842572/708784067684073492/member_gray_ban_red.png';
    $color = 13974845;

    $embed = (new DiscordEmbedMessage())
      ->setAuthorName(author_name: $lng->get('embeds.mute-user'))
      ->setAuthorIcon(author_icon: $icon)
      ->addField(title: $lng->get('embeds.author'), value: "{$message->author} | `{$message->author->username}#{$message->author->discriminator}`", inLine: true)
      ->addField(title: $lng->get('embeds.duration'), value: gmdate("H:i:s", $timeout), inLine: true)
      ->addField(title: $lng->get('embeds.reason'), value: sprintf("> {$reason}, " . $lng->get('embeds.violations'), $count, wordEnd(num: $count, name: 'violations', lng: $lng)), inLine: false)
      ->setFooterText(footer_text: $lng->get('embeds.user-id') . ": {$message->author->id}")
      ->setColor(color: $color)
      ->setTimestamp(timestamp: new DateTime());

    $webhook = new DiscordWebhook(webhookUrl: $webhook->url);
    $webhook->send(message: $embed);
  }

  public static function no_perm(Language $lng): MessageBuilder
  {
    return MessageBuilder::new()->setContent(sprintf($lng->get('no-perm'), 'Administrator, Owner'));
  }

  public static function response(object $discord, string $color, string $title): MessageBuilder
  {
    $embed = $discord->factory(Embed::class)
      ->setDescription($title)
      ->setColor($color);

    return MessageBuilder::new()->addEmbed($embed);
  }
}
