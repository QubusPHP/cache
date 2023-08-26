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

use Psr\Cache\InvalidArgumentException as Psr6TypeException;
use Psr\SimpleCache\InvalidArgumentException as Psr16TypeException;
use Qubus\Exception\Exception;

class TypeException extends Exception implements Psr6TypeException, Psr16TypeException
{
}
