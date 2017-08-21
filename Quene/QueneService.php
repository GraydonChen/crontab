<?php
require dirname(__FILE__).'/Service.php';
$slave = TSWOOLE_MAIN ? '' : '.slave';
if (TSWOOLE_ENV === 1) {
	$SwooleConfig = include dirname(__FILE__) . "/Config/Quene/config.on{$slave}.php";
} else {
	$SwooleConfig = include dirname(__FILE__) . "/Config/Quene/config.demo{$slave}.php";
}
$SwooleConfig['MainProcessName'] = implode(' ', $argv);
$SwooleConfig['Port'] = TSWOOLE_PORT;
$SwooleConfig['SocketType'] = SWOOLE_SOCK_TCP;
$SwooleConfig["Behavior"] = array("QueneBehivor", TSWOOLE_WEBROOT . 'crontab/Swoole/Apps/Quene/QueneBehivor.php');
$QueneService = new SwooleService($SwooleConfig);
function processEnd() {
	$error = error_get_last();
	if ($error) {
		$error['date'] = date('Ymd H:i:s', time());
		Swoole_Log('swoole_quene_run_error', var_export($error, true));
	}
}
register_shutdown_function("processEnd");

//用于记录 进程启动时间
global $crontab_work_table;
$crontab_work_table = new swoole_table(1024);
$crontab_work_table->column('workid', swoole_table::TYPE_INT, 1);
$crontab_work_table->column('beginTime', swoole_table::TYPE_INT, 4);
$crontab_work_table->column('use_mem', swoole_table::TYPE_INT, 4);
$crontab_work_table->create();

$QueneService->Swoole->addListener($SwooleConfig['Host'], TSWOOLE_UDPPORT, SWOOLE_SOCK_UDP);
$QueneService->Start();
