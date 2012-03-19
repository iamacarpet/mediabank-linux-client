<?php

set_time_limit(0);

//------------------------------
// Setup our main class...
//------------------------------

require "includes/functions.php";
require "includes/errors.php";

class mediaSync {
	var $func;
	var $err;
	var $config;
	var $dl;
	var $checkin;
	var $websvr;
	function mediaSync(){
		$this->func = new Functions;
		$this->err = new Errors();
		
		// Grab our config from XML
		if (file_exists('/etc/mediasync/config.xml')) {
			$this->config = simplexml_load_file('/etc/mediasync/config.xml');
		} else {
			exit('Failed to open config.xml');
		}
		// Check our config is valid...
		$this->config = $this->func->checkConfig($this->config);
		
	}
}

$mediaSync = new mediaSync();

if (! is_dir('/var/run/mediasync')){
	mkdir('/var/run/mediasync');
}

//------------------------------
// Setup our includes...
//------------------------------
ini_set('include_path', '/usr/share/mediasync/libs/PEAR/:' . ini_get("include_path"));

//------------------------------
// Create The Check-In Child
//------------------------------
$checkin_pid = pcntl_fork();
if ($checkin_pid == -1) {
	die('could not fork checkin');
} else if (!$checkin_pid) {
	setproctitle('mediasync-checkind');
	// we are the child, do stuff!
	require "includes/checkin.php";
	 
	$mediaSync->checkin = new CheckIn();
	$mediaSync->checkin->startService();
} else {
	// we are the parent - write the pid
	$fp = fopen('/var/run/mediasync/checkin.pid', 'w');
	fwrite($fp, $checkin_pid);
	fclose($fp);
}

//------------------------------
// Create The Web-Server Child
//------------------------------
$webserver_pid = pcntl_fork();
if ($webserver_pid == -1) {
	die('could not fork');
} else if (!$webserver_pid) {
	setproctitle('mediasync-webserverd');
	// we are the child, do stuff!
	require "includes/webserver.php";
	
	$mediaSync->websvr = new WebServer();
	$mediaSync->websvr->StartService();
} else {
	// we are the parent - write the pid
	$fp = fopen('/var/run/mediasync/webserver.pid', 'w');
	fwrite($fp, $webserver_pid);
	fclose($fp);
}

//------------------------------
// Create The Downloader Child
//------------------------------
$downloader_pid = pcntl_fork();
if ($downloader_pid == -1) {
	die('could not fork');
} else if (!$downloader_pid) {
	setproctitle('mediasync_downloaderd');
	// we are the child, do stuff!
	require "includes/downloader.php";
	
	$mediaSync->dl = new Downloader();
	$mediaSync->dl->StartService();
} else {
	// we are the parent - write the pid
	$fp = fopen('/var/run/mediasync/downloader.pid', 'w');
	fwrite($fp, $downloader_pid);
	fclose($fp);
}

//------------------------------
// Finish up...
//------------------------------
//pcntl_wait($status); //Protect against Zombie children

?>