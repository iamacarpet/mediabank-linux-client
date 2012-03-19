<?php

class Errors {
	var $fp;
	
	function Errors(){
        error_reporting(E_ALL);
        
        ini_set('display_errors', 'On');

        ini_set('error_log', '/var/log/mediasyncd.log.php-errors');
	}
	
	function logError($str = 'Error', $program = 'Unknown'){
		
		$str = date('d/m/y h:i:s A') . ' - ' . $program . ': ' . $str;
		
		$fp = fopen('/var/log/mediasync.log', 'a');
		
		while (! flock($fp, LOCK_EX) ){
			usleep(200);	
		}
		
		fwrite($fp, $str . "\n");
		
		flock($fp, LOCK_UN);
		
		fclose($fp);
	}
	
    function initErr($program = 'php'){
           ini_set('error_log', '/var/log/mediasync_' . $program . '.log');
    }
    
}

?>