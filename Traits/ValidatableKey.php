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

namespace Qubus\Cache\Traits;

use Qubus\Cache\TypeException;
use Traversable;

use function gettype;
use function is_array;
use function is_string;
use function mb_strlen;
use function preg_match;
use function preg_quote;
use function sprintf;

trait ValidatableKey
{
    /**
     * Reserved key characters that should not be used in a cache key.
     */
    final public function reservedKeyCharacters(): string
    {
        return '{}()/\@:';
    }

    /**
     * Validates cache key.
     *
     * @param string $key
     * @return string
     * @throws TypeException
     */
    protected function validateKey(string $key): string
    {
        if (null === $key) {
            throw new TypeException('Cache key cannot be null.');
        }

        if (false === is_string($key)) {
            throw new TypeException(
                sprintf(
                    'Argument "%s" is invalid. Must enter a string, "%s" given',
                    $key,
                    gettype($key)
                )
            );
        }

        if (preg_match('#[' . preg_quote($this->reservedKeyCharacters()) . ']#', $key) > 0) {
            throw new TypeException(
                sprintf(
                    'The given cache key "%s" contains reserved characters: "%s".',
                    $key,
                    $this->reservedKeyCharacters()
                )
            );
        }

        $stringLength = mb_strlen($key);

        if ($stringLength <= 0 || $stringLength > 64) {
            throw new TypeException('Cache key characters must be greater than zero and less than equal to 64.');
        }

        return $this->prefix($key);
    }

    /**
     * Checks if key is hashed.
     *
     * @param string $key
     * @return bool
     */
    protected function isHashed(string $key): bool
    {
        return (bool) preg_match('/^[0-9a-f]{40}$/i', $key);
    }

    /**
     * Affixes a prefix to the
     *
     * @param string $key
     * @return string
     */
    protected function prefix(string $key): string
    {
        if (!$this->isHashed($key)) {
            $key = sha1($key);
        }

        return (null === $this->namespace) ? self::CACHE_FLAG . $key : self::CACHE_FLAG . "{$this->namespace}_{$key}";
    }

    /**
     * Validates an array of keys.
     *
     * @param array $keys
     * @throws TypeException
     */
    protected function validateKeys($keys): void
    {
        if (is_array($keys)) {
            return;
        }

        if ($keys instanceof Traversable) {
            return;
        }

        throw new TypeException('Invalid. Keys must be iterable.');
    }
}
