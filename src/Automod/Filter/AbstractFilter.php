<?php

declare(strict_types=1);

namespace Naneynonn\Automod\Filter;

use Ragnarok\Fenrir\Discord;

use Ragnarok\Fenrir\Gateway\Events\MessageCreate;
use Ragnarok\Fenrir\Gateway\Events\MessageUpdate;

use Ragnarok\Fenrir\Parts\GuildMember;
use Ragnarok\Fenrir\Parts\Channel;

use React\Promise\PromiseInterface;
use Clue\React\Redis\RedisClient;

use Naneynonn\Automod\Context;
use Naneynonn\Model;
use Naneynonn\Language;

use RuntimeException;

use function React\Promise\reject;

abstract class AbstractFilter implements FilterInterface
{
  protected readonly MessageCreate|MessageUpdate $message;
  protected readonly Discord $discord;
  protected readonly Channel $channel;
  protected readonly ?GuildMember $member;
  protected readonly RedisClient $redis;
  protected readonly Language $lng;
  protected readonly Model $model;
  protected readonly array $settings;
  protected readonly array $permissions;
  protected readonly array $rule;

  public function __construct(Context $context, array $rule)
  {
    $this->message = $context->message;
    $this->discord = $context->discord;
    $this->channel = $context->channel;
    $this->member = $context->member;
    $this->redis = $context->redis;
    $this->lng = $context->lng;
    $this->model = $context->model;
    $this->settings = $context->settings;
    $this->permissions = $context->permissions;
    $this->rule = $rule;
  }

  protected function sendReject(string $type, string $text): PromiseInterface
  {
    return reject(new RuntimeException(ucfirst($type) . ' | ' . $text));
  }
}
