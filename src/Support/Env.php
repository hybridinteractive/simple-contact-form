<?php

namespace hybridinteractive\SimpleContactForm\Support;

use Craft;

/**
 * Environment helper class
 */
class Env
{
    /**
     * Parse environment variables in a value.
     *
     * @param mixed $value
     * @return mixed
     */
    public static function parse($value)
    {
        if (is_string($value)) {
            return Craft::parseEnv($value);
        }

        if (is_array($value)) {
            return array_map([static::class, 'parse'], $value);
        }

        return $value;
    }
}

