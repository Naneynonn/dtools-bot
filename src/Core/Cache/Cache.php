<?php

declare(strict_types=1);

namespace Naneynonn\Core\Cache;

use React\Promise\PromiseInterface;
use Clue\React\Redis\LazyClient as RedisClient;

use Closure;

use function React\Async\await;

final class Cache
{
  private const string NAME = 'cache';

  public static function request(RedisClient $redis, Closure $fn, array $params, int $ttl = 3600): mixed
  {
    $key = self::generateKey($params);

    $result = await($redis->get($key));

    if (!empty($result)) {
      // Подавляем предупреждения от unserialize() с помощью @
      $data = @unserialize($result);

      if ($data === false && $result != 'b:0;') {
        // Возникла ошибка при десериализации
        echo 'cache: Ошибка при десериализации данных для ключа ' . $key . PHP_EOL;
        echo 'Данные: ' . var_export($result, true) . PHP_EOL;

        // Удаляем проблемный ключ из Redis
        $redis->del($key);
      } else {
        return $data;
      }
    }

    // Данные отсутствуют в кэше, выполняем функцию и кэшируем результат
    $result = await($fn());
    if (!empty($result)) {
      $redis->set($key, serialize($result), 'EX', $ttl);
    }

    return $result;
  }

  public static function del(RedisClient $redis, array $params): PromiseInterface
  {
    $key = self::generateKey(params: $params);
    return $redis->del($key);
  }

  private static function generateKey(array $params): string
  {
    return self::NAME . ':' . md5(json_encode($params));
  }
}
