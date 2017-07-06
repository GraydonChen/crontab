<?php
/**
 * Description of SwooleCrontabConfig
 *
 * @author JsonChen
 */
class SwooleCrontabConfig {

	/**
	 * 主服务器任务列表
	 * @return type
	 */
	public static function getMainRunList($sid, $online) {
		//method:要运行的方法名，
		//cnt:最大并发执行数  
		//interval:每隔n秒触发一次，
		//workerid:只在指定 workid进程上执行，默认为第1个
		//excludeSid array 排除的站点
		//includeSid array 包含的站点
		//switch 表示必须开启某个开关，oo::$config['']
		//日语平台玩家数很小，线上主web不跑以下任务(doproc,callback,doudp)
		//印尼、繁体、腾讯平台内存较小，调小进程数
		$timei = intval(date('i', time())); //分钟,00 - 59
		$processList['hallRankListCrontab'] = array(
			'method' => 'oo::topboard()->hallRankListCrontab();',
			'cnt' => 1,
			'interval' =>60,
		);
		$processList['AsyncCall'] = array(
			'method' => 'Mod\Base\AsyncCall::exec();',
			'cnt' => 1,
			'interval' => 1,
			'excludeSid' => $online ? array(57,110) : array(),
		);
		if(oo::$config['sid'] == 13){
			$processList['AsyncCall']['cnt'] = 2;
		}
		$processList['mkcount'] = array(
			'method' => 'oo::mtcount()->mkcount(0);',
			'cnt' => 1,
			'interval' => 60,
		);
		$processList['doUdp'] = array(
			'method' => 'oo::udp()->doUdp();',
			'cnt' => 1,
			'interval' => 1,
			'excludeSid' => $online ? array(57,110) : array(),
		);
		$processList['doproc0'] = array(
			'method' => 'oo::proc()->doProc(0);',
			'cnt' => 1,
			'interval' => 1,
			'excludeSid' => $online ? array(57,110) : array(),
		);
		$processList['doproc1'] = array(
			'method' => 'oo::proc()->doProc(1);',
			'cnt' => 1,
			'interval' => 1,
			'excludeSid' => $online ? array(57,110) : array(),
			'switch'=>'newhall'
		);
		$processList['doproc109'] = array(
			'method' => 'oo::proc()->doProc(109);',
			'cnt' => 1,
			'interval' => 1,
			'excludeSid' => $online ? array(57,110) : array(),
		);
		$processList['writeoli'] = array(
			'method' => 'oo::membertable()->writeoli();',
			'cnt' => 1,
			'interval' => 3,
			'workid' => 1
		);
		$processList['writeoff'] = array(
			'method' => 'oo::offline()->writeoff();',
			'cnt' => 1,
			'interval' => 1800,
			'workid' => 1
		);
		$processList['writenow'] = array(
			'method' => 'oo::history()->writenow(1);',
			'cnt' => 1,
			'interval' => 1800,
			'workid' => 1
		);
        oo::notice()->doList();
		$processList['dolist'] = array(
			'method' => 'oo::notice()->doList();',
			'cnt' => 1,
			'interval' => 1,
			'workid' => 1,
			'excludeSid' => $online ? array(57,110) : array(),
		);
		foreach ([0, 1, 2, 3] as $ttype) {
			$processList["genTableList{$ttype}"] = array(//生成桌子列表
				'method' => 'oo::tablesList()->sortTableList('.$ttype.');',
				'cnt' => 1,
				'interval' => 200,
			);
		}
		$processList['SaveMonitorInfoToLocal'] = array(
			'method' => 'SwooleModelCrontab::SaveMonitorInfoToLocal();',
			'cnt' => 1,
			'interval' => 60,
			'workid' => 1
		);
		$processList['doplayinvite'] = array(
			'method' => 'oo::playinvite()->crontab();',
			'cnt' => 1,
			'interval' => 60,
			'workid' => 1,
		);
		$processList['mttback'] = array(
			'method' => 'oo::swooleMtt()->doMttBack();',
			'cnt' => 1,
			'interval' => $online ? 1 : 0.2,
			'includeSid' => array(101, 104, 143)
		);		
		$processList['cmscrontab'] = array(
			'method' => 'oo::cmscrontab()->crontab();',
			'cnt' => 1,
			'interval' => 60,
		);
		if (!$online) {
			$processList['cmscrontab_run'] = array(
				'method' => 'oo::cmscrontab()->dorun();',
				'cnt' => 1,
				'interval' => 5,
			);
		}
		$processList['donqueue'] = array(
			'method' => 'oo::nqueue()->run();',
			'cnt' => 1,
			'interval' => 5,
		);
		$processList['mailtasknew'] = array(
			'method' => 'oo::obj("mailtasknew")->crontab();',
			'cnt' => 1,
			'interval' => 60,
		);
		$processList['dotableslog'] = array(
			'method' => 'oo::tablesloganalyze()->run();',
			'cnt' => oo::$config['sid']==13?3: 2,
			'interval' => 1,
			'excludeSid' => $online ? array(57,110) : array(),
		);
		$processList['mongockeck'] = array(
			'method' => 'omongotable::mongockeck();',
			'cnt' => 1,
			'interval' => 60,
		);
		if (oo::$config['openRedisTopBoard'] || oo::$config['mbDailyWinRank']) {
			$processList['topboard'] = array(
				'method' => 'oo::topboard()->run();',
				'cnt' => 1,
				'interval' => 60,
			);
		}
		$processList['dojbslogin'] = array(
			'method' => 'oo::matchnew()->dojbslogin();',
			'cnt' => 1,
			'interval' => 60,
		);
		$processList['msgPush'] = array(
			'method' => 'oo::msgPush()->run();',
			'cnt' => 1,
			'interval' => 60,
		);
		if(!PRODUCTION_SERVER || oo::$config['swoole_mlog_mf']){
			$processList['domlog_new'] = array(
				'method' => 'oo::boyaammoneynew()->run();',
				'cnt' => 1,
				'interval' => 5
			);	
			$processList['domlog_check'] = array(
				'method' => 'oo::boyaammoneynew()->checkMlog();',
				'cnt' => 1,
				'interval' => 30,
			);
			if(method_exists(oo::boyaammoneynew(), "doLimitLog")){
				$processList['domlog_limit'] = array(
					'method' => 'oo::boyaammoneynew()->doLimitLog();',
					'cnt' => 1,
					'interval' => 30,
				);
			}
		}
		$processList['senddata'] = array(
			'method' => 'oo::monitor()->senddata();',
			'cnt' => 1,
			'interval' => 60,
		);
		if($online){//内网不检测
			$processList['checkserver'] = array(//检测sver
				'method' => 'oo::monitor()->checkServer();',
				'cnt' => 1,
				'interval' => 180,
			);
		}
		if($online){//内网不检测
			$processList['checkgi'] = array(//检测gi淘汰数
				'method' => 'oo::monitor()->checkgi();',
				'cnt' => 1,
				'interval' => 600,
			);
		}
		foreach (array(0,1,2,3,4,5,6,1004) as $ttype){
			$processList['writetbl'.$ttype] = array(
				'method' => 'oo::swooledotables()->doWritetbl('.$ttype.');',
				'cnt' => 1,
				'workid' => 0,
				'interval' => 1,
			);
		}
		if(oo::$config['pineapple']){
			$processList['hallGameparty'] = array(
				'method' => 'oo::pineapple()->realGameparty();',
				'cnt' => 1,
				'interval' => 60,
			);
		}
		if(oo::$config['mttRobotOptimize']){
			$processList['robotMttSignUp'] = array(
				'method' => 'oo::robot()->robotMttSignUp();',
				'cnt' => 1,
				'interval' => 60,
			);
			$processList['mttIntoMatch'] = array(
				'method' => 'oo::robot()->mttIntoMatch();',
				'cnt' => 1,
				'interval' => 1,
			);			
		}
		if(oo::$config['sngHotColdPeriodControl']){
			$processList['sngRobotColdHotControl'] = array(
				'method' => 'oo::robot()->sngRobotColdHotControl();',
				'cnt' => 1,
				'interval' => 300,
			);
		}
		if(oo::$config['OpenSwooleCCgateV2']){			
			$processList['ccgatestate'] = array(
				'method' => 'oo::ccgatev2()->saveSvrState();',
				'cnt' => 1,
				'interval' => 300,
				'includeSid' => array(13, 93, 117, 57, 67, 103, 110, 124, 144),
			);
		}
		if(oo::$config['vvipSwitch_V2']){
			$processList['makePlogCache'] = array(
				'method' => 'oo::vvipext()->makePlogCache();',
				'cnt' => 1,
				'interval' => 300,
			);
 			$processList['clearRedutbl'] = array(
				'method' => 'oo::vvipext()->clearRedutbl();',
				'cnt' => 1,
				'interval' => 300,
			);          
		}
		if(oo::$config['liveStreamSwitch']){
			$processList['cronUpdateBlackList'] = array(
				'method' => 'oo::livestreamdata()->cronUpdateBlackList();',
				'cnt' => 1,
				'interval' => 10,
                'includeSid' => array(117,93),
			);        
		}      
        
		if($online){//内网不检测
			$processList['checkfbstatus'] = array(//检测sver
				'method' => 'oo::monitor()->getFBPlatStatus();',
				'cnt' => 1,
				'interval' => 600,
				'excludeSid' => array(93,117)
			);
		}
		return self::filter($processList, $sid);
	}

	/**
	 * 从服务器任务列表
	 * @return type
	 */
	public static function getSlaveRunList($sid, $online) {
		//if (!$online) {
			//内网机器问题不处理业务
			//return array();
		//}
		//method:要运行的方法名，
		//cnt:最大并发执行数  
		//interval:每隔n秒触发一次，
		//workerid:只在指定 workid进程上执行，不填表所有work进程上都触发
		//excludeSid array 排除的站点
		//includeSid array 包含的站点
		//switch 表示必须开启某个开关，oo::$config['']
		$processList['AsyncCall'] = array(
			'method' => 'Mod\Base\AsyncCall::exec();',
			'cnt' => in_array($sid, array(93)) ? 1 : 2,
			'interval' => 1
		);
		if(oo::$config['sid'] == 13){
			$processList['AsyncCall']['cnt'] = 3;
		}
		$processList['doUdp'] = array(
			'method' => 'oo::udp()->doUdp();',
			'cnt' => in_array($sid, array(93)) ? 1 : 2,
			'interval' => 1,
			//'includeSid' => array(13, 57, 93, 117),
		);

		$processList['doproc0'] = array(
			'method' => 'oo::proc()->doProc(0);',
			'cnt' => in_array($sid, array(93)) ? 1 : 2,
			'interval' => 1
		);
		$processList['doproc1'] = array(
			'method' => 'oo::proc()->doProc(1);',
			'cnt' => in_array($sid, array(93)) ? 1 : 2,
			'interval' => 1,
			'switch'=>'newhall'
		);
		$processList['dolist'] = array(
			'method' => 'oo::notice()->doList();',
			'cnt' => ($sid == 117) ? 2 : 1,
			'interval' => 1,
		);
		$processList['doproc109'] = array(
			'method' => 'oo::proc()->doProc(109);',
			'cnt' => in_array($sid, array(93)) ? 1 : 2,
			'interval' => 1
		);
		$processList['SaveMonitorInfoToLocal'] = array(
			'method' => 'SwooleModelCrontab::SaveMonitorInfoToLocal();',
			'cnt' => 1,
			'interval' => 60,
			'workid' => 1
		);
		$processList['mttback'] = array(
			'method' => 'oo::swooleMtt()->doMttBack();',
			'cnt' => 1,
			'interval' => 0.5,
			'includeSid' => array(101, 104, 143)
		);			
		$processList['cmscrontab_run'] = array(
			'method' => 'oo::cmscrontab()->dorun();',
			'cnt' => 1,
			'interval' => 20
		);
		$processList['dotableslog'] = array(
			'method' => 'oo::tablesloganalyze()->run();',
			'cnt' => oo::$config['sid']==117 ? 5 : 3,
			'interval' => 1,
		);
		$processList['monitor'] = array(
			'method' => 'oo::monitor()->monitor();',
			'cnt' => 1,
			'interval' => 60,
		);
		$processList['delUploadShareImg'] = array(
				'method' => 'oo::logs()->delUploadShareImg();',
				'cnt' => 1,
				'interval' => 3600,
				'switch'=>'mttAccountOpt'
		);
		return self::filter($processList, $sid);
	}

	private static function filter($processList, $sid) {
		$processListData = array();
		foreach ($processList as $key => $process) {
			if (isset($process['includeSid']) && (!empty($process['includeSid'])) && (!in_array($sid, $process['includeSid']))) {
				continue;
			}
			if (isset($process['excludeSid']) && (!empty($process['excludeSid'])) && (in_array($sid, $process['excludeSid']))) {
				continue;
			}
			$process['interval'] = isset($process['interval']) ? $process['interval'] : 5;
			$process['interval'] = $process['interval'] * 1000;
			if (!PRODUCTION_SERVER) {
				if ($key != 'SaveMonitorInfoToLocal') {
					//$process['interval'] = $process['interval'] * 5;
				}
			}
			if (!($process['cnt'] && $process['method'])) {
				continue;
			}
			if (isset($process['switch']) && !oo::$config[$process['switch']]) {
				continue;
			}
			$process['method'] = trim($process['method']);
			if (substr($process['method'], -1) != ';') {
				$process['method'].= ';';
			}
			if (!isset($process['workid'])) {
				$process['workid'] = 0;
			}
			$processListData[$key] = $process;
		}
		return $processListData;
	}

}
