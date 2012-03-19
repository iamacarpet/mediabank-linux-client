<?php

class Functions {
	
	function checkConfig($xml){
		
        if ( (int)$xml->global->configured != 1 ){
            die('MediaSync is Not Configured');
        }
        
		return $xml;
	}
	
	function doPost($url, $data, $optional_headers = null){
		$params = array('http' => array( 'method' => 'POST', 'content' => http_build_query($data)));
		
		if ($optional_headers !== null){
			$params['http']['header'] = $optional_headers;
		}
		
		$ctx = stream_context_create($params);
		
		$fp = @fopen($url, 'rb', false, $ctx);
		
		if (!$fp) {
			return 0;
		}
		
		$response = @stream_get_contents($fp);
		
		if ($response === false) {
			return 0;
		}
		
		return $response;
	}
    
}

?>