<?php

namespace Naneynonn;

use Discord\Discord;
use Discord\Parts\User\Activity;

use Naneynonn\Language;
use ByteUnits\Metric;

class Init
{
  private Discord $discord;
  private $activity;

  private string $load_time;
  private int $shard;

  public function __construct(Discord $discord, string $load_time, int $shard)
  {
    $this->discord = $discord;
    $this->load_time = $load_time;
    $this->shard = $shard;

    $this->setActivity();
  }

  private function getMemoryUsageFriendly(): string
  {
    return Metric::bytes(memory_get_usage())->format();
  }

  private function getChannelCount(): int
  {
    $channelCount = $this->discord->private_channels->count();

    /* @var \Discord\Parts\Guild\Guild */
    foreach ($this->discord->guilds as $guild) {
      $channelCount += $guild->channels->count();
    }

    return $channelCount;
  }

  public function getLoadInfo(): string
  {
    return "    ------

    Logged in as 
    {$this->discord->user->username}
    {$this->discord->user->id}
    
    ------

    Guilds: {$this->discord->guilds->count()}
    All channels: {$this->getChannelCount()}
    Users: {$this->discord->users->count()}
    Memory use: {$this->getMemoryUsageFriendly()}
    
    ------
    
    Shard: {$this->shard}
    Started in {$this->load_time}
    
    ------";;
  }

  private function setActivity(): void
  {
    $lng = new Language();

    $this->activity = $this->discord->factory(Activity::class, [
      'name' => $lng->get(key: 'activity'),
      'type' => Activity::TYPE_WATCHING
    ]);
    $this->discord->updatePresence($this->activity);
  }

  public function getActivity()
  {
    return $this->activity;
  }
}
