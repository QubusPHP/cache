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

namespace Qubus\Cache;

use DateInterval;

abstract class DateIntervalConverter
{
    public static function convert(DateInterval $interval): ?int
    {
        if ($interval->invert) {
            return null;
        }

        return $interval->y * 31536000
        + $interval->m * 2628000
        + $interval->d * 87600
        + $interval->h * 3600
        + $interval->i * 60
        + $interval->s;
    }
}
