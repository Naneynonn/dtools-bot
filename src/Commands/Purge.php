<?php

declare(strict_types=1);

namespace Naneynonn\Commands;

use Ragnarok\Fenrir\Interaction\CommandInteraction;
use Ragnarok\Fenrir\Rest\Helpers\Channel\GetMessagesBuilder;
use Ragnarok\Fenrir\Gateway\Events\InteractionCreate;

use Naneynonn\Attr\Command;
use Naneynonn\Attr\SubCommand;
use Naneynonn\Core\App\CommandHelper;
use Naneynonn\Embeds;

use Carbon\Carbon;
use Closure;

use function React\Async\async;
use function React\Async\await;

#[Command(name: 'purge')]
class Purge extends CommandHelper
{
  private const int LIMIT = 100;

  #[SubCommand(name: 'any')]
  public function any(CommandInteraction $command): void
  {
    $interaction = $command->interaction;
    $this->setLocale(locale: $interaction->locale);

    if (!$this->isServerAdmin(interaction: $interaction)) {
      $this->sendMessage(command: $command, embed: Embeds::noPerm(lng: $this->lng));
      return;
    }

    $limit = $command->getOption('any')?->options[0]?->value;
    if (is_null($limit)) {
      $this->sendMessage(command: $command, embed: Embeds::danger(text: $this->lng->trans('embed.empty.field')));
      return;
    }

    if ($limit > self::LIMIT) $limit = self::LIMIT;

    async(function () use ($interaction, $limit, $command) {
      try {
        $messages = await($this->discord->rest->channel->getMessages(
          channelId: $interaction->channel->id,
          getMessagesBuilder: GetMessagesBuilder::new()->setLimit($limit)
        ));

        $ids = $this->collectIds(messages: $messages);

        if (empty($ids)) {
          $this->sendMessage(command: $command, embed: Embeds::danger(text: $this->lng->trans('embed.purge.old')));
          return;
        }

        $this->bulkDeleteMessages(interaction: $interaction, ids: $ids);

        $count_ids = count($ids);
        $this->sendMessage(command: $command, embed: Embeds::success(text: $this->lng->trans('embed.purge.del', [
          '%count%' => $count_ids,
          '%msg%' => $this->lng->trans('count.messages', ['count' => $count_ids])
        ])));
      } catch (\Throwable $th) {
        echo 'purge.any' . $th->getMessage() . PHP_EOL;
      }
    })();
  }

  #[SubCommand(name: 'user')]
  public function user(CommandInteraction $command): void
  {
    $interaction = $command->interaction;
    $this->setLocale(locale: $interaction->locale);

    if (!$this->isServerAdmin(interaction: $interaction)) {
      $this->sendMessage(command: $command, embed: Embeds::noPerm(lng: $this->lng));
      return;
    }

    $limit = $command->getOption('user')?->options[0]?->value;
    if (is_null($limit)) {
      $this->sendMessage(command: $command, embed: Embeds::danger(text: $this->lng->trans('embed.empty.field')));
      return;
    }

    if ($limit > self::LIMIT) $limit = self::LIMIT;

    $user_id = $command->getOption('user')?->options[1]?->value;
    if (is_null($user_id)) {
      $this->sendMessage(command: $command, embed: Embeds::danger(text: $this->lng->trans('embed.empty.field')));
      return;
    }

    async(function () use ($interaction, $limit, $command, $user_id) {
      try {
        $messages = await($this->discord->rest->channel->getMessages(
          channelId: $interaction->channel->id,
          getMessagesBuilder: GetMessagesBuilder::new()->setLimit($limit)
        ));

        $ids = $this->collectIds(messages: $messages, condition: fn ($message) => $message->author->id == $user_id);

        if (empty($ids)) {
          $this->sendMessage(command: $command, embed: Embeds::danger(text: $this->lng->trans('embed.purge.old')));
          return;
        }

        $this->bulkDeleteMessages(interaction: $interaction, ids: $ids);

        $count_ids = count($ids);
        $this->sendMessage(command: $command, embed: Embeds::success(text: $this->lng->trans('embed.purge.del', [
          '%count%' => $count_ids,
          '%msg%' => $this->lng->trans('count.messages', ['count' => $count_ids])
        ])));
      } catch (\Throwable $th) {
        echo 'purge.user' . $th->getMessage() . PHP_EOL;
      }
    })();
  }

  #[SubCommand(name: 'bots')]
  public function bots(CommandInteraction $command): void
  {
    $interaction = $command->interaction;
    $this->setLocale(locale: $interaction->locale);

    if (!$this->isServerAdmin(interaction: $interaction)) {
      $this->sendMessage(command: $command, embed: Embeds::noPerm(lng: $this->lng));
      return;
    }

    $limit = $command->getOption('bots')?->options[0]?->value;
    if (is_null($limit)) {
      $this->sendMessage(command: $command, embed: Embeds::danger(text: $this->lng->trans('embed.empty.field')));
      return;
    }

    if ($limit > self::LIMIT) $limit = self::LIMIT;

    async(function () use ($interaction, $limit, $command) {
      try {
        $messages = await($this->discord->rest->channel->getMessages(
          channelId: $interaction->channel->id,
          getMessagesBuilder: GetMessagesBuilder::new()->setLimit($limit)
        ));

        $ids = $this->collectIds(messages: $messages, condition: fn ($message) => $message->author->bot);

        if (empty($ids)) {
          $this->sendMessage(command: $command, embed: Embeds::danger(text: $this->lng->trans('embed.purge.old')));
          return;
        }

        $this->bulkDeleteMessages(interaction: $interaction, ids: $ids);

        $count_ids = count($ids);
        $this->sendMessage(command: $command, embed: Embeds::success(text: $this->lng->trans('embed.purge.del', [
          '%count%' => $count_ids,
          '%msg%' => $this->lng->trans('count.messages', ['count' => $count_ids])
        ])));
      } catch (\Throwable $th) {
        echo 'purge.bots' . $th->getMessage() . PHP_EOL;
      }
    })();
  }

  private function collectIds(array $messages, ?Closure $condition = null): array
  {
    $ids = [];
    $twoWeeksAgo = Carbon::now()->subDays(14);

    foreach ($messages as $message) {
      if ($message->timestamp->lessThanOrEqualTo($twoWeeksAgo)) continue;
      if (!is_null($condition) && !$condition($message)) continue;

      $ids[] = $message->id;
    }

    return $ids;
  }

  private function bulkDeleteMessages(InteractionCreate $interaction, array $ids): void
  {
    if (count($ids) < 2) {
      $this->discord->rest->channel->deleteMessage(
        channelId: $interaction->channel->id,
        messageId: $ids[0],
        reason: $this->lng->trans('audit.message.bulk')
      );
    } else {
      $this->discord->rest->channel->bulkDeleteMessages(
        channelId: $interaction->channel->id,
        messageIds: $ids,
        reason: $this->lng->trans('audit.message.bulk')
      );
    }
  }
}
