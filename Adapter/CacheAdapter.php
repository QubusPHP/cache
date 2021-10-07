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

interface CacheAdapter
{
    /**
     * Get a value from the cache store.
     *
     * @param string $key Cache key.
     * @return mixed Cache value corresponding to the key or null if no value was found.
     */
    public function get(string $key);

    /**
     * Get multiple values from the cache store.
     *
     * @param array $keys Array of cache keys.
     * @return array[string|null Sequential array for each cache value corresponding to a key.
     *                            Null will be assigned if value was not found.
     */
    public function getMultiple(array $keys): ?iterable;

    /**
     * Sets a value into the cache store.
     *
     * @param string $key Cache key.
     * @param mixed $value Value to cache.
     * @param int|null $ttl Expiration time in seconds. Null means indefinite storage time.
     * @return bool True if the value has been stored successfully. False otherwise.
     */
    public function set(string $key, mixed $value, ?int $ttl): bool;

    /**
     * Sets multiple values into the cache store.
     *
     * @param array $values Associative array indexed by the cache key.
     *                      Cache value can be accessed through "value" key and ttl through "ttl" key.
     * @return array|null Returns null if all values have been stored successfully or an array representing all
     *                    cache keys that cannot be stored.
     */
    public function setMultiple(array $values): ?array;

    /**
     * Deletes a value from the cache store.
     *
     * @param string $key Cache key.
     * @return bool True if the cached value has been successfully deleted. False otherwise.
     */
    public function delete(string $key): bool;

    /**
     * Deletes multiple values from the cache store.
     *
     * @param array $keys An array of keys to delete
     * @return array|null A list of all keys that cannot be deleted or null if all keys has been deleted.
     */
    public function deleteMultiple(array $keys): ?array;

    /**
     * Checks if a value has been cached.
     *
     * @param string $key Cache key.
     * @return bool True if the cache key corresponds to a value. False otherwise.
     */
    public function has(string $key): bool;

    /**
     * Purge the store of all cache values.
     *
     * @param string|null $pattern Regex pattern for targeting only certain keys or null to purge everything.
     */
    public function purge(?string $pattern): void;
}
