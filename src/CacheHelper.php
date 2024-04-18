<?php

namespace Naneynonn;

use React\Cache\ArrayCache;
use React\Promise\PromiseInterface;

use function React\Async\async;
use function React\Async\await;

class CacheHelper
{
  private const NAME = 'FNRR';
  private $cache;

  public function __construct()
  {
    $this->cache = new ArrayCache();
  }

  public function cachedRequest(callable $fn, array $params, int $ttl = 3600): PromiseInterface
  {
    $key = $this->generateCacheKey($params);

    $cachedResult = $this->cache->get($key);

    if (!is_null(await($cachedResult))) {
      return $cachedResult;
    }

    $result = $fn();
    $this->cache->set($key, $result, $ttl);

    return $result;
  }

  private function generateCacheKey(array $params): string
  {
    return self::NAME . ':' . md5(json_encode($params));
  }

  public function delete(array $params): void
  {
    $key = $this->generateCacheKey(params: $params);
    $this->cache->delete($key);
  }
}
