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

namespace Qubus\Cache\Psr6;

use DateInterval;
use DateTime;
use DateTimeInterface;
use Psr\Cache\CacheItemInterface;
use Qubus\Support\DateTime\QubusDateTimeImmutable;

use function is_int;
use function Qubus\Support\Helpers\is_null__;

final class Item implements CacheItemInterface
{
    /** @var DateTimeInterface|DateInterval|int|null $expiration */
    protected DateTimeInterface|DateInterval|int|null $expiration;

    /**
     * Default value to use as default expiration date.
     */
    public const EXPIRATION = 'now +100 years';

    /**
     * @param string $key Cache key.
     * @param mixed $value Cache value.
     */
    public function __construct(
        private string $key,
        private mixed $value = null,
        ?DateTimeInterface $ttl = null,
        private bool $isHit = false
    ) {
        $this->expiresAt($ttl);
    }

    /**
     * {@inheritdoc}
     */
    public function getKey(): string
    {
        return $this->key;
    }

    /**
     * {@inheritdoc}
     */
    public function get(): mixed
    {
        return $this->value;
    }

    /**
     * {@inheritdoc}
     */
    public function isHit(): bool
    {
        return $this->isHit && ! $this->isExpired() && $this->value !== false;
    }

    /**
     * {@inheritdoc}
     */
    public function set(mixed $value): static
    {
        $this->value = $value;

        return $this;
    }

    /**
     * Set whether it's a cache hit or not.
     *
     * @param bool $value False or true.
     * @return static
     */
    public function setHit(bool $value): static
    {
        $this->isHit = (bool) $value;

        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function expiresAt(?DateTimeInterface $expiration): static
    {
        $this->expiration = match (true) {
            $expiration instanceof DateTimeInterface => $expiration,
            is_null__($expiration) => new QubusDateTimeImmutable(self::EXPIRATION)
        };

        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function expiresAfter(int|DateInterval|null $time): static
    {
        $this->expiration = match (true) {
            $time instanceof DateInterval => (new QubusDateTimeImmutable())->add($time),
            is_int($time) => new QubusDateTimeImmutable("now +$time seconds"),
            is_null__($time) => new QubusDateTimeImmutable(self::EXPIRATION)
        };

        return $this;
    }

    /**
     * Returns a DateInterval object.
     */
    public function getExpiresAt(): DateTime|DateInterval|QubusDateTimeImmutable
    {
        return $this->expiration;
    }

    /**
     * Returns the number of seconds a cache should expire.
     */
    public function getExpiresInSeconds(): int
    {
        return $this->getExpiresAt()->getTimestamp() - (new QubusDateTimeImmutable('now'))->getTimestamp();
    }

    /**
     * Returns true if expired, false otherwise.
     */
    public function isExpired(): bool
    {
        return (new QubusDateTimeImmutable('now'))->getTimestamp() > $this->getExpiresAt()->getTimestamp();
    }
}
