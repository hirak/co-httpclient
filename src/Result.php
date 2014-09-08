<?php
namespace Spindle\Co;

/**
 * 並列実行のレスポンスなどを一時的に蓄えるための
 * Generatorもどき
 */
class Result
{
    private $ret;

    function __construct($ret) {
        $this->ret = $ret;
    }

    function valid() {
        return true;
    }

    function current() {
        return $this->ret;
    }

    function key() {
        return 'return';
    }

    function __call($method, $args) {
        if ($method !== 'throw') throw new \BadMethodCallException;
        $e = $args[0];
        throw $e;
    }
}
