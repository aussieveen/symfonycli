<?php

namespace App\Cache;

use IM\Fabric\Package\Security\TokenGenerator\Cache\CacheInterface;
use League\OAuth2\Client\Token\AccessTokenInterface;
use Symfony\Contracts\Cache\CacheInterface as SymfonyCacheInterface;

class AccessTokenCache implements CacheInterface
{
    /**
     * @var SymfonyCacheInterface
     */
    private $cache;

    public function __construct(SymfonyCacheInterface $cache)
    {
        $this->cache = $cache;
    }

    public function getCache(string $key): ?AccessTokenInterface
    {
        return $this->cache->get($this->getCacheKey($key), new CacheSetter(null, 0));
    }

    public function setCache(string $key, AccessTokenInterface $accessToken): void
    {
        $this->cache->get($this->getCacheKey($key), new CacheSetter($accessToken, $accessToken->getExpires()));
    }

    private function getCacheKey(string $key): string
    {
        return 'IDENTITY-TOKEN' . "-$key";
    }
}