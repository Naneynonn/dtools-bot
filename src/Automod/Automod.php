<?php

declare(strict_types=1);

namespace Naneynonn\Automod;

use Ragnarok\Fenrir\Discord;
use Ragnarok\Fenrir\Gateway\Events\MessageCreate;
use Ragnarok\Fenrir\Gateway\Events\MessageUpdate;
use Ragnarok\Fenrir\Parts\Channel;
use Ragnarok\Fenrir\Parts\GuildMember;
use React\EventLoop\LoopInterface;
use Clue\React\Redis\RedisClient;
use Throwable;

use Naneynonn\Language;
use Naneynonn\Model;
use Naneynonn\Core\Cache\Cache;

use Naneynonn\Automod\Filter\Caps;
use Naneynonn\Automod\Filter\Badwords;
use Naneynonn\Automod\Filter\Replace;
use Naneynonn\Automod\Filter\Zalgo;
use Naneynonn\Automod\Filter\Duplicate;
use Naneynonn\Automod\Filter\Russian;
use Naneynonn\Automod\Filter\Invite;

use function React\Promise\any;
use function React\Async\async;
use function Naneynonn\getIgnoredPermissions;

final class Automod
{
  private const array FILTER_MAP = [
    'caps'      => Caps::class,
    'replace'   => Replace::class,
    'zalgo'     => Zalgo::class,
    'duplicate' => Duplicate::class,
    'invite'    => Invite::class,
    'badwords'  => Badwords::class,
    'russian'   => Russian::class,
  ];

  public function __construct(
    private MessageCreate|MessageUpdate $message,
    private Discord $discord,
    private Language $lng,
    private RedisClient $redis,
    private LoopInterface $loop
  ) {}

  public function handle(): void
  {
    if ($this->isSkippable()) return;

    async(function () {
      try {
        $this->asyncHandle();
      } catch (Throwable $th) {
        echo "Automod error: {$th->getMessage()}" . PHP_EOL;
      }
    })();
  }

  private function isSkippable(): bool
  {
    return ($this->message->author->bot ?? false)
      || (
        empty($this->message->content)
        && empty($this->message->sticker_items)
        && empty($this->message->attachments)
        && empty($this->message->embeds)
      );
  }

  private function asyncHandle(): void
  {
    $channel = $this->getChannel();
    if (!$channel) return;

    $model = new Model();
    $settings = $model->getSettingsServer($channel->guild_id);
    if (!$settings || !$settings['is_enable']) return;

    $rulesAutomod = $model->getAutomodSettings($channel->guild_id);
    if (!$rulesAutomod) return;

    $this->lng->setLocale($settings['lang']);
    $member = $this->message->member ?? $this->getMember(channel: $channel);

    $permissions = $model->getServerPerm($channel->guild_id, 'automod');
    if (getIgnoredPermissions(
      perm: $permissions,
      message: $this->message,
      parent_id: $channel->parent_id,
      selection: 'all',
      member: $member
    )) return;

    $context = new Context(
      message: $this->message,
      discord: $this->discord,
      channel: $channel,
      member: $member,
      redis: $this->redis,
      lng: clone $this->lng,
      model: $model,
      settings: $settings,
      permissions: $permissions,
      automod: $rulesAutomod
    );

    [$mainFilters, $fallbackFilters] = $this->prepareFilters($rulesAutomod, $context);

    $this->runFilters($mainFilters, $context, fn() => $this->runFilters($fallbackFilters, $context));
  }

  private function getChannel(): ?Channel
  {
    return Cache::request(
      redis: $this->redis,
      fn: fn() => $this->discord->rest->channel->get($this->message->channel_id),
      params: [
        'channel_id' => $this->message->channel_id,
        'key' => 'automod.getChannel'
      ]
    );
  }

  // Message update dosnt $message->guild_id
  private function getMember(Channel $channel): ?GuildMember
  {
    return Cache::request(
      redis: $this->redis,
      fn: fn() => $this->discord->rest->guild->getMember(
        guildId: $channel->guild_id,
        memberId: $this->message->author->id
      ),
      params: [
        'guild_id' => $channel->guild_id,
        'member_id' => $this->message->author->id,
        'key' => 'automod.getMember'
      ]
    );
  }

  private function prepareFilters(array $rules, Context $context): array
  {
    $main = [];
    $fallback = [];

    foreach ($rules as $rule) {
      $type = $rule['type'] ?? null;
      if (!isset(self::FILTER_MAP[$type])) continue;

      $rule['options'] = !empty($rule['options']) ? json_decode($rule['options'], true) : null;

      $filter = new (self::FILTER_MAP[$type])($context, $rule);

      $main[] = static fn() => $filter->process();

      if (method_exists($filter, 'filters')) {
        $extra = $filter->filters();
        $main = [...$main, ...($extra['main'] ?? [])];
        $fallback = [...$fallback, ...($extra['fallback'] ?? [])];
      }
    }

    return [$main, $fallback];
  }

  private function runFilters(array $filters, Context $context, ?callable $onFail = null): void
  {
    if (empty($filters)) {
      if ($onFail) $onFail();
      return;
    }

    $promises = array_map(static fn($fn) => $fn(), $filters);

    any($promises)
      ->then(static fn($result) => (new ResultHandler($context))->handle($result))
      ->catch(static function (Throwable $e) use ($onFail) {
        // echo "Automod batch failed: {$e->getMessage()}" . PHP_EOL;
        if ($onFail) $onFail();
      });
  }
}
