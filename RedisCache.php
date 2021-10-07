<?php

/**
 * Qubus\Cache
 *
 * @link       https://github.com/QubusPHP/cache
 * @copyright  2021 Joshua Parker <josh@joshuaparker.blog>
 * @license    https://opensource.org/licenses/mit-license.php MIT License
 *
 * @since      1.0.0
 */

declare(strict_types=1);

namespace Qubus\Cache;

use DateInterval;
use Qubus\Cache\Adapter\RedisCacheAdapter;
use Redis;

final class RedisCache extends BaseCache
{
    public function __construct(Redis $redis, int|null|DateInterval $ttl = null, ?string $namespace = null)
    {
        $this->adapter = new RedisCacheAdapter($redis);
        parent::__construct($ttl, $namespace);
    }
}
