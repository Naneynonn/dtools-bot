<?php

namespace Naneynonn;

use Discord\Discord;
use Discord\Parts\User\Activity;

use Naneynonn\Language;

class Init
{
  private Discord $discord;
  private $activity;

  private string $load_time;

  public function __construct(Discord $discord, string $load_time)
  {
    $this->discord = $discord;
    $this->load_time = $load_time;

    $this->setActivity();
  }

  private function getMemoryUsageFriendly(): string
  {
    $size = memory_get_usage(true);
    $unit = ['B', 'KB', 'MB', 'GB', 'TB', 'PB'];

    return @round($size / pow(1024, ($i = floor(log($size, 1024)))), 2) . ' ' . $unit[$i];
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
    
    Started in {$this->load_time}
    
    ------";;
  }

  private function setActivity(): void
  {
    $lng = new Language();

    $this->activity = $this->discord->factory(Activity::class, [
      'name' => $lng->get_global(key: 'activity'),
      'type' => Activity::TYPE_WATCHING
    ]);
    $this->discord->updatePresence($this->activity);
  }

  public function getActivity()
  {
    return $this->activity;
  }
}
