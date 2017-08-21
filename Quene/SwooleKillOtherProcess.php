<?php
$otherVer = '20170301';
$other_killFile = TSWOOLE_VERTMPROOT . 'SwooleService.otherkill.ver';
if ((!is_file($other_killFile)) || file_get_contents($other_killFile) != $otherVer) {
	/*
	$killUdp = false;
	if(oo::$config['sid']==13){
		$killUdp = true;
	}
	if($killUdp){
		$psGrepArr = array('udp.php', 'callbackudp.php'); //udp的需要等swoole启动起来后再杀，防止丢数据
	}else{
		$psGrepArr = array('callbackdo.sh.php', 'doproc.sh.php', 'writetbl.sh.php', 'doudp.sh.php','doudp.php','writeoli.php','writeoff.sh.php', 'writenow.sh.php', 'dolist.sh.php');	
	}
	*/
	$psGrepArr = array(
		'crontab/udplog.php',
		'data/clearmongo'
	);
	foreach ($psGrepArr as $psGrep) {
		$psGrep = WWWROOT . $psGrep;
		$kill_runFile_sh = 'ps -eaf |grep "' . $psGrep . '" | grep -v "grep"| awk \'{print $2}\'|xargs kill -9';
		system($kill_runFile_sh);
	}
	file_put_contents($other_killFile, $otherVer, null);
}

