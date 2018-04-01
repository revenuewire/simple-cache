<?php
/**
 * Created by IntelliJ IDEA.
 * User: swang
 * Date: 2018-03-30
 * Time: 8:34 PM
 */

namespace RW;

use Psr\SimpleCache\CacheException;

class SimpleCacheException extends \InvalidArgumentException implements CacheException
{

}