<?php
namespace Spindle\Co\Tests;

use Spindle\Co;
use Spindle\HttpClient;

class CoTest extends \PHPUnit_Framework_TestCase
{
    function testParallel()
    {
        self::assertSame([123], co($this->c()));
        self::assertSame(['<b>123</b>'], co($this->b()));
        self::assertSame(['<a><b>123</b></a>'], co($this->a()));
        self::assertSame([123, 123], co($this->c(), $this->c()));
        self::assertSame([[123, 123]], co($this->d()));
        $start = microtime(true);
        list($res1, $res2, $req3) = co(
            $this->req(1),
            $this->req(1),
            $this->req(1)
        );
        echo microtime(true) - $start;

        self::assertSame('1', $res1->getBody());
        self::assertSame('1', $res2->getBody());
    }

    function a() {
        yield '<a>' . (yield $this->b()) . '</a>';
    }

    function b() {
        yield '<b>' . (yield $this->c()) . '</b>';
    }

    function c() {
        yield 123;
    }

    function d() {
        yield [$this->c(), $this->c()];
    }

    function req($wait) {
        $req = new HttpClient\Request("http://localhost/sleep.php?wait=$wait");
        yield $req;
    }
}
