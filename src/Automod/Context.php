<?php

declare(strict_types=1);

namespace Naneynonn\Automod;

use Ragnarok\Fenrir\Discord;

use Ragnarok\Fenrir\Gateway\Events\MessageCreate;
use Ragnarok\Fenrir\Gateway\Events\MessageUpdate;

use Ragnarok\Fenrir\Parts\Channel;
use Ragnarok\Fenrir\Parts\GuildMember;

use Clue\React\Redis\RedisClient;

use Naneynonn\Language;
use Naneynonn\Model;

final class Context
{
  public function __construct(
    public readonly MessageCreate|MessageUpdate $message,
    public readonly Discord $discord,
    public readonly Channel $channel,
    public readonly ?GuildMember $member,

    public readonly RedisClient $redis,

    public readonly Language $lng,
    public readonly Model $model,

    public readonly array $settings,
    public readonly array $permissions,
    public readonly array $automod
  ) {}
}
