--TEST--
swoole_server: addProcess
--SKIPIF--
<?php require __DIR__ . "/../include/skipif.inc"; ?>
--INI--
assert.active=1
assert.warning=1
assert.bail=0
assert.quiet_eval=0

--FILE--
<?php
require_once __DIR__ . "/../include/swoole.inc";

$serv = new \swoole_server(TCP_SERVER_HOST, 9501);
$process = new \Swoole\Process(function ($process) use ($serv) {
    $s = $serv->stats();
    assert($s and is_array($s) and count($s) > 1);
    $serv->shutdown();
});

$serv->set([
    "worker_num" => 1,
    'log_file' => '/dev/null',
]);

$serv->on("Receive", function (\swoole_server $serv, $fd, $rid, $data) use ($process) {

});

$serv->addProcess($process);
$serv->start();

?>
--EXPECT--