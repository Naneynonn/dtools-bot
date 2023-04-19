<?php

namespace Naneynonn\Filter;

use React\Promise\PromiseInterface;

use Discord\Parts\Channel\Message;

use Naneynonn\Language;
use Naneynonn\Model;
use Naneynonn\Config;

use function React\Promise\reject;
use function React\Promise\resolve;

class BadWords extends Config
{
  private const TYPE = 'badwords';

  private Message $message;
  private Language $lng;
  private Model $model;

  private array $settings;
  private array $perm;

  public function __construct(Message $message, Language $lng, array $settings, array $perm, Model $model)
  {
    $this->message = $message;

    $this->settings = $settings;
    $this->perm = $perm;
    $this->lng = $lng;
    $this->model = $model;
  }

  public function process(): PromiseInterface
  {
    if (!$this->settings['is_' . self::TYPE . '_status']) return reject('status off');

    // Load BadWords Exceptions
    $skip = $this->model->getBadWordsExeption(id: $this->message->guild->id);
    $skip = $this->skipWords(skip: $skip);

    $badword_check = $this->checkBadWords(message: $this->message->content, skip: $skip);
    if (!isset($badword_check['badwords'])) return reject('no badwords');

    $badword = $badword_check['badwords'];
    if (!$badword) return reject('ok words');

    // вынести getIgnoredPermissions в MessageProcessor
    if (getIgnoredPermissions(perm: $this->perm, message: $this->message, selection: self::TYPE)) return reject('ignored perm');

    return resolve([
      'module' => self::TYPE,
      'reason' => [
        'log' => $this->lng->get('embeds.foul-lang'),
        'timeout' => $this->lng->get('embeds.foul-lang')
      ]
    ]);
  }

  private function skipWords(array $skip): string
  {
    if (!$skip) $skip = '';
    else $skip = implode(', ', array_map(function ($entry) {
      return $entry['word'];
    }, $skip));

    return $skip;
  }

  private function checkBadWords(string $message, string $skip): ?array
  {
    $url = 'https://api.discord.band/v1/badwords';

    $body = [
      "message" => $message,
      "type" => 1,
      "skip" => $skip
    ];
    $response_json = json_encode($body, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "POST");
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json', 'Authorization: Bearer ' . self::API_TOKEN]);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 2);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 1);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $response_json);
    $response = curl_exec($ch);
    curl_close($ch);
    $results = json_decode($response, true);

    return $results;
  }
}
