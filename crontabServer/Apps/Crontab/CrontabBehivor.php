<?php

define('SWOOLE_CRONTAB_ROOT', dirname(__FILE__) . '/');
include_once TSWOOLE_LIBROOT . 'Lib.php';
SwooleLib::LoadLibClass('SwooleBehavior', 'Core');
SwooleLib::LoadLibClass('SwooleHelper', 'Core');
include_once SWOOLE_CRONTAB_ROOT . 'Model/SwooleModelCrontab.php';

class CrontabBehivor extends SwooleBehavior {

	/**
	 * 处理包信息
	 * @var ReadPackageExt 
	 */
	private $readPackage;

	/**
	 * 处理TCP协议
	 * @param type $server
	 * @param type $fd
	 * @param type $from_id
	 * @param type $packet_buff
	 * @throws Exception
	 */
	public function onReceive($server, $fd, $from_id, $packet_buff) {
		try {
			$this->readPackage->ReadPackageBuffer($packet_buff);
			SwooleHelper::I()->Reset($server, $fd, $from_id);
			$action = $this->readPackage->GetCmdType();
			switch ($action) {
				case "0x881"://Test					
					SwooleModelCrontab::TestAndClearCrontabRunCnt();
					$write = new WritePackage();
					$write->WriteBegin($action);
					$write->WriteByte(1);
					SwooleHelper::I()->SendPackage($write);
					break;
				case "0x882"://重新reload
					SwooleHelper::I()->Swoole->reload();
					$write = new WritePackage();
					$write->WriteBegin($action);
					$write->WriteByte(1);
					SwooleHelper::I()->SendPackage($write);
					break;
				case "0x883"://获取监控信息及状态
					SwooleModelcrontab::GetMonitorInfo($action);
					break;
			}
		} catch (Exception $ex) {
			$info = $server->connection_info($fd);
			$info['exption'] = $ex;
			Swoole_Log('CrontabBehivorRecev', var_export($info, 1));
		}
	}

	/**
	 * 达到内存峰值时(300M)则自动退出
	 * @param type $limit
	 */
	private function CheckMemoryLimitAndExit($server,$limit = 200) {
		$useMem = memory_get_usage(1) / 1024 / 1024;
		if ($useMem > $limit) {
			system("kill -15 " . $server->worker_pid);
			usleep(50);
			if($useMem>300){				
				exit();
			}
		}
	}

	/**
	 * 处理Task异步任务
	 * @param type $serv
	 * @param type $task_id
	 * @param type $from_id
	 * @param type $data
	 */
	public function onTask($serv, $task_id, $from_id, $data) {
		$cmdArr = explode('|', $data);
		if (count($cmdArr) > 1) {
			$beginTime = microtime(true);
			$beginUseMem = memory_get_usage(1);
			$processName = $cmdArr[0];
			$method = $cmdArr[1];
			$GLOBALS['crontab_method'] = $method;
			$workid = $serv->worker_id;
			$result = SwooleModelCrontab::SaveCrontabRunCnt($processName, true, $workid);
			if (!$result) {
				return;
			}
			eval($method);
			SwooleModelCrontab::SaveCrontabRunCnt($processName, false, $workid);
			$this->destoryCache();
			SwooleModelCrontab::SaveMonitorInfo($beginTime, $method,$beginUseMem);
		}
		SwooleModelCrontab::SaveWorkerRequestCount($serv->worker_id);
		$this->CheckMemoryLimitAndExit($serv);
	}
	public function destoryCache(){
		ocache::destroy(false);
	}

	/**
	 * Work/Task进程启动
	 * @global type $config
	 * @param type $serv
	 * @param type $worker_id
	 */
	public function onWorkerStart($serv, $worker_id) {
		//加载Texas初始化配置
		define('IN_WEB', true);
		define('IN_CRONTAB', true);
		global $config;
		include( TSWOOLE_WEBROOT . 'common.php');
		ini_set('memory_limit', '512M');
		set_time_limit(0);		
		SwooleModelCrontab::SaveWorkerRunInfo($worker_id);
		SwooleModelCrontab::SaveMonitorInfoToLocal();
		oo::setLang(oo::$config['langtype']);
		if (!$serv->taskworker) {
			$this->readPackage = new ReadPackageExt();
			//前4个工作进程
			if ($worker_id > 4) {
				return;
			}
			//Work进程启动，执行定时任务
			$processList = SwooleModelCrontab::GetCrontabConfig(SwooleModelCrontab::ProcessListName);
			if (empty($processList)) {
				return;
			}
			$ticks = array();
			foreach ($processList as $process) {
				if (!in_array($process['interval'], $ticks)) {
					$ticks[] = $process['interval'];
				}
			}
			foreach ($ticks as $interval) {
				$serv->tick($interval, function() use ($serv, $interval) {
					$workid = $serv->worker_id;
					$processList = SwooleModelCrontab::GetCrontabConfig(SwooleModelCrontab::ProcessListName);
					foreach ($processList as $name => $process) {
						if (($process['interval'] == $interval) && (!isset($process['workid']) || ($workid == $process['workid']))) {
							SwooleModelCrontab::CrontabProcess($serv, $name);
						}
					}
				});
			}
		}
	}

}
