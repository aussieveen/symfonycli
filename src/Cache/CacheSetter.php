<?php

namespace App\Cache;

use DateTime;
use Symfony\Contracts\Cache\ItemInterface;

class CacheSetter
{
    /**
     * @var string
     */
    private $cacheItem;

    /**
     * @var int
     */
    private $expirationTimestamp;

    public function __construct($cacheItem, int $expirationTimestamp)
    {
        $this->cacheItem = $cacheItem;
        $this->expirationTimestamp = $expirationTimestamp;
    }

    public function __invoke(ItemInterface $itemInterface)
    {
        $expiration = new DateTime();
        $expiration->setTimestamp($this->expirationTimestamp);
        $itemInterface->expiresAt($expiration);

        return $this->cacheItem;
    }
}