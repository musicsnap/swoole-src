--TEST--
swoole_http_client_coro: reconnect but failed
--SKIPIF--
<?php require __DIR__ . '/../include/skipif.inc'; ?>
--FILE--
<?php
require __DIR__ . '/../include/bootstrap.php';
$pm = new ProcessManager;
$pm->initRandomData(MAX_REQUESTS);
$pm->parentFunc = function () use ($pm) {
    go(function () use ($pm) {
        $cli = new Swoole\Coroutine\Http\Client('127.0.0.1', $pm->getFreePort());
        $cli->set(['timeout' => 1]);

        $retry_time = microtime(true);
        for ($n = MAX_REQUESTS; $n--;) {
            $ret = $cli->get('/');
            assert($ret == !($n % 2));
            if ($ret) {
                assert($cli->body === $pm->getRandomData());
            }
        }
        $retry_time = microtime(true) - $retry_time;

        $pm->kill();
        usleep(1000);

        $failed_time = microtime(true);
        assert(!$cli->get('/'));
        assert($cli->errCode === SOCKET_ECONNRESET);
        assert($cli->statusCode === SWOOLE_HTTP_CLIENT_ESTATUS_SERVER_RESET);
        for ($n = MAX_REQUESTS; $n--;) {
            assert(!$cli->get('/'));
            assert($cli->errCode === SOCKET_ECONNREFUSED);
            assert($cli->statusCode === SWOOLE_HTTP_CLIENT_ESTATUS_CONNECT_FAILED);
        }
        $failed_time = microtime(true) - $failed_time;

        phpt_var_dump($failed_time, $retry_time);
        assert($failed_time < $retry_time);
    });
    swoole_event_wait();
    echo "OK\n";
};
$pm->childFunc = function () use ($pm) {
    $server = new swoole_http_server('127.0.0.1', $pm->getFreePort(), SWOOLE_BASE);
    $server->set([
        'worker_num' => 1,
        'log_file' => '/dev/null'
    ]);
    $server->on('workerStart', function () use ($pm) {
        $pm->wakeup();
    });
    $server->on('request', function (swoole_http_request $request, swoole_http_response $response) use ($pm, $server) {
        static $i = 0;
        $i++;
        if ($i % 2) {
            $server->close($request->fd);
        } else {
            $response->end($pm->getRandomData());
        }
    });
    $server->start();
};
$pm->childFirst();
$pm->run();
?>
--EXPECT--
OK
