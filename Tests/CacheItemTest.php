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

namespace Qubus\Tests\Cache;

use DateInterval;
use DateTime;
use PHPUnit\Framework\Assert;
use PHPUnit\Framework\TestCase;
use Qubus\Cache\Psr6\Item;

use function time;

class CacheItemTest extends TestCase
{
    protected Item $item;

    public function setUp(): void
    {
        $this->item = new Item('poolItem', 'Has some data.', null, true);
        $this->item->expiresAfter((int) 20);
    }

    public function testGetsKey()
    {
        Assert::assertSame('poolItem', $this->item->getKey());
    }

    public function testGetValue()
    {
        Assert::assertSame('Has some data.', $this->item->get());
    }

    public function testGetExpire()
    {
        $time = time() + 3600;
        $expire = new DateTime();

        $this->item->expiresAt($expire->setTimestamp($time));
        $this->assertEquals($time, $this->item->getExpiresAt()->getTimestamp());
    }

    public function testIsHitIsTrue()
    {
        Assert::assertTrue($this->item->isHit());
    }

    public function testCacheItemSetToDifferentValueAfterInstantiation()
    {
        $expiration = new DateTime();
        $expiration->add(new DateInterval('PT100S'));

        $this->item->set('My new data.')
            ->expiresAt($expiration)
            ->expiresAfter(null);

        Assert::assertTrue($this->item->isHit());
        Assert::assertEquals('poolItem', $this->item->getKey());
        Assert::assertEquals('My new data.', $this->item->get());
    }

    public function testExpiresAtSetNull()
    {
        $this->item->expiresAt(null);
        $this->assertGreaterThanOrEqual(time() + 3500, $this->item->getExpiresAt()->getTimestamp());
        $this->assertLessThanOrEqual($this->item->getExpiresAt()->getTimestamp(), time() + 3600);
    }
}
