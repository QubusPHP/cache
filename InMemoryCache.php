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
use Qubus\Cache\Adapter\InMemoryCacheAdapter;

final class InMemoryCache extends BaseCache
{
    public function __construct(int|null|DateInterval $ttl = null, ?string $namespace = null)
    {
        $this->adapter = new InMemoryCacheAdapter();
        parent::__construct($ttl, $namespace);
    }
}
