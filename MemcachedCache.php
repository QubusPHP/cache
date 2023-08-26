<?php

/**
 * Qubus\Cache
 *
 * @link       https://github.com/QubusPHP/cache
 * @copyright  2021
 * @author     Joshua Parker <joshua@joshuaparker.dev>
 * @license    https://opensource.org/licenses/mit-license.php MIT License
 */

declare(strict_types=1);

namespace Qubus\Cache;

use DateInterval;
use Memcached;
use Qubus\Cache\Adapter\MemcachedCacheAdapter;

final class MemcachedCache extends BaseCache
{
    public function __construct(Memcached $memcached, int|null|DateInterval $ttl = null, ?string $namespace = null)
    {
        $this->adapter = new MemcachedCacheAdapter($memcached);
        parent::__construct($ttl, $namespace);
    }
}
