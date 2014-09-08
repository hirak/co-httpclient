<?php
/**
 * hirak/co
 *
 */
namespace {

use Spindle\HttpClient;
use Spindle\Co;

/**
 * @param Generator[] $args
 * @return mixed[]
 */
function co()
{
    // normalize
    $args = func_get_args();
    if (count($args) === 1 && is_array($args[0])) {
        $args = array_values($args[0]);
    }

    // initialize
    $returns = [];

    $blockedList = [];
    $runningList = [];

    foreach ($args as $i => $arg) {
        if ($arg instanceof Generator) {
            $arg->responseId = $i;
            $stack = new SplStack;
            $stack->push($arg);
            $runningList[spl_object_hash($stack)] = $stack;
        } else {
            $returns[$i] = $arg;
        }
    }

    $multiRequest = new HttpClient\Multi;

    // mainloop
    for (;;) {
        foreach ($runningList as $i => $stack) {
            $g = $stack->pop();
            for (;;) { //stacked co-routineの処理
                try {
                    $valid = $g->valid();
                } catch (Exception $e) {
                    Co\exceptionHandle($e, $stack);
                }

                if ($valid) {
                    $ret = $g->current();

                    if ($ret instanceof Generator) {
                        $stack->push($g);
                        $g = $ret;
                        continue; //そのままもぐる
                    }

                    if ($ret instanceof HttpClient\Request) {
                        //curl_multiを開始
                        $stack->push($g);
                        $ret->stackId = spl_object_hash($stack);
                        $multiRequest->attach($ret);
                        $multiRequest->start();

                        //stackをblockedListに移す
                        $blockedList[$i] = $stack;
                        unset($runningList[$i]);
                        continue 2; //他のcoroutineを調べる
                    }

                    if (Co\isGeneratorArray($ret)) {
                        $stack->push($g);
                        $stack->parallel = true;
                        $stack->ret = [];
                        $stack->cnt = count($ret);

                        //stackをblockedListに移す
                        $blockedList[$i] = $stack;
                        unset($runningList[$i]);

                        //子coroutineをrunningListに移す
                        foreach ($ret as $responseId => $g2) {
                            $stack = new SplStack;
                            $stack->push($g2);
                            $stack->parentStackId = $i;
                            $stack->responseId = $responseId; //何番目の引数だったか記憶
                            $runningList[spl_object_hash($stack)] = $stack;
                        }

                        continue 2;
                    }
                }

                if (!$valid || $g->key() === 'return') {
                    if ($stack->isEmpty()) {
                        if (isset($stack->parentStackId)) {
                            $parent = $blockedList[$stack->parentStackId];
                            $parent->ret[$stack->responseId] = $ret;
                            $parent->cnt--;
                            unset($stack->parentStackId);
                        } else {
                            $returns[$g->responseId] = $ret;
                        }
                        unset($runningList[$i]);
                        continue 2;
                    }
                    $g = $stack->pop();
                }

                try {
                    $g->send($ret);
                } catch (Exception $e) {
                    Co\exceptionHandle($e, $stack);
                }
            }
        }

        if ($runningList) {
            continue;
        } elseif (!$blockedList) {
            break;
        }

        for (;;) {
            //runningがゼロになったので、イベント発生を待つ

            //blockedが解除されている並列処理待ちがあればrunningへ移す
            foreach ($blockedList as $i => $stack) {
                if (isset($stack->parallel) && $stack->cnt <= 0) {
                    ksort($stack->ret, SORT_NUMERIC);
                    $stack->push(new Spindle\Co\Result($stack->ret));
                    $runningList[$i] = $stack;
                    unset($blockedList[$i]);
                }
            }

            //(何かイベントが発生するか、何かエラーが起きるまでブロックする)
            if (count($multiRequest) > 0) {
                /** @type HttpClient\Request[] $requests */
                $requests = $multiRequest->getFinishedResponses();
                foreach ($requests as $req) {
                    $stack = $blockedList[$req->stackId];
                    $result = new Spindle\Co\Result($req->getResponse());
                    $result->responseId = 1;
                    $stack->push($result);
                    $runningList[$req->stackId] = $stack;
                    unset($blockedList[$req->stackId]);
                }
            }
            if ($runningList) continue 2;
        }
    }

    ksort($returns, SORT_NUMERIC);
    return $returns;
}

}


namespace Spindle\Co {

    function exceptionHandle(\Exception $e, SplStack $stack) {
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

    function isGeneratorArray($arr) {
        if (!is_array($arr)) return false;

        foreach ($arr as $g) {
            if (! $g instanceof Generator) {
                return false;
            }
        }
        return true;
    }
}

