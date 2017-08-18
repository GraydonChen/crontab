<?php
system("source /etc/profile");
system("umask 002");
//此进程会每隔1分钟启动一次,用于启动Swoole及监控，确保swoole出故障后会自动重启
define('IN_WEB', true);
define('TSWOOLE_ROOT', dirname(__FILE__) . '/');
define('TSWOOLE_VERROOT', dirname(__FILE__) . '/Ver/');
define('TSWOOLE_WEBMAIN', intval($argv[1])); //1：为主web服务 0：为从web
require dirname(__FILE__) . '/../../common.php';

if (IS_PHP7){
	define('PHP_BIN', '/usr/local/php7/bin/php -f ');
}else{
	define('PHP_BIN', '/usr/local/php/bin/php -f ');	
}

define('TSWOOLE_VERTMPROOT', PATH_DAT . 'swooletmp/');
if (!is_dir(TSWOOLE_VERTMPROOT)) {
	mkdir(TSWOOLE_VERTMPROOT, 0775, true);
}
//启动监控程序
$monitorPath = PHP_BIN . TSWOOLE_ROOT.'Monitor.php >/dev/null 2>&1 &';		
system($monitorPath);
if(PRODUCTION_SERVER){
	//连接外网检测并设代理，当前只有新浪平台支持，需要一些特殊配置
	if (oo::$config['sid'] == 117 && TSWOOLE_WEBMAIN === 1) {
		$netCheck = PHP_BIN . TSWOOLE_ROOT . 'NetCheck.php >/dev/null 2>&1 &';
		system($netCheck);
	}
}
abstract class SwooleMonitorBase {

	/**
	 * 测试服务器是否在运行
	 */
	abstract function TestSwooleIsWorking();

	/**
	 * 使用TCP热重启服务器
	 */
	abstract function ReloadSwooleByTcp();

	/**
	 * 进程名
	 * @var type 
	 */
	protected $SwooleName, $SwooleTcpPort, $SwooleUdpPort;
	/**
	 * 主进程名
	 * @var type 
	 */
	public $run_processName;
	/**
	 * 强制重启需设置的版本号
	 */
	abstract function getKillVer();

	/**
	 * 启动Swoole
	 */
	public function Start() {
		$port = $this->SwooleTcpPort;
		$udpport = $this->SwooleUdpPort;
		//防止端口忘记配置
		if ($port < 5000 || $udpport < 5000) {
			die('not set port!');
		}
		
		$env = PRODUCTION_SERVER ? 1 : 0;
		$runFile = TSWOOLE_ROOT . $this->SwooleName . '.php ' . oo::$config['sid'] . ' ' . $env . ' ' . TSWOOLE_WEBMAIN . ' ' . $port . ' ' . $udpport;
		$psGrep = TSWOOLE_ROOT . $this->SwooleName . '.php ' . oo::$config['sid'] . ' ';
		
		$this->run_processName = PHP_BIN . $runFile;
		//强制重启Swoole进程
		$killVer = $this->getKillVer();
		$killFile = TSWOOLE_VERTMPROOT . $this->SwooleName . '.kill.ver';
		$basename = basename(WWWROOT); //解决同服务器有多个平台运行的情况
		$reload = false;
		if ((!is_file($killFile)) || file_get_contents($killFile) != $killVer) {
			$this->ReloadSwooleService($runFile, $psGrep, 1, $this->SwooleName);
			file_put_contents($killFile, $killVer, null);
			$reload = true;
		} else {
			#检查进程是否存在，不存在则重启
			$check = 3;
			$working = 0;
			while ($check) {
				$working = $this->TestSwooleIsWorking();
				if ($working) {
					break;
				}
				sleep(1);
				$check--;
			}
			if (!$working) {
				$this->ReloadSwooleService($runFile, $psGrep, 2, $this->SwooleName);
				$reload = true;
			}
		}
		//重启work进程
		if (!$reload) {
			$swooleVer = $this->getHotReloadVer();
			$CrontabVerFile = TSWOOLE_VERTMPROOT . $this->SwooleName . '.ver';
			if ((!is_file($CrontabVerFile)) || file_get_contents($CrontabVerFile) != $swooleVer) {
				$check = 3;
				$send = false;
				while ($check) {
					$send = $this->ReloadSwooleByTcp();
					if ($send) {
						break;
					}
					sleep(1);
					$check--;
				}
				if (!$send) {
					$this->ReloadSwooleService($runFile, $psGrep, 3, $this->SwooleName);
				}
				file_put_contents($CrontabVerFile, $swooleVer, null);
			}
		}
	}
	/**
	 * 获取重启的版本号
	 * @return type
	 */
	private function getHotReloadVer(){
		$swooleverData = include_once TSWOOLE_VERROOT . $this->SwooleName . '.ver.php';
		return $swooleverData['ver'];
	}
	/**
	 * 记录版本号
	 * @param type $swooleVer
	 */
	private function writeHotReloadVer($swooleVer){		
		$SwooleVerFile = TSWOOLE_VERTMPROOT . $this->SwooleName . '.ver';
		file_put_contents($SwooleVerFile, $swooleVer, null);
	}
	/**
	 * 重启Swoole进程，先kill再重启
	 * @param type $runFile
	 * @param type $psGrep
	 * @param type $runType
	 * @param type $swooleName
	 */
	public function ReloadSwooleService($runFile, $psGrep, $runType, $swooleName) {
		$kill_runFile_sh = 'ps -eaf |grep "' . $psGrep . '" | grep -v "grep"| awk \'{print $2}\'|xargs kill -9';
		//主进程退出后，杀掉所有未退出的子进程，防止task进程刚启动情况
		system($kill_runFile_sh);
		sleep(1);
		system($kill_runFile_sh);
		sleep(1);
		system($kill_runFile_sh);
		$this->run_processName = PHP_BIN . $runFile;
		$run = $this->run_processName . ' > /dev/null 2>&1 &';		
		system($run);
		//更新重启版本号
		$swooleVer = $this->getHotReloadVer();		
		$this->writeHotReloadVer($swooleVer);
		//记录重启日志
			$types=array(
			1=>'被人为强制杀进程重启',
			2=>'检测到服务异常，系统强制杀进程重启',
			3=>'无法热重启，系统强制杀进程重启'
		);	
		if ($runType != 1) {
			SwooleLib::LoadLibClass('SwooleHelper', 'Core');
			$ip = long2ip(SwooleHelper::Get_Local_Ip());
			$info = "[server error]-Swoole服务进程【" . $ip . "-" . $swooleName . "】异常:" . $types[$runType];
			
			functions::fatalError($info);
		}
	}

}
if(PRODUCTION_SERVER || TSWOOLE_WEBMAIN){
		//启动CrontabService.php 定时任务,内网所有定时只有主Web执行 
		include_once dirname(__FILE__) . '/CrontabMonitor.php';
		$CrontabMonitor = new SwooleCrontabMonitor();
		$CrontabMonitor->Start();
}

//iplocation
if ( (SERVER_TYPE === 'demo' && oo::$config['sid'] == 13) || PRODUCTION_SERVER ) {
	include_once dirname(__FILE__) . '/IpLocationMonitor.php';
	$IpLocationMonitor = new IpLocationMonitor();
	$IpLocationMonitor->Start();
}

//启动Udp/Tcp服务
include_once dirname(__FILE__) . '/QueneMonitor.php';
$QueneMonitor = new SwooleQueneMonitor();
$QueneMonitor->Start();
if(PRODUCTION_SERVER && TSWOOLE_WEBMAIN && oo::$config['OpenUdpLogStart_Swoole']){
	//启动Udp/日志服务
	include_once dirname(__FILE__) . '/LogMonitor.php';
	$LogMonitor = new SwooleLogMonitor();
	$LogMonitor->Start();
}elseif(!PRODUCTION_SERVER && !TSWOOLE_WEBMAIN){
	//内网日志全部放在192.168.202.93机器上	
	//启动Udp/日志服务
	include_once dirname(__FILE__) . '/LogMonitor.php';
	$LogMonitor = new SwooleLogMonitor();
	$LogMonitor->Start();
}

if (PRODUCTION_SERVER) {
	//杀进程,用于部署需要
	include_once dirname(__FILE__) . '/SwooleKillOtherProcess.php';
}

if(PRODUCTION_SERVER && TSWOOLE_WEBMAIN){//主服务器预警系统监控
	oo::warning()->check();
}

if(oo::$config['swivtserver'] && TSWOOLE_WEBMAIN){
	//启动邀请打牌服务,只有主Web执行 
	include_once dirname(__FILE__) . '/InviteMonitor.php';
	$InviteMonitor = new SwooleInviteMonitor();
	$InviteMonitor->Start();
}