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

namespace Qubus\Cache\Adapter;

use League\Flysystem\FilesystemException;
use League\Flysystem\FilesystemOperator;
use Qubus\Support\DateTime\QubusDateTimeImmutable;

use function array_key_exists;
use function is_array;
use function is_int;
use function preg_match;
use function serialize;
use function str_starts_with;
use function time;
use function unserialize;

class FileSystemCacheAdapter extends Multiple implements CacheAdapter
{
    public function __construct(protected FilesystemOperator $operator)
    {
    }

    /**
     * {@inheritdoc}
     */
    public function get(string $key): mixed
    {
        $cache = $this->readValidEntry($key);

        return $cache['value'] ?? null;
    }

    /**
     * {@inheritdoc}
     */
    public function set(string $key, mixed $value, ?int $ttl = null): bool
    {
        if (null !== $ttl && $ttl <= 0) {
            return $this->delete($key);
        }

        $cache = [
            'key'   => $key,
            'ttl'   => null === $ttl ? null : time() + $ttl,
            'value' => $value,
        ];

        try {
            $this->operator->write($key, serialize($cache));
        } catch (FilesystemException) {
            return false;
        }

        return true;
    }

    /**
     * {@inheritdoc}
     */
    public function delete(string $key): bool
    {
        try {
            if (! $this->operator->fileExists($key)) {
                return true;
            }

            $this->operator->delete($key);

            return true;
        } catch (FilesystemException) {
            return false;
        }
    }

    /**
     * {@inheritdoc}
     */
    public function purge(?string $pattern = null): void
    {
        try {
            $files = $this->operator->listContents('.', true);
            foreach ($files as $file) {
                if ('dir' === $file['type']) {
                    continue;
                }

                if (null === $pattern || 1 === preg_match("#{$pattern}#", $file['path'])) {
                    try {
                        $this->operator->delete($file['path']);
                    } catch (FilesystemException) {
                        // Continue purging other entries if one file cannot be removed.
                    }
                }
            }
        } catch (FilesystemException) {
            return;
        }
    }

    /**
     * {@inheritdoc}
     */
    public function has(string $key): bool
    {
        return null !== $this->readValidEntry($key);
    }

    /**
     * Delete expired or malformed cache entries while preserving unrelated files.
     */
    public function prune(): bool
    {
        $success = true;

        try {
            foreach ($this->operator->listContents('.', true) as $file) {
                if ('dir' === $file['type']) {
                    continue;
                }

                $path = $file['path'];
                if (! str_starts_with($path, '@psr6_') && ! str_starts_with($path, '@psr16_')) {
                    continue;
                }

                if (null === $this->readValidEntry($path) && $this->operator->fileExists($path)) {
                    $success = false;
                }
            }
        } catch (FilesystemException) {
            return false;
        }

        return $success;
    }

    /**
     * Read an entry and remove it if it is expired or malformed.
     *
     * @return array{key: string, ttl: int|QubusDateTimeImmutable|null, value: mixed}|null
     */
    private function readValidEntry(string $key): ?array
    {
        try {
            if (! $this->operator->fileExists($key)) {
                return null;
            }

            $data = @unserialize($this->operator->read($key), ['allowed_classes' => true]);
        } catch (FilesystemException) {
            return null;
        }

        if (
            ! is_array($data)
            || ! array_key_exists('key', $data)
            || ! array_key_exists('ttl', $data)
            || ! array_key_exists('value', $data)
            || $data['key'] !== $key
        ) {
            $this->delete($key);
            return null;
        }

        $expiresAt = match (true) {
            $data['ttl'] instanceof QubusDateTimeImmutable => $data['ttl']->getTimestamp(),
            is_int($data['ttl']) => $data['ttl'],
            null === $data['ttl'] => null,
            default => 0,
        };

        if (null !== $expiresAt && $expiresAt <= time()) {
            $this->delete($key);
            return null;
        }

        return $data;
    }
}
