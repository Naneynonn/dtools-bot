<?php

function getDecodeImage(string $url): string
{
  $path = $url;
  $type = pathinfo($path, PATHINFO_EXTENSION);
  $data = file_get_contents($path);
  return 'data:image/' . $type . ';base64,' . base64_encode($data);
}

function checkBadWords(string $message): ?array
{
  $url = 'https://api.discord.band/v1/badwords';

  $body = [
    "message" => $message,
    "type" => 1
  ];
  $response_json = json_encode($body, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

  $ch = curl_init();
  curl_setopt($ch, CURLOPT_URL, $url);
  curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "POST");
  curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json', 'Authorization: Bearer ' . CONFIG['api']['token']]);
  curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);
  curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 2);
  curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 1);
  curl_setopt($ch, CURLOPT_POSTFIELDS, $response_json);
  $response = curl_exec($ch);
  curl_close($ch);
  $results = json_decode($response, true);

  return $results;
}

function isTimeTimeout(string $user_id, int $warnings = 5, int $time = 600): bool
{
  if ($warnings === 0) return false;

  $client = new Predis\Client();
  $key = "bot:badwords:{$user_id}";

  if ($client->exists(key: $key)) {
    $count = $client->incr(key: $key);
    if ($count >= $warnings) {
      $client->del(key: $key);
      return true;
    }
  } else {
    $client->setex(key: $key, seconds: $time, value: 1);
  }

  return false;
}

function getNormalEnd(int $num, string $for_1, string $for_2, string $for_5): string
{
  $num = abs($num) % 100; // берем число по модулю и сбрасываем сотни (делим на 100, а остаток присваиваем переменной $num)
  $num_x = $num % 10; // сбрасываем десятки и записываем в новую переменную

  if ($num > 10 && $num < 20) return $for_5; // если число принадлежит отрезку [11;19]
  if ($num_x > 1 && $num_x < 5) return $for_2; // иначе если число оканчивается на 2,3,4
  if ($num_x == 1) return $for_1; // иначе если оканчивается на 1

  return $for_5;
}

function getNormalEndByLang(int $num, string $name, array $lng): string
{
  $num = abs($num) % 100; // берем число по модулю и сбрасываем сотни (делим на 100, а остаток присваиваем переменной $num)
  $num_x = $num % 10; // сбрасываем десятки и записываем в новую переменную

  if ($num > 10 && $num < 20) return $lng['count'][$name][5]; // если число принадлежит отрезку [11;19] 
  if ($num_x > 1 && $num_x < 5) return $lng['count'][$name][2]; // иначе если число оканчивается на 2,3,4
  if ($num_x == 1) return $lng['count'][$name][1]; // иначе если оканчивается на 1

  return $lng['count'][$name][5];
}
