<?php

declare(strict_types=1);

namespace Naneynonn\Reactions;

use Naneynonn\Core\Cache\Cache;
use Ragnarok\Fenrir\Discord;
use Ragnarok\Fenrir\Gateway\Events\MessageCreate;
use Ragnarok\Fenrir\Parts\Emoji;
use Ragnarok\Fenrir\Rest\Helpers\Emoji\EmojiBuilder;

use Naneynonn\Memory;
use Naneynonn\Model;

use Random\Engine\Mt19937;
use Random\Randomizer;
use Clue\React\Redis\LazyClient as RedisClient;
use Ragnarok\Fenrir\Parts\Channel;
use Ragnarok\Fenrir\Parts\Guild;

use function Naneynonn\getIgnoredPermissions;
use function React\Async\async;

final class RandomReaction
{
  use Memory;

  private Discord $discord;
  private MessageCreate $message;
  private Randomizer $random;
  private RedisClient $redis;

  public function __construct(Discord $discord, MessageCreate $message, RedisClient $redis)
  {
    $this->discord = $discord;
    $this->message = $message;
    $this->redis = $redis;

    $this->random = new Randomizer(new Mt19937());
  }

  public function get(): void
  {
    if (!$this->isAllow()) return;

    $model = new Model();
    $settings = $model->getSettingsReactions(id: $this->message->guild_id);
    if (!$settings || !$settings['is_enable']) return;

    $channel = $this->getChannel();
    if (empty($channel)) return;

    $perm = $model->getServerPerm(id: $this->message->guild_id, module: 'reactions');
    if ($this->isSkip(perm: $perm, channel: $channel)) return;

    $this->handle();
  }

  private function handle(): void
  {
    async(function () {
      try {
        $guild = $this->getGuild();

        $num = $this->getRandomEmoji(emojis: $guild->emojis);
        $this->createReaction(emoji: $guild->emojis[$num]);

        $this->getMemoryUsage(text: 'Random Reaction |');
      } catch (\Throwable $th) {
        echo 'reactions.rendom: ' . $th->getMessage() . PHP_EOL;
      }
    })();
  }

  private function isAllow(): bool
  {
    return $this->random->getInt(1, 100) === 1;
  }

  private function getRandomEmoji(array $emojis): int
  {
    return $this->random->getInt(0, count($emojis) - 1);
  }

  private function createReaction(Emoji $emoji): void
  {
    $this->discord->rest->channel->createReaction(
      channelId: $this->message->channel_id,
      messageId: $this->message->id,
      emoji: EmojiBuilder::new()
        ->setId($emoji->id)
        ->setName($emoji->name)
        ->setAnimated($emoji->animated)
    );
  }

  private function getChannel(): ?Channel
  {
    return Cache::request(
      redis: $this->redis,
      fn: fn() => $this->discord->rest->channel->get($this->message->channel_id),
      params: ['channel_id' => $this->message->channel_id]
    );
  }

  private function getGuild(): ?Guild
  {
    return Cache::request(
      redis: $this->redis,
      fn: fn() => $this->discord->rest->guild->get(guildId: $this->message->guild_id),
      params: ['guild_id' => $this->message->guild_id]
    );
  }

  private function isSkip(array $perm, Channel $channel): bool
  {
    return getIgnoredPermissions(perm: $perm, message: $this->message, parent_id: $channel->parent_id, selection: 'all') || getIgnoredPermissions(perm: $perm, message: $this->message, parent_id: $channel->parent_id, selection: 'random');
  }
}
