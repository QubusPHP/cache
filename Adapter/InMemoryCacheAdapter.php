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

namespace Qubus\Cache\Adapter;

use DateInterval;
use Qubus\Cache\DateIntervalConverter;
use Qubus\Cache\TypeException;
use Qubus\Support\DateTime\QubusDateTimeImmutable;

use function array_keys;
use function is_int;
use function is_string;
use function preg_match;
use function Qubus\Support\Helpers\is_null__;
use function serialize;
use function time;
use function unserialize;

class InMemoryCacheAdapter extends Multiple implements CacheAdapter
{
    private array $cache = [];

    /**
     * {@inheritDoc}
     *
     * @see \Qubus\Cache\Adapter\CacheAdapter::get()
     */
    public function get(string $key)
    {
        if (! is_string($key)) {
            throw new TypeException('$key must be a string.');
        }

        // expired data should be deleted first.
        if (! $this->has($key)) {
            return null;
        }

        $cache = unserialize($this->cache[$key]);

        return null === $cache['value'] ? null : $cache['value'];
    }

    /**
     * {@inheritDoc}
     *
     * @see \Qubus\Cache\Adapter\CacheAdapter::set()
     */
    public function set(string $key, mixed $value, ?int $ttl): bool
    {
        $expire = $this->convertTtl($ttl);

        $cache = [
            'key'   => $key,
            'ttl'   => $expire,
            'value' => $value,
        ];

        $this->cache[$key] = serialize($cache);

        return true;
    }

    /**
     * {@inheritDoc}
     *
     * @see \Qubus\Cache\Adapter\CacheAdapter::delete()
     */
    public function delete(string $key): bool
    {
        unset($this->cache[$key]);
        $this->cache[$key] = null;

        return true;
    }

    /**
     * {@inheritDoc}
     *
     * @see \Qubus\Cache\Adapter\CacheAdapter::has()
     */
    public function has(string $key): bool
    {
        if (! is_string($key)) {
            throw new TypeException('$key must be a string');
        }

        if (! isset($this->cache[$key])) {
            return false;
        }

        $data = unserialize($this->cache[$key]);

        $expire = match (true) {
            $data['ttl'] instanceof QubusDateTimeImmutable => $data['ttl']->getTimestamp(),
            is_int($data['ttl']) => $data['ttl'],
            is_null__($data['ttl']) => time() + 315360000 //ten years
        };

        if ($expire === 0 || $expire < time()) {
            $this->delete($key);
            return false;
        }

        return true;
    }

    /**
     * {@inheritDoc}
     *
     * @see \Qubus\Cache\Adapter\CacheAdapter::purge()
     */
    public function purge(?string $pattern): void
    {
        if (empty($this->cache)) {
            return;
        }

        if (is_null__($pattern)) {
            $this->cache = [];

            return;
        }

        $keys = [];
        foreach (array_keys($this->cache) as $key) {
            if (1 === preg_match("#{$pattern}#", $key)) {
                $keys[] = $key;
            }
        }

        $this->deleteMultiple($keys);
    }

    private function convertTtl(?int $ttl): int|QubusDateTimeImmutable
    {
        if ($ttl instanceof DateInterval) {
            $ttl = DateIntervalConverter::convert($ttl);
        }

        return match (true) {
            $ttl instanceof DateInterval => (new QubusDateTimeImmutable())->add($ttl),
            is_int($ttl) => new QubusDateTimeImmutable("now +$ttl seconds"),
            is_null__($ttl) => time() + 315360000 //ten years
        };
    }
}
