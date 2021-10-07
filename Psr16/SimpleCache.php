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

namespace Qubus\Cache\Psr16;

use DateInterval;
use Psr\SimpleCache\CacheInterface;
use Qubus\Cache\Adapter\CacheAdapter;
use Qubus\Cache\DateIntervalConverter;
use Qubus\Cache\Traits\ValidatableKey;

use function array_combine;
use function array_keys;
use function array_map;
use function is_int;

final class SimpleCache implements CacheInterface
{
    use ValidatableKey;

    protected CacheAdapter $adapter;

    /** @var int|null|DateInterval $ttl */
    protected int|null|DateInterval $ttl;

    protected ?string $namespace;

    /** @var const CACHE_FLAG */
    public const CACHE_FLAG = "@psr16_";

    public function __construct(
        CacheAdapter $adapter,
        int|null|DateInterval $ttl = null,
        ?string $namespace = 'default'
    ) {
        $this->adapter = $adapter;
        $this->ttl = $ttl;
        $this->namespace = $namespace;
    }

    /**
     * {@inheritdoc}
     */
    public function get($key, $default = null)
    {
        return (null !== $value = $this->adapter->get($this->validateKey($key))) ? $value : $default;
    }

    /**
     * {@inheritdoc}
     */
    public function set($key, $value, $ttl = null)
    {
        return $this->adapter->set($this->validateKey($key), $value, $this->getTtl($ttl));
    }

    /**
     * {@inheritdoc}
     */
    public function delete($key)
    {
        return $this->adapter->delete($this->validateKey($key));
    }

    /**
     * {@inheritdoc}
     */
    public function clear()
    {
        $this->adapter->purge(self::CACHE_FLAG . $this->namespace);

        return true;
    }

    /**
     * {@inheritdoc}
     */
    public function getMultiple($keys, $default = null)
    {
        return array_combine($keys, array_map(function ($value) use ($default) {
            return $value ?? $default;
        }, $this->adapter->getMultiple(array_map([$this, 'validateKey'], $keys))));
    }

    /**
     * {@inheritdoc}
     */
    public function setMultiple($values, $ttl = null)
    {
        return null === $this->adapter->setMultiple(
            array_combine(
                array_map([$this, 'validateKey'], array_keys($values)),
                array_map(function ($value) use ($ttl) {
                    return [
                        'value' => $value,
                        'ttl'   => $this->getTtl($ttl),
                    ];
                }, $values)
            )
        );
    }

    /**
     * {@inheritdoc}
     */
    public function deleteMultiple($keys)
    {
        return null === $this->adapter->deleteMultiple(array_map([$this, 'validateKey'], $keys));
    }

    /**
     * {@inheritdoc}
     */
    public function has($key)
    {
        return $this->adapter->has($this->validateKey($key));
    }

    protected function getTtl(int|null|DateInterval $ttl): ?int
    {
        if (is_int($ttl)) {
            return $ttl === 1 ? $this->ttl : $ttl;
        }

        if ($ttl instanceof DateInterval) {
            return DateIntervalConverter::convert($ttl);
        }

        return null;
    }
}
