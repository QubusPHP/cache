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
use League\Flysystem\FilesystemOperator;
use League\Flysystem\UnableToDeleteFile;
use League\Flysystem\UnableToReadFile;
use League\Flysystem\UnableToWriteFile;
use Qubus\Cache\Adapter\Multiple;
use Qubus\Cache\DateIntervalConverter;
use Qubus\Exception\Data\TypeException;
use Qubus\Support\DateTime\QubusDateTimeImmutable;

use function is_int;
use function is_string;
use function preg_match;
use function Qubus\Support\Helpers\is_false__;
use function Qubus\Support\Helpers\is_null__;
use function serialize;
use function time;
use function unserialize;

class FileSystemCacheAdapter extends Multiple implements CacheAdapter
{
    protected FilesystemOperator $operator;

    public function __construct(FilesystemOperator $operator)
    {
        $this->operator = $operator;
    }

    /**
     * {@inheritdoc}
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

        try {
            $cache = unserialize($this->operator->read($key));

            return $cache['value'];
        } catch (UnableToReadFile $ex) {
            return null;
        }

        return null;
    }

    /**
     * {@inheritdoc}
     */
    public function set(string $key, mixed $value, ?int $ttl): bool
    {
        if (! is_string($key)) {
            throw new TypeException('$key must be a string.');
        }

        $expire = $this->convertTtl($ttl);

        $cache = [
            'key'   => $key,
            'ttl'   => $expire,
            'value' => $value,
        ];

        try {
            $this->operator->write($key, serialize($cache));
        } catch (UnableToWriteFile $ex) {
            return false;
        }

        return true;
    }

    /**
     * {@inheritdoc}
     */
    public function delete(string $key): bool
    {
        if (! is_string($key)) {
            throw new TypeException('$key must be a string');
        }

        // Leaving this here in case it is needed based on some unforseen circumstance.
        /*if (! $this->has($key)) {
            return true;
        }*/

        try {
            $this->operator->delete($key);

            return true;
        } catch (UnableToDeleteFile $ex) {
            return false;
        }

        return false;
    }

    /**
     * {@inheritdoc}
     */
    public function purge(?string $pattern): void
    {
        $files = $this->operator->listContents('.');
        foreach ($files as $file) {
            try {
                if ('dir' === $file['type']) {
                    return;
                } else {
                    if (1 === preg_match("#{$pattern}#", $file['path'])) {
                        $this->operator->delete($file['path']);
                    }
                }
            } catch (UnableToDeleteFile $e) {
                return;
            }
        }
    }

    /**
     * {@inheritdoc}
     */
    public function has(string $key): bool
    {
        if (! is_string($key)) {
            throw new TypeException('$key must be a string');
        }

        if (is_false__($this->operator->fileExists($key))) {
            return false;
        }

        $data = unserialize($this->operator->read($key));

        $expire = match (true) {
            $data['ttl'] instanceof QubusDateTimeImmutable => $data['ttl']->getTimestamp(),
            is_int($data['ttl']) => $data['ttl'],
            is_null__($data['ttl']) => time() + 315360000 //ten years
        };

        if ($expire === 0 || $expire < time()) {
            $this->operator->delete($key);
            return false;
        }

        return true;
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
