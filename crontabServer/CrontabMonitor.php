<?php

class SwooleCrontabMonitor extends SwooleMonitorBase {

	private $SwooleModel;

	public function __construct() {
		$this->SwooleModel = oo::swoolecrontab();
		$this->SwooleName = 'CrontabService';
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
			return '201704191';
		}
		$killVers = array(
			110=>'2017032700',
			57=>'2017032700',
			117 =>'2017042614',
			93 =>'2017032700',
			13 =>'2017032700',
			67 =>'2017032700',
		);
		if(isset($killVers[oo::$config['sid']])){
			return $killVers[oo::$config['sid']];
		}
		return '2017032700';
	}

}
