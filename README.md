hiraku/co-httpclient
========================

yield(generator)で`curl_multi`をうまいこと書けるようにした関数のサンプル実装。
まだ結構バグバグなので、本番利用は危険です。

## install

requirement:

- php >= 5.5
- ext-curl

```sh
$ composer require hirak/co-httpclient
```

## 使い方

```php
<?php
require 'vendor/autoload.php';

use Spindle\HttpClient;

/**
 * JSONを返すWebAPIをGETメソッドで叩く関数。
 *
 * - 待ちが発生するところにyieldキーワードを埋め込む。
 * - returnの代わりにyieldキーワードを使う。
 * - 例外は通常通り使用可能。
 */
function getWebapiAsync($url) {
    $req = new HttpClient\Request($url);

    /** @type HttpClient\Response */
    $res = (yield $req);

    if (($status = $res->getStatusCode()) >= 400) {
        throw new \RuntimeException($url, $status);
    }

    yield json_decode($res->getBody());
}

//試しにpackagist.orgのjsonを取ってきてパースしてみる
// json_decodeなどはなるべくWebAPIの待ち時間中に処理されます。

list($jpmirror, $origin) = co(
    getWebapiAsync('http://composer-proxy.jp/proxy/packagist/packages.json'),
    getWebapiAsync('https://packagist.org/packages.json')
);

var_dump($jpmirror, $origin);

```


## license

CC0-1.0 (Public Domain)
