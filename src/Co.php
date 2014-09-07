<?php
namespace Spindle\Co;

use Spindle\HttpClient;
use Generator;
use SplStack;
use Exception;
use SplObjectStorage;

function co()
{
    $args = func_get_args();
    if (count($args) === 1 && is_array($args[0])) {
        $args = array_values($args[0]);
    }

    $returns = [];
    $coroutines = [];
    foreach ($args as $i => $arg) {
        if ($arg instanceof Generator) {
            $coroutines[$i] = $arg;
        } else {
            $returns[$i] = $arg;
        }
    }

    for (;;) {
        foreach ($coroutines as $i => $co) {
            $e = null;
            $flag = $co->key();
            try {
                $ret = $co->current();
            } catch (\Exception $e) {
                //どうする？
                //呼び出し元があれば、stackからpopしてthrowしなおす。
            }

            if (! $co->valid() || $flag instanceof Flag && $flag->valueOf() === Flag::END) {
                $returns[$i] = $ret;
                unset($coroutines[$i]);
            }
        }
        if (! $coroutines) break;
    }

    ksort($returns, SORT_NUMERIC);
    return $returns;
}

function asleep($seconds) {
    return new Wait($seconds);
}

function stackedCoroutine(Generator $g) {
    $stack = new SplStack;

    for (;;) {
        try {
            $value = $g->current();
        } catch (\Exception $e) {
            exceptionHandle($e, $stack);
        }

        if ($value instanceof Generator) {
            $stack->push($g);
            $g = $value;
            continue;
        }

        $isRetval = $g->key() === 'return';
        if (!$g->valid() || $isRetval) {
            if ($stack->isEmpty()) {
                return $value;
            }

            $g = $stack->pop();
            try {
                $g->send($isRetval ? $value : null);
            } catch (\Exception $e) {
                exceptionHandle($e, $stack);
            }
            continue;
        }

        try {
            $g->send($value);
        } catch (\Exception $e) {
            exceptionHandle($e, $stack);
        }
    }
}

function exceptionHandle(\Exception $e, SplStack $stack) {
    echo 1, PHP_EOL;
    while (! $stack->isEmpty()) {
        $g = $stack->pop();
        try {
            $g->throw($e);
            $e = null;
        } catch (\Exception $e) {
        }

        if ($e === null) return;
    }
    throw $e;
}


function a() {
    echo "<a>\n";
    echo (yield b());
    echo "</a>\n";
}

function b() {
    echo "<b>\n";
    try {
        echo (yield c());
    } catch (\Exception $e) {
        echo 'Exception';
    }
    echo "</b>\n";
}

function c() {
    echo "<c>\n";
    echo (yield 123);
    echo "</c>\n";
    throw new \Exception;
}

//foreach (stackedCoroutine(a()) as $_) {
//    echo $_;
//}
stackedCoroutine(a());

