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

use function array_keys;
use function array_map;
use function array_values;

abstract class Multiple implements CacheAdapter
{
    /**
     * {@inheritdoc}
     */
    public function getMultiple(array $keys): ?array
    {
        return array_map([$this, 'get'], $keys);
    }

    /**
     * {@inheritdoc}
     */
    public function setMultiple(array $values): ?array
    {
        foreach ($values as $key => $value) {
            if ($this->set($key, $value['value'], $value['ttl'])) {
                unset($values[$key]);
            }
        }

        return empty($values) ? null : array_keys($values);
    }

    /**
     * {@inheritdoc}
     */
    public function deleteMultiple(array $keys): ?array
    {
        foreach ($keys as $index => $key) {
            if ($this->delete($key)) {
                unset($keys[$index]);
            }
        }

        return empty($keys) ? null : array_values($keys);
    }
}
