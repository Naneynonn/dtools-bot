<?php

declare(strict_types=1);

namespace Naneynonn\Core\Cache;

use React\Promise\PromiseInterface;
use Clue\React\Redis\RedisClient;

use Closure;
use Throwable;

use function React\Async\await;

final class Cache
{
  private const string NAME = 'cache:bot:dtools';

  public static function request(RedisClient $redis, Closure $fn, array $params, int $ttl = 3600): mixed
  {
    $key = self::generateKey($params);

    try {
      $result = await($redis->get($key));
    } catch (Throwable $e) {
      error_log('[Redis] Ошибка запроса: ' . $e->getMessage());
      return null;
    }

    if (!empty($result)) {
      try {
        $data = unserialize($result);
        if ($data === false && $result !== 'b:0;') {
          throw new \RuntimeException("Ошибка десериализации для ключа {$key}");
        }
        return $data;
      } catch (Throwable $e) {
        error_log('[Cache] Ошибка десериализации: ' . $e->getMessage());
        error_log('Данные: ' . var_export($result, true));
        $redis->del($key);
        return null;
      }
    }

    $result = await($fn());
    if (!is_null($result)) {
      $redis->set($key, serialize($result), 'EX', $ttl);
    }

    return $result;
  }

  public static function del(RedisClient $redis, array $params): PromiseInterface
  {
    $key = self::generateKey($params);
    return $redis->del($key);
  }

  private static function generateKey(array $params): string
  {
    ksort($params); // Гарантируем одинаковый порядок ключей
    return self::NAME . ':' . hash('sha256', json_encode($params, JSON_UNESCAPED_UNICODE));
  }
}
