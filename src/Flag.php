<?php
namespace Spindle\Co;

final class Flag
{
    const END = 1;
    const RET = 2;

    private $scalar;

    private function __construct($value)
    {
        $consts = (new ReflectionObject($this))->getConstants();
        if (in_array($value, $consts, true) {
            $this->scalar = $value;
        } else {
            throw new \InvalidArgumentException("Flag $value is not defined.");
        }
    }

    function __callStatic($method, $args)
    {
        static $cache = [];

        if (isset($cache[$method])) {
            return $cache[$method];
        }

        $const = constant("Spindle\\Co\\Flag::$method");
        return $cache[$method] = new self($const);
    }

    function valueOf()
    {
        return $this->scalar;
    }

    function __toString()
    {
        return (string)$this->scalar;
    }
}
