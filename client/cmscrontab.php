<?php
/**
 * @Description crontab.php 定时任务
 * 
 * 	前5个字段分别表示：
  分钟：0-59
  小时：1-23
  日期：1-31
  月份：1-12
  星期：0-6（0表示周日）

  特殊符号：
 * ： 表示任何时刻
  ,：表示分割
  －：表示一个段，如小时：1-5，表示1到5点
  /n : 表示每个n的单位执行一次，如第二段里，* / 1, 就表示每隔1个小时执行一次命令。也可以写成1-23/1.

  （周）和（日月）都定义时，判断其中一对成立即可
  如：0 0 1,15 * 1		毎月1日和 15日和周日的 00:00 执行

  优化级 ',' '/' '-'：
  如小时：1,2,3,6-9/2 表示1、2、3、6、8点执行，并非1、3、5、6、7点执行

 * @Author AlenAl
 * @Date 2013-9-4
 */
defined( 'TSWOOLE' ) or die( 'Include Error!' );
class ModelCMSCrontab {

	const COMMA = ',';
	const STEP = '/';
	const PERIOD = '-';
	const ANY = '*';

	private $_type = array('i', 'H', 'd', 'm', 'w');
	private $_config = array(); //需要执行的任务配置，config/crontab/{sid}.php cms->系统->定时任务 生成即可
	
	public function __construct() {		
		$needstatsid = is_array(oo::$config['needstatsid']) && !empty(oo::$config['needstatsid']) ? oo::$config['needstatsid'] : array(oo::$config['sid']);
		foreach ($needstatsid as $needid) {
			$file = CFGROOT . 'crontab/' . $needid . '.php';
			if (!is_file($file))
				$file = PATH_CFG . "crontab/{$needid}.php"; //暂时兼容
			$config = file_exists($file) ? include( $file ) : array();
			!empty($config) && $this->_config += $config;
		}
	}
	/**
	 * @desc cms定时任务
	 * @param void
	 * @return true
	 */
	public function crontab() {
		$config = $this->_config;
		$time = time();
		$ret = array();
		foreach ($config as $id => $v) {
			$switch = trim($v['switch']);
			if ($v['endtime'] > 0 && $v['endtime'] < $time) {
				$ret[$id] = '结束时间小于当前时间';
				continue;
			}
			if (strlen($switch)) {//有定义开关
				$switchRet = array();
				$switchArray = explode(self::COMMA, $switch);
				foreach ((array) $switchArray as $switch) {
					$switchRet[] = oo::$config[$switch] ? true : false; //开了几个
				}
				if (!$v['relation'] && count($switchRet) != array_sum($switchRet)) {// 默认relation false表示与关系
					$ret[$id] = '某一个开关已关闭';
					continue;
				} else if (!empty($switchRet) && !array_sum($switchRet)) {
					$ret[$id] = '开关全部已关闭';
					continue;
				}
			}

			if (!$this->check($v['crontab'])) {//非执行点
				$ret[$id] = '非执行时间点';
				continue;
			}
			if (!functions::checkSyntaxBlock($v['code'])) {//语法失败
				oo::logs()->debug(date('Y-m-d H:i:s') . " CRONTAB ID:{$id}" . implode("\n", $v), 'phperror.txt');
				continue;
			}
			//放进队列			
			ocache::redisudp()->lPush(okey::mkudp('cmscrontab'), $id);
			$ret[$id] = '正常执行';
		}
		return true;
	}
	/**
	 * 执行
	 */
	public function dorun() {
		$i = 100;
		while ($i > 0) {
			$i--;
			$id = (string) ocache::redisudp()->rPop(okey::mkudp('cmscrontab'));
			if(!$id){
				break;
			}
			$cfg = $this->_config[$id];
			if ($cfg && $cfg['code']) {				
				$begin_microTime = microtime(true);
				$begin_usemem =memory_get_usage(1);
				eval($cfg['code']);					
				SwooleModelcrontab::SaveMonitorInfo($begin_microTime, "cms定时任务_" . $id,$begin_usemem);
			}
		}
	}

	/**
	 * @desc 核验
	 * @param String $string 5段（分时日月周）连接字符串
	 * @param String $split 分隔符,默认#
	 * @return Boolean/Int
	 */
    2#0#*#*#1
	private function check($string, $split = '#') {
		if (!strlen($string) || !is_string($string)) {
			return -100;
		}
		if (!strlen($split)) {
			return -101;
		}

		$cRet = array();
		foreach (explode($split, $string) as $k => $string) {
			$cRet[$this->_type[$k]] = $this->_analy($string, $this->_type[$k]);
			if ($cRet[$this->_type[$k]] < 0) {
				return false;
			}
		}
		$ret = $cRet['d'] && $cRet['m'] && $cRet['w'];
		if (( is_bool($cRet['d']) || is_bool($cRet['m']) ) && is_bool($cRet['w'])) {//如果都定义了，则（日月）（周）一对为true即可
			$ret = ($cRet['d'] && $cRet['m']) || $cRet['w'] ? true : $ret;
		}
		return $cRet['i'] && $cRet['H'] && $ret ? true : false;
	}

	/**
	 * @desc 单段分析
	 * @param String $string 单段
	 * @param String $type 分i 时h 日d 月m 周w
	 * @return Boolean/Int true通过
	 */
	private function _analy($string, $type) {
		if (!strlen($string) || !is_string($string)) {
			return -200;
		}
		if (!in_array($type, $this->_type, true)) {
			return -201;
		}

		$step = 0;
		$isANY = false;
		$validTimeBox = $commaTimeBox = array(); //有效时间

		if (strpos($string, self::COMMA) !== false) {//有‘,’号
			$commaTimeBox = explode(',', $string);
		} else {
			$commaTimeBox[] = $string;
		}

		foreach ((array) $commaTimeBox as $v) {
			$step = 0;

			if (strpos($v, self::STEP) !== false) {//有‘/’号
				list( $v, $step ) = explode(self::STEP, $v);
				$step = functions::uint($step);
			}

			if ($v === self::ANY) {//有*号
				$isANY = true;
				break;
			}

			$tmp = $step ? $step : 1;
			if (strpos($v, self::PERIOD) !== false) {//有‘-’号
				list( $start, $end ) = explode(self::PERIOD, $v);
				$min = min(array((int) $start, (int) $end));
				$max = max(array((int) $start, (int) $end));
				for ($min; $min <= $max; $min += $tmp) {
					$validTimeBox[] = (int) $min;
				}
			} else {
				$validTimeBox[] = (int) $v;
			}
		}

		$nowTime = (int) date($type);
		if ($isANY === true) {//有‘*’号
			if (!$step)
				return 1;
			return $nowTime % $step == 0 ? true : false;
		}else {
			$validTimeBox = array_unique($validTimeBox);
			return in_array($nowTime, $validTimeBox, true) ? true : false;
		}
		return false;
	}
}
