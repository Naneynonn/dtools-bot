<?php

declare(strict_types=1);

namespace Naneynonn;

use Ragnarok\Fenrir\Discord;
use Ragnarok\Fenrir\Parts\Message;
use Ragnarok\Fenrir\Parts\Webhook;

use Ragnarok\Fenrir\Gateway\Events\GuildCreate;
use Ragnarok\Fenrir\Gateway\Events\GuildDelete;
use Ragnarok\Fenrir\Gateway\Events\MessageCreate;

use Ragnarok\Fenrir\Rest\Helpers\Channel\EmbedBuilder;
use Ragnarok\Fenrir\Rest\Helpers\Webhook\WebhookBuilder;

use Woeler\DiscordPhp\Message\DiscordEmbedMessage;
use Woeler\DiscordPhp\Webhook\DiscordWebhook;

use Carbon\Carbon;

use Naneynonn\Language;
use Naneynonn\Config;
use Naneynonn\Core\Enums\Color;
use Naneynonn\Core\Enums\Emoji;

final class Embeds
{
  use Config;

  private static function hexToDec(string $color): int|float
  {
    return hexdec(trim($color, '#'));
  }

  public static function messageDelete(Discord $discord, Webhook $webhook, MessageCreate|Message $message, string $text, Language $lng, string $reason): void
  {
    $icon = 'https://media.discordapp.net/attachments/686585233339842572/708784170729472080/message_gray_minus_red.png';
    $color = 13974845;

    $channel = "<#{$message->channel_id}>";
    $author = '<@' . $message->author->id . '>';
    $content = mb_strimwidth($text, 0, 1000, "...");

    $discord->rest->webhook->execute(
      webhookId: $webhook->id,
      token: $webhook->token,
      builder: WebhookBuilder::new()
        ->setUsername($lng->trans('name'))
        ->setAvatarUrl(self::AVATAR)
        ->addEmbed(
          EmbedBuilder::new()
            ->setAuthor(name: $lng->trans('embed.message.delete'), iconUrl: $icon)
            ->addField(name: $lng->trans('embed.channel'), value: $channel, inline: true)
            ->addField(name: $lng->trans('embed.author'), value: "{$author} | `@{$message->author->username}`", inline: true)
            ->addField(name: $lng->trans('embed.message.content'), value: ">>> {$content}", inline: false)
            ->addField(name: $lng->trans('embed.reason.name'), value: "> {$reason}", inline: false)
            ->setFooter(text: $lng->trans('embed.message.id') . ": {$message->id}")
            ->setColor($color)
            ->setTimestamp(Carbon::now())
        )
    );
  }

  public static function timeoutMember(Discord $discord, Webhook $webhook, MessageCreate|Message $message, Language $lng, string $reason, int $count, int $timeout): void
  {
    $icon = 'https://media.discordapp.net/attachments/686585233339842572/708784067684073492/member_gray_ban_red.png';
    $color = 13974845;

    $author = '<@' . $message->author->id . '>';
    $v = $lng->trans('embed.violations', ['%count%' => $count, '%text%' => $lng->trans('count.violations', ['count' => $count])]);

    if ($timeout >= 86400) {
      $days = floor($timeout / 86400);
      $remaining_seconds = $timeout % 86400;
      $time = $days . 'd ' . gmdate("H:i:s", $remaining_seconds);
    } else {
      $time = gmdate("H:i:s", $timeout);
    }

    $discord->rest->webhook->execute(
      webhookId: $webhook->id,
      token: $webhook->token,
      builder: WebhookBuilder::new()
        ->setUsername($lng->trans('name'))
        ->setAvatarUrl(self::AVATAR)
        ->addEmbed(
          EmbedBuilder::new()
            ->setAuthor(name: $lng->trans('embed.user.mute'), iconUrl: $icon)
            ->addField(name: $lng->trans('embed.author'), value: "{$author} | `@{$message->author->username}`", inline: true)
            ->addField(name: $lng->trans('embed.duration'), value: $time, inline: true)
            ->addField(name: $lng->trans('embed.reason.name'), value: "> {$reason}, {$v}", inline: false)
            ->setFooter(text: $lng->trans('embed.user.id') . ": {$message->id}")
            ->setColor($color)
            ->setTimestamp(Carbon::now())
        )
    );
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

  public static function response(int|string|Color $color, string $title, ?Emoji $emoji = null): EmbedBuilder
  {
    if (is_string($color)) {
      $color = self::hexToDec($color);
    } elseif ($color instanceof Color) {
      $color = $color->value;
    }

    if (!empty($emoji)) {
      $title = "{$emoji->value} {$title}";
    }

    return (new EmbedBuilder())
      ->setDescription($title)
      ->setColor($color);
  }

  public static function danger(string $text): EmbedBuilder
  {
    return self::response(color: Color::DANGER, emoji: Emoji::ERROR, title: $text);
  }

  public static function success(string $text): EmbedBuilder
  {
    return self::response(color: Color::SUCCESS, emoji: Emoji::ENABLE, title: $text);
  }

  public static function info(string $text, Emoji $emoji = Emoji::INFO): EmbedBuilder
  {
    return self::response(color: Color::INFO, emoji: $emoji, title: $text);
  }

  public static function noPerm(Language $lng): EmbedBuilder
  {
    return self::danger(text: $lng->trans('no-perm', ['%perm%' => 'Administrator, Owner']));
  }
}
