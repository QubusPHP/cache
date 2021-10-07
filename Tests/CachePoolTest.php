<?php

/*
 * This file is part of php-cache organization. It has been ported into this library because
 * of the conflict in PHPUnit version requirements as well as the removal of unneeded tests.
 *
 * (c) 2015-2015 Aaron Scherer <aequasi@gmail.com>, Tobias Nyholm <tobias.nyholm@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

declare(strict_types=1);

namespace Qubus\Tests\Cache;

use DateTime;
use PHPUnit\Framework\Assert;
use PHPUnit\Framework\TestCase;
use Qubus\Cache\TypeException;
use stdClass;
use Traversable;

use function chr;
use function date;
use function gc_collect_cycles;
use function in_array;
use function is_array;
use function is_bool;
use function is_float;
use function is_int;
use function is_object;
use function is_string;
use function Qubus\Support\Helpers\is_null__;
use function sleep;
use function str_repeat;
use function time;

abstract class CachePoolTest extends TestCase
{
    /** @type array with functionName => reason. */
    protected $skippedTests = [];

    /** @type CacheItemPoolInterface */
    protected $cache;

    /**
     * @return CacheItemPoolInterface that is used in the tests
     */
    abstract public function createCachePool();

    /**
     * @before
     */
    public function setupService()
    {
        $this->cache = $this->createCachePool();
    }

    /**
     * @after
     */
    public function tearDownService()
    {
        if ($this->cache !== null) {
            $this->cache->clear();
        }
    }

    public function testBasicUsage()
    {
        if (isset($this->skippedTests[__FUNCTION__])) {
            $this->markTestSkipped($this->skippedTests[__FUNCTION__]);
        }

        $item = $this->cache->getItem('key');
        $item->set('4711');
        $this->cache->save($item);

        $item = $this->cache->getItem('key2');
        $item->set('4712');
        $this->cache->save($item);

        $fooItem = $this->cache->getItem('key');
        Assert::assertTrue($fooItem->isHit());
        Assert::assertEquals('4711', $fooItem->get());

        $barItem = $this->cache->getItem('key2');
        Assert::assertTrue($barItem->isHit());
        Assert::assertEquals('4712', $barItem->get());

        // Remove 'key' and make sure 'key2' is still there
        $this->cache->deleteItem('key');
        Assert::assertFalse($this->cache->getItem('key')->isHit());
        Assert::assertTrue($this->cache->getItem('key2')->isHit());

        // Remove everything
        $this->cache->clear();
        Assert::assertFalse($this->cache->getItem('key')->isHit());
        Assert::assertFalse($this->cache->getItem('key2')->isHit());
    }

    public function testBasicUsageWithLongKey()
    {
        if (isset($this->skippedTests[__FUNCTION__])) {
            $this->markTestSkipped($this->skippedTests[__FUNCTION__]);
        }

        $this->expectException(TypeException::class);

        $pool = $this->createCachePool();

        $key = str_repeat('a', 300);

        $item = $pool->getItem($key);
    }

    public function testItemModifiersReturnsStatic()
    {
        if (isset($this->skippedTests[__FUNCTION__])) {
            $this->markTestSkipped($this->skippedTests[__FUNCTION__]);
        }

        $item = $this->cache->getItem('key');
        Assert::assertSame($item, $item->set('4711'));
        Assert::assertSame($item, $item->expiresAfter(2));
        Assert::assertSame($item, $item->expiresAt(new DateTime('+2hours')));
    }

    public function testGetItem()
    {
        if (isset($this->skippedTests[__FUNCTION__])) {
            $this->markTestSkipped($this->skippedTests[__FUNCTION__]);
        }

        $item = $this->cache->getItem('key');
        $item->set('value');
        $this->cache->save($item);

        // get existing item
        $item = $this->cache->getItem('key');
        Assert::assertEquals('value', $item->get(), 'A stored item must be returned from cached.');
        Assert::assertEquals('key', $item->getKey(), 'Cache key can not change.');

        // get non-existent item
        $item = $this->cache->getItem('key2');
        Assert::assertFalse($item->isHit());
        Assert::assertNull($item->get(), "Item's value must be null when isHit is false.");
    }

    public function testGetItems()
    {
        if (isset($this->skippedTests[__FUNCTION__])) {
            $this->markTestSkipped($this->skippedTests[__FUNCTION__]);
        }

        $keys  = ['foo', 'bar', 'baz'];
        $items = $this->cache->getItems($keys);

        $count = 0;

        /** @type CacheItemInterface $item */
        foreach ($items as $i => $item) {
            $item->set($i);
            $this->cache->save($item);

            $count++;
        }

        Assert::assertSame(3, $count);

        $keys[] = 'biz';
        /** @type CacheItemInterface[] $items */
        $items = $this->cache->getItems($keys);
        $count = 0;
        foreach ($items as $key => $item) {
            $itemKey = $item->getKey();
            Assert::assertEquals($itemKey, $key, 'Keys must be preserved when fetching multiple items');
            Assert::assertTrue(in_array($key, $keys), 'Cache key can not change.');

            // Remove $key for $keys
            foreach ($keys as $k => $v) {
                if ($v === $key) {
                    unset($keys[$k]);
                }
            }

            $count++;
        }

        Assert::assertSame(4, $count);
    }

    public function testGetEmptyItems()
    {
        if (isset($this->skippedTests[__FUNCTION__])) {
            $this->markTestSkipped($this->skippedTests[__FUNCTION__]);
        }

        $items = $this->cache->getItems([]);
        Assert::assertTrue(
            is_array($items) || $items instanceof Traversable,
            'A call to getItems with an empty array must always return an array or \Traversable.'
        );

        $count = 0;
        foreach ($items as $item) {
            $count++;
        }

        Assert::assertSame(0, $count);
    }

    public function testHasItem()
    {
        if (isset($this->skippedTests[__FUNCTION__])) {
            $this->markTestSkipped($this->skippedTests[__FUNCTION__]);
        }

        $item = $this->cache->getItem('key');
        $item->set('value');
        $this->cache->save($item);

        // has existing item
        Assert::assertTrue($this->cache->hasItem('key'));

        // has non-existent item
        Assert::assertFalse($this->cache->hasItem('key2'));
    }

    public function testClear()
    {
        if (isset($this->skippedTests[__FUNCTION__])) {
            $this->markTestSkipped($this->skippedTests[__FUNCTION__]);
        }

        $item = $this->cache->getItem('key');
        $item->set('value');
        $this->cache->save($item);

        $return = $this->cache->clear();

        Assert::assertTrue($return, 'clear() must return true if cache was cleared. ');
        Assert::assertFalse($this->cache->getItem('key')->isHit(), 'No item should be a hit after the cache is cleared. ');
        Assert::assertFalse($this->cache->hasItem('key2'), 'The cache pool should be empty after it is cleared.');
    }

    public function testClearWithDeferredItems()
    {
        if (isset($this->skippedTests[__FUNCTION__])) {
            $this->markTestSkipped($this->skippedTests[__FUNCTION__]);
        }

        $item = $this->cache->getItem('key');
        $item->set('value');
        $this->cache->saveDeferred($item);

        $this->cache->clear();
        $this->cache->commit();

        Assert::assertFalse($this->cache->getItem('key')->isHit(), 'Deferred items must be cleared on clear(). ');
    }

    public function testDeleteItem()
    {
        if (isset($this->skippedTests[__FUNCTION__])) {
            $this->markTestSkipped($this->skippedTests[__FUNCTION__]);
        }

        $item = $this->cache->getItem('key');
        $item->set('value');
        $this->cache->save($item);

        Assert::assertTrue($this->cache->deleteItem('key'));
        Assert::assertFalse($this->cache->getItem('key')->isHit(), 'A deleted item should not be a hit.');
        Assert::assertFalse($this->cache->hasItem('key'), 'A deleted item should not be a in cache.');

        Assert::assertTrue($this->cache->deleteItem('key2'), 'Deleting an item that does not exist should return true.');
    }

    public function testDeleteItems()
    {
        if (isset($this->skippedTests[__FUNCTION__])) {
            $this->markTestSkipped($this->skippedTests[__FUNCTION__]);
        }

        $items = $this->cache->getItems(['foo', 'bar', 'baz']);

        /** @type CacheItemInterface $item */
        foreach ($items as $idx => $item) {
            $item->set($idx);
            $this->cache->save($item);
        }

        // All should be a hit but 'biz'
        Assert::assertTrue($this->cache->getItem('foo')->isHit());
        Assert::assertTrue($this->cache->getItem('bar')->isHit());
        Assert::assertTrue($this->cache->getItem('baz')->isHit());
        Assert::assertFalse($this->cache->getItem('biz')->isHit());

        $return = $this->cache->deleteItems(['foo', 'bar']);
        Assert::assertTrue($return);

        Assert::assertFalse($this->cache->getItem('foo')->isHit());
        Assert::assertFalse($this->cache->getItem('bar')->isHit());
        Assert::assertTrue($this->cache->getItem('baz')->isHit());
    }

    public function testSave()
    {
        if (isset($this->skippedTests[__FUNCTION__])) {
            $this->markTestSkipped($this->skippedTests[__FUNCTION__]);
        }

        $item = $this->cache->getItem('key');
        $item->set('value');
        $return = $this->cache->save($item);

        Assert::assertTrue($return, 'save() should return true when items are saved.');
        Assert::assertEquals('value', $this->cache->getItem('key')->get());
    }

    public function testSaveExpired()
    {
        if (isset($this->skippedTests[__FUNCTION__])) {
            $this->markTestSkipped($this->skippedTests[__FUNCTION__]);
        }

        $item = $this->cache->getItem('key');
        $item->set('I love coconuts.');
        $item->expiresAt(DateTime::createFromFormat('U', date('U', time() + 10)));
        $this->cache->save($item);
        $item->expiresAt(DateTime::createFromFormat('U', date('U', time() - 1)));
        $this->cache->save($item);
        $item = $this->cache->getItem('key');
        Assert::assertFalse($item->isHit(), 'Cache should not save expired items');
    }

    public function testSaveWithoutExpire()
    {
        if (isset($this->skippedTests[__FUNCTION__])) {
            $this->markTestSkipped($this->skippedTests[__FUNCTION__]);
        }

        $item = $this->cache->getItem('test_ttl_null');
        $item->set('data');
        $this->cache->save($item);

        // Use a new pool instance to ensure that we don't hit any caches
        $pool = $this->createCachePool();
        $item = $pool->getItem('test_ttl_null');

        Assert::assertTrue($item->isHit(), 'Cache should have retrieved the items');
        Assert::assertEquals('data', $item->get());
    }

    public function testDeferredSave()
    {
        if (isset($this->skippedTests[__FUNCTION__])) {
            $this->markTestSkipped($this->skippedTests[__FUNCTION__]);
        }

        $item = $this->cache->getItem('key');
        $item->set('4711');
        $return = $this->cache->saveDeferred($item);
        Assert::assertTrue($return, 'save() should return true when items are saved.');

        $item = $this->cache->getItem('key2');
        $item->set('4712');
        $this->cache->saveDeferred($item);

        // They are not saved yet but should be a hit
        Assert::assertTrue($this->cache->hasItem('key'), 'Deferred items should be considered as a part of the cache even before they are committed');
        Assert::assertTrue($this->cache->getItem('key')->isHit(), 'Deferred items should be a hit even before they are committed');
        Assert::assertTrue($this->cache->getItem('key2')->isHit());

        $this->cache->commit();

        // They should be a hit after the commit as well
        Assert::assertTrue($this->cache->getItem('key')->isHit());
        Assert::assertTrue($this->cache->getItem('key2')->isHit());
    }

    public function testDeleteDeferredItem()
    {
        if (isset($this->skippedTests[__FUNCTION__])) {
            $this->markTestSkipped($this->skippedTests[__FUNCTION__]);
        }

        $item = $this->cache->getItem('key');
        $item->set('4711');
        $this->cache->saveDeferred($item);
        Assert::assertTrue($this->cache->getItem('key')->isHit());

        $this->cache->deleteItem('key');
        Assert::assertFalse($this->cache->hasItem('key'), 'You must be able to delete a deferred item before committed. ');
        Assert::assertFalse($this->cache->getItem('key')->isHit(), 'You must be able to delete a deferred item before committed. ');

        $this->cache->commit();
        Assert::assertFalse($this->cache->hasItem('key'), 'A deleted item should not reappear after commit. ');
        Assert::assertFalse($this->cache->getItem('key')->isHit(), 'A deleted item should not reappear after commit. ');
    }

    public function testDeferredSaveWithoutCommit()
    {
        if (isset($this->skippedTests[__FUNCTION__])) {
            $this->markTestSkipped($this->skippedTests[__FUNCTION__]);
        }

        $this->prepareDeferredSaveWithoutCommit();
        gc_collect_cycles();

        $cache = $this->createCachePool();
        Assert::assertTrue($cache->getItem('key')->isHit(), 'A deferred item should automatically be committed on CachePool::__destruct().');
    }

    private function prepareDeferredSaveWithoutCommit()
    {
        $cache       = $this->cache;
        $this->cache = null;

        $item = $cache->getItem('key');
        $item->set('4711');
        $cache->saveDeferred($item);
    }

    public function testCommit()
    {
        if (isset($this->skippedTests[__FUNCTION__])) {
            $this->markTestSkipped($this->skippedTests[__FUNCTION__]);
        }

        $item = $this->cache->getItem('key');
        $item->set('value');
        $this->cache->saveDeferred($item);
        $return = $this->cache->commit();

        Assert::assertTrue($return, 'commit() should return true on successful commit. ');
        Assert::assertEquals('value', $this->cache->getItem('key')->get());

        $return = $this->cache->commit();
        Assert::assertTrue($return, 'commit() should return true even if no items were deferred. ');
    }

    /**
     * @medium
     */
    public function testExpiration()
    {
        if (isset($this->skippedTests[__FUNCTION__])) {
            $this->markTestSkipped($this->skippedTests[__FUNCTION__]);
        }

        $item = $this->cache->getItem('key');
        $item->set('value');
        $item->expiresAfter(2);
        $this->cache->save($item);

        sleep(3);
        $item = $this->cache->getItem('key');
        Assert::assertFalse($item->isHit());
        Assert::assertNull($item->get(), "Item's value must be null when isHit() is false.");
    }

    public function testExpiresAt()
    {
        if (isset($this->skippedTests[__FUNCTION__])) {
            $this->markTestSkipped($this->skippedTests[__FUNCTION__]);
        }

        $item = $this->cache->getItem('key');
        $item->set('value');
        $item->expiresAt(new DateTime('+2hours'));
        $this->cache->save($item);

        $item = $this->cache->getItem('key');
        Assert::assertTrue($item->isHit());
    }

    public function testExpiresAtWithNull()
    {
        if (isset($this->skippedTests[__FUNCTION__])) {
            $this->markTestSkipped($this->skippedTests[__FUNCTION__]);
        }

        $item = $this->cache->getItem('key');
        $item->set('value');
        $item->expiresAt(null);
        $this->cache->save($item);

        $item = $this->cache->getItem('key');
        Assert::assertTrue($item->isHit());
    }

    public function testExpiresAfterWithNull()
    {
        if (isset($this->skippedTests[__FUNCTION__])) {
            $this->markTestSkipped($this->skippedTests[__FUNCTION__]);
        }

        $item = $this->cache->getItem('key');
        $item->set('value');
        $item->expiresAfter(null);
        $this->cache->save($item);

        $item = $this->cache->getItem('key');
        Assert::assertTrue($item->isHit());
    }

    public function testKeyLength()
    {
        if (isset($this->skippedTests[__FUNCTION__])) {
            $this->markTestSkipped($this->skippedTests[__FUNCTION__]);
        }

        $key  = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789_.';
        $item = $this->cache->getItem($key);
        $item->set('value');
        Assert::assertTrue($this->cache->save($item), 'The implementation does not support a valid cache key');

        Assert::assertTrue($this->cache->hasItem($key));
    }

    public function testDataTypeString()
    {
        if (isset($this->skippedTests[__FUNCTION__])) {
            $this->markTestSkipped($this->skippedTests[__FUNCTION__]);
        }

        $item = $this->cache->getItem('key');
        $item->set('5');
        $this->cache->save($item);

        $item = $this->cache->getItem('key');
        Assert::assertTrue('5' === $item->get(), 'Wrong data type. If we store a string we must get an string back.');
        Assert::assertTrue(is_string($item->get()), 'Wrong data type. If we store a string we must get an string back.');
    }

    public function testDataTypeInteger()
    {
        if (isset($this->skippedTests[__FUNCTION__])) {
            $this->markTestSkipped($this->skippedTests[__FUNCTION__]);
        }

        $item = $this->cache->getItem('key');
        $item->set(5);
        $this->cache->save($item);

        $item = $this->cache->getItem('key');
        Assert::assertTrue(5 === $item->get(), 'Wrong data type. If we store an int we must get an int back.');
        Assert::assertTrue(is_int($item->get()), 'Wrong data type. If we store an int we must get an int back.');
    }

    public function testDataTypeNull()
    {
        if (isset($this->skippedTests[__FUNCTION__])) {
            $this->markTestSkipped($this->skippedTests[__FUNCTION__]);
        }

        $item = $this->cache->getItem('key');
        $item->set(null);
        $this->cache->save($item);

        Assert::assertTrue($this->cache->hasItem('key'), 'Null is a perfectly acceptable cache value. hasItem() should return true when null are stored. ');
        $item = $this->cache->getItem('key');
        Assert::assertTrue(null === $item->get(), 'Wrong data type. If we store null we must get an null back.');
        Assert::assertTrue(is_null__($item->get()), 'Wrong data type. If we store null we must get an null back.');
    }

    public function testDataTypeFloat()
    {
        if (isset($this->skippedTests[__FUNCTION__])) {
            $this->markTestSkipped($this->skippedTests[__FUNCTION__]);
        }

        $float = 1.23456789;
        $item  = $this->cache->getItem('key');
        $item->set($float);
        $this->cache->save($item);

        $item = $this->cache->getItem('key');
        Assert::assertTrue(is_float($item->get()), 'Wrong data type. If we store a float, a float must be returned.');
        Assert::assertEquals($float, $item->get());
        Assert::assertTrue($item->isHit(), 'isHit() should return true when a float is stored. ');
    }

    public function testDataTypeBoolean()
    {
        if (isset($this->skippedTests[__FUNCTION__])) {
            $this->markTestSkipped($this->skippedTests[__FUNCTION__]);
        }

        $item = $this->cache->getItem('key');
        $item->set(true);
        $this->cache->save($item);

        $item = $this->cache->getItem('key');
        Assert::assertTrue(is_bool($item->get()), 'Wrong data type. If we store boolean we must get an boolean back.');
        Assert::assertTrue($item->get());
        Assert::assertTrue($item->isHit(), 'isHit() should return true when true are stored. ');
    }

    public function testDataTypeArray()
    {
        if (isset($this->skippedTests[__FUNCTION__])) {
            $this->markTestSkipped($this->skippedTests[__FUNCTION__]);
        }

        $array = ['a' => 'foo', 2 => 'bar'];
        $item  = $this->cache->getItem('key');
        $item->set($array);
        $this->cache->save($item);

        $item = $this->cache->getItem('key');
        Assert::assertTrue(is_array($item->get()), 'Wrong data type. If we store array we must get an array back.');
        Assert::assertEquals($array, $item->get());
        Assert::assertTrue($item->isHit(), 'isHit() should return true when array are stored. ');
    }

    public function testDataTypeObject()
    {
        if (isset($this->skippedTests[__FUNCTION__])) {
            $this->markTestSkipped($this->skippedTests[__FUNCTION__]);
        }

        $object    = new stdClass();
        $object->a = 'foo';
        $item      = $this->cache->getItem('key');
        $item->set($object);
        $this->cache->save($item);

        $item = $this->cache->getItem('key');
        Assert::assertTrue(is_object($item->get()), 'Wrong data type. If we store object we must get an object back.');
        Assert::assertEquals($object, $item->get());
        Assert::assertTrue($item->isHit(), 'isHit() should return true when object are stored. ');
    }

    public function testBinaryData()
    {
        if (isset($this->skippedTests[__FUNCTION__])) {
            $this->markTestSkipped($this->skippedTests[__FUNCTION__]);
        }

        $data = '';
        for ($i = 0; $i < 256; $i++) {
            $data .= chr($i);
        }

        $item = $this->cache->getItem('key');
        $item->set($data);
        $this->cache->save($item);

        $item = $this->cache->getItem('key');
        Assert::assertTrue($data === $item->get(), 'Binary data must survive a round trip.');
    }

    public function testIsHit()
    {
        if (isset($this->skippedTests[__FUNCTION__])) {
            $this->markTestSkipped($this->skippedTests[__FUNCTION__]);
        }

        $item = $this->cache->getItem('key');
        $item->set('value');
        $this->cache->save($item);

        $item = $this->cache->getItem('key');
        Assert::assertTrue($item->isHit());
    }

    public function testIsHitDeferred()
    {
        if (isset($this->skippedTests[__FUNCTION__])) {
            $this->markTestSkipped($this->skippedTests[__FUNCTION__]);
        }

        $item = $this->cache->getItem('key');
        $item->set('value');
        $this->cache->saveDeferred($item);

        // Test accessing the value before it is committed
        $item = $this->cache->getItem('key');
        Assert::assertTrue($item->isHit());

        $this->cache->commit();
        $item = $this->cache->getItem('key');
        Assert::assertTrue($item->isHit());
    }

    public function testSaveDeferredWhenChangingValues()
    {
        if (isset($this->skippedTests[__FUNCTION__])) {
            $this->markTestSkipped($this->skippedTests[__FUNCTION__]);
        }

        $item = $this->cache->getItem('key');
        $item->set('value');
        $this->cache->saveDeferred($item);

        $item = $this->cache->getItem('key');
        $item->set('new value');

        $item = $this->cache->getItem('key');
        Assert::assertEquals('value', $item->get(), 'Items that is put in the deferred queue should not get their values changed');

        $this->cache->commit();
        $item = $this->cache->getItem('key');
        Assert::assertEquals('value', $item->get(), 'Items that is put in the deferred queue should not get their values changed');
    }

    public function testSaveDeferredOverwrite()
    {
        if (isset($this->skippedTests[__FUNCTION__])) {
            $this->markTestSkipped($this->skippedTests[__FUNCTION__]);
        }

        $item = $this->cache->getItem('key');
        $item->set('value');
        $this->cache->saveDeferred($item);

        $item = $this->cache->getItem('key');
        $item->set('new value');
        $this->cache->saveDeferred($item);

        $item = $this->cache->getItem('key');
        Assert::assertEquals('new value', $item->get());

        $this->cache->commit();
        $item = $this->cache->getItem('key');
        Assert::assertEquals('new value', $item->get());
    }

    public function testSavingObject()
    {
        if (isset($this->skippedTests[__FUNCTION__])) {
            $this->markTestSkipped($this->skippedTests[__FUNCTION__]);
        }

        $item = $this->cache->getItem('key');
        $item->set(new DateTime());
        $this->cache->save($item);

        $item  = $this->cache->getItem('key');
        $value = $item->get();
        Assert::assertInstanceOf('DateTime', $value, 'You must be able to store objects in cache.');
    }

    /**
     * @medium
     */
    public function testHasItemReturnsFalseWhenDeferredItemIsExpired()
    {
        if (isset($this->skippedTests[__FUNCTION__])) {
            $this->markTestSkipped($this->skippedTests[__FUNCTION__]);
        }

        $item = $this->cache->getItem('key');
        $item->set('value');
        $item->expiresAfter(2);
        $this->cache->saveDeferred($item);

        sleep(3);
        Assert::assertFalse($this->cache->hasItem('key'));
    }
}
