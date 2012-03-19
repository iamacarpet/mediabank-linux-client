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
	var $version = '0.2.5';
    var $run = true;
    var $pid = array();
	function mediaSync(){
		$this->func = new Functions;
		$this->err = new Errors();
		
		// Grab our config from XML
		if (file_exists('/etc/mediasync/config.xml')) {
			$this->config = simplexml_load_file('/etc/mediasync/config.xml');
		} else {
			die('Failed to open config.xml');
		}
		// Check our config is valid...
		$this->config = $this->func->checkConfig($this->config);
		
	}
    
    function startCheckIn(){
        global $mediaSync;
        
        $checkin_pid = pcntl_fork();
        if ($checkin_pid == -1) {
            die('could not fork checkin');
        } else if (!$checkin_pid) {
            setproctitle('mediasync-checkind: Starting Up');
            // we are the child, do stuff!
            require "includes/checkin.php";

            $mediaSync->checkin = new CheckIn();
            $mediaSync->checkin->startService();
        } else {
	        // we are the parent - write the pid
            $fp = fopen('/var/run/mediasync/checkin.pid', 'w');
            fwrite($fp, $checkin_pid);
            fclose($fp);
            $this->pid['check'] = $checkin_pid;
        }
    }
    
    function startWebserver(){
        global $mediaSync;
        
        $webserver_pid = pcntl_fork();
        if ($webserver_pid == -1) {
            die('could not fork');
        } else if (!$webserver_pid) {
            setproctitle('mediasync-webserverd: Starting Up');
            // we are the child, do stuff!
            require "includes/webserver.php";
	
            $mediaSync->websvr = new WebServer();
            $mediaSync->websvr->StartService();
        } else {
            // we are the parent - write the pid
            $fp = fopen('/var/run/mediasync/webserver.pid', 'w');
            fwrite($fp, $webserver_pid);
            fclose($fp);
            $this->pid['web'] = $webserver_pid;
        }
    }
    
    function startDownloader(){
        global $mediaSync;
    
        $downloader_pid = pcntl_fork();
        if ($downloader_pid == -1) {
            die('could not fork');
        } else if (!$downloader_pid) {
        	setproctitle('mediasync_downloaderd: Starting Up');
        	// we are the child, do stuff!
        	require "includes/downloader.php";
	
        	$mediaSync->dl = new Downloader();
        	$mediaSync->dl->StartService();
        } else {
        	// we are the parent - write the pid
        	$fp = fopen('/var/run/mediasync/downloader.pid', 'w');
        	fwrite($fp, $downloader_pid);
        	fclose($fp);
            $this->pid['dl'] = $downloader_pid;
        }
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
$mediaSync->startCheckIn();

//------------------------------
// Create The Web-Server Child
//------------------------------
$mediaSync->startWebserver();

//------------------------------
// Create The Downloader Child
//------------------------------
$mediaSync->startDownloader();

//------------------------------
// Watch for the services exiting.
//------------------------------

setproctitle('mediasyncd');

while ($mediaSync->run){
    if (! posix_getsid($mediaSync->pid['check'])){
        // Log the error.
        // -- This is where we would submit there error in the PHP log file out to our server, then clean it.
        $mediaSync->err->logError('The checkin service has crashed - Performing restart.', 'mediasyncd');
        // Checkin Service is in error and has exited - Restart.
        $mediaSync->startCheckIn();
    }
    if (! posix_getsid($mediaSync->pid['web'])){
        // Log the error.
        // -- This is where we would submit there error in the PHP log file out to our server, then clean it.
        $mediaSync->err->logError('The web server service has crashed - Performing restart.', 'mediasyncd');
        // Web server service is in error and has exited - Restart.
        $mediaSync->startWebserver();
    }
    if (! posix_getsid($mediaSync->pid['dl'])){
        // Log the error.
        // -- This is where we would submit there error in the PHP log file out to our server, then clean it.
        $mediaSync->err->logError('The downloader service has crashed - Performing restart.', 'mediasyncd');
        // Downloader service is in error and has exited - Restart.
        $mediaSync->startDownloader();
    }
    sleep(5);
}

//------------------------------
// Finish up...
//------------------------------

?>