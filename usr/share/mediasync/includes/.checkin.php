<?php

class CheckIn {
	var $init = 0;
	
	var $load1min;
	var $load5min;
	var $load15min;
	
	var $freespace;
	var $totalspace;
	
	function CheckIn(){
		$this->init = 1;
	}
	
	function startService(){
		global $mediaSync;
		
		while (1){
			$this->performCheckIn();
			
			sleep((int)$mediaSync->config->checkIn->everySeconds);	
		}
	}
	
	function performCheckIn(){
		global $mediaSync;
		// Build our response...
		
		$xml = new SimpleXMLElement('<?xml version="1.0" encoding="UTF-8"?><checkin></checkin>');
		
		$xml->addChild('serverAuth', $mediaSync->config->global->serverKey);
		$disk = $xml->addChild('disk');
		$this->processDisk();
		$disk->addChild('freeSpace', $this->freespace);
		$disk->addChild('totalSpace', $this->totalspace);
		$disk->addChild('percentFull', $this->getPercentFull());
		
		$server = $xml->addChild('server');
		$server->addChild('uptime', $this->getUptime());
		
		$load = $server->addChild('load');
		$this->processLoad();
		$load->addChild('load1min', $this->load1min);
		$load->addChild('load5min', $this->load5min);
		$load->addChild('load15min', $this->load15min);
		
		$inet = $xml->addChild('internet');
		$inet->addChild('ip', $this->getInternetIP());
		$inet->addChild('internalip', $this->getInternalIP());
		
		libxml_use_internal_errors(true);
		$response = simplexml_load_string($mediaSync->func->doPost('http://' . $mediaSync->config->global->serverIP . ':' . $mediaSync->config->global->serverPort . '/api/checkin', array('xml' => $xml->asXML()) ));
		if (!$response){
			$mediaSync->err->logError('CheckIn Service Error: Unable to make connection...');
		} else {
			if ($response->status['code'] != '200'){
				$mediaSync->err->logError('CheckIn Service Error: ' . $response->asXML());
			}
		}
	}
	
	//------------------------------
	// Disk Stuff...
	//------------------------------
	
	function processDisk(){
		global $mediaSync;
		
		$this->freespace = disk_free_space($mediaSync->config->storage->mediaBase);
		$this->totalspace = disk_total_space($mediaSync->config->storage->mediaBase);
	}
	
	function getPercentFull(){
		return round((100 - (( $this->freespace / $this->totalspace ) * 100 )),2);
	}
	
	//------------------------------
	// Server Stuff
	//------------------------------
	
	function getUptime(){
		$fp = fopen('/proc/uptime', 'r');
		
		$line = fread($fp, 128);
		
		fclose($fp);
		
		$uptime = explode(' ', $line);
		
		return $uptime[0];
	}
	
	function processLoad(){
		$fp = fopen('/proc/loadavg', 'r');
		
		$line = fread($fp, 128);
		
		fclose($fp);
		
		$load = explode(' ', $line);
		
		$this->load1min = $load[0];
		$this->load5min = $load[1];
		$this->load15min = $load[2];
	}
	
	//------------------------------
	// Internet Stuff
	//------------------------------
	
	function getInternetIP(){
		$fp = fopen('http://sassybox.net/ip.php', 'r');
		
		$line = fread($fp, 128);
		
		return $line;
	}
	
	function getInternalIP(){
		$output = shell_exec('ifconfig');
		
		preg_match_all('/inet addr: ?([^ ]+)/', $output, $ips);
		
		return $ips[1][0];
	}
}

?>