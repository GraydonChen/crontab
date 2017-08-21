<?php

class SwooleQueneMonitor  extends SwooleMonitorBase {
	private $SwooleModel;

	public function __construct() {
		$this->SwooleModel = oo::swoolequene();
		$this->SwooleName = 'QueneService';
		$this->SwooleTcpPort = $this->SwooleModel->SwooleTcpPort;
		$this->SwooleUdpPort = $this->SwooleModel->SwooleUdpPort;
	}

	public function TestSwooleIsWorking() {
		return $this->SwooleModel->TestSwooleIsWorking();
	}

	public function ReloadSwooleByTcp() {
		return $this->SwooleModel->ReloadSwooleByTcp();
	}

	public function getKillVer() {
		if(!PRODUCTION_SERVER){
			return '201707241';
		}
		//杀进程版本,强制杀进程可能会造成运行中的程序中断导致业务数据丢失		
		$killVers = array(
			110=>'20170301',
			117 =>'2017042614',
			93 =>'20170301',
			101=> '20170301',
			104=> '20170301',
			143=> '20170301',
		);
		if(isset($killVers[oo::$config['sid']])){
			return $killVers[oo::$config['sid']];
		}
		return '20170426';
	}
}
