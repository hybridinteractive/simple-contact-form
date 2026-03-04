<?php

namespace hybridinteractive\SimpleContactForm\Support;

/**
 * Array helper class
 */
class Arr
{
    /**
     * Remove an item from an array and return its value.
     *
     * @param array $array
     * @param string $key
     * @param mixed $default
     * @return mixed
     */
    public static function pull(array &$array, string $key, $default = null)
    {
        if (array_key_exists($key, $array)) {
            $value = $array[$key];
            unset($array[$key]);
            return $value;
        }

        return $default;
    }
}

