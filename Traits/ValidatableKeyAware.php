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

trait ValidatableKeyAware
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
        if ('' === $key) {
            throw new TypeException('Cache key cannot be empty.');
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
     * Affixes a prefix to the namespace.
     *
     * @param string $key
     * @return string
     */
    protected function prefix(string $key): string
    {
        $key = sha1($key);

        return (null === $this->namespace) ? self::CACHE_FLAG . $key : self::CACHE_FLAG . "{$this->namespace}_{$key}";
    }

    /**
     * Keep namespaces safe for filesystem paths, regular expressions, and glob patterns.
     */
    protected function normalizeNamespace(?string $namespace = null): ?string
    {
        if (null === $namespace || 1 === preg_match('/^[A-Za-z0-9_-]{1,64}$/D', $namespace)) {
            return $namespace;
        }

        return sha1($namespace);
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
