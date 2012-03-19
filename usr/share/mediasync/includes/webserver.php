<?php

require_once 'HTTP/Server.php';

class HTTP_Server_File extends HTTP_Server {

	function GET($clientId, &$request){	
		$path_info       = $request->getPathInfo();
		
		$headers = array();
		
		$get = array();
		parse_str($request->query_string, $get);
		
		if ($path_info == '/browse'){
			return $this->doBrowse($get);
		} else if ($path_info == '/delete'){
			return $this->doDelete($get);
		} else {
			return array( "code" => 404, "headers" => array('Content-type' => 'text/xml'), "body" => "<?xml version=\"1.0\" encoding=\"UTF-8\"?><webserver><status code='404'><message>File Not Found</message></status></webserver>" );
		}
		
		return array( "code" => 200, "headers" => array('Content-type' => 'text/xml'), "body" => "<?xml version=\"1.0\" encoding=\"UTF-8\"?><webserver><status code='403'><message>Access Denied</message></status></webserver>" );
    }
	
	function doBrowse($get){
		global $mediaSync;
		
		libxml_use_internal_errors(true);
		$xml = simplexml_load_string($get['xml']);
		if (!$xml){
			return array( "code" => 200, "headers" => array('Content-type' => 'text/xml'), "body" => "<?xml version=\"1.0\" encoding=\"UTF-8\"?><webserver><status code='406'><message>Invalid XML Request</message></status></webserver>" );
		}
		
		if ( ! $xml->clientAuth == $mediaSync->config->global->clientKey){
			return array( "code" => 200, "headers" => array('Content-type' => 'text/xml'), "body" => "<?xml version=\"1.0\" encoding=\"UTF-8\"?><webserver><status code='407'><message>Access Denied - Invalid Client Key</message></status></webserver>" );
		}
		
		if ($xml->type == 'tv'){
			$folder = $mediaSync->config->storage->tvStore . '/';
		} else if ($xml->type == 'movie'){
			$folder = $mediaSync->config->storage->movieStore . '/';
		} else if ($xml->type == 'music'){
			$folder = $mediaSync->config->storage->musicStore . '/';
		} else {
			return array( "code" => 200, "headers" => array('Content-type' => 'text/xml'), "body" => "<?xml version=\"1.0\" encoding=\"UTF-8\"?><webserver><status code='408'><message>Invalid Media Browse Type</message></status></webserver>" );
		}
		
		$folders = explode("/", $xml->directory);
		$if = 0;
		$cf = count($folders);
		foreach ($folders as $f){
			$if++;
			if ($f == '..'){
				continue;
			} else if ($f == '.'){
				continue;
			} else {
				$folder .= $f;
				if ($if != $cf){
					$folder .= '/';
				}
			}
		}
		
		$rxml = new SimpleXMLElement("<?xml version=\"1.0\" encoding=\"UTF-8\"?><browse><status code='200'><message>OK</message></status></browse>");
		
		$listing = $rxml->addChild('listing');
		$files = array();
		$dirs = array();
		if (is_dir($folder)){
			if ($dh = opendir($folder)){
				while (($file = readdir($dh)) !== false) {
					if ($file == '.' || $file == '..'){
						continue;
					}
					if ( filetype($folder . '/' . $file) == 'dir'){
						$dirs[] = $file;
					} else {
						$files[] = $file;
					}
				}
				closedir($dh);
				natsort($dirs);
				natsort($files);
				foreach($dirs as $dir){
					$dxml = $listing->addChild('directory');
                                      	$dxml->addAttribute('name', $dir);
				}
				foreach($files as $file){
					$fxml = $listing->addChild('file');
					$fxml->addAttribute('name', $file);
                                        $fxml->addAttribute('size', filesize($folder . '/' . $file));
                                        $fxml->addAttribute('mdate', filemtime($folder . '/' . $file));
				}
			} else {
				return array( "code" => 200, "headers" => array('Content-type' => 'text/xml'), "body" => "<?xml version=\"1.0\" encoding=\"UTF-8\"?><webserver><status code='500'><message>Can't Open Directory</message></status></webserver>" );
			}
		} else {
			return array( "code" => 200, "headers" => array('Content-type' => 'text/xml'), "body" => "<?xml version=\"1.0\" encoding=\"UTF-8\"?><webserver><status code='500'><message>Not A Directory</message></status></webserver>" );
		}
		
		return array( "code" => 200, "headers" => array('Content-type' => 'text/xml'), "body" => $rxml->asXML() );
	}
	
	function doDelete($get){
		global $mediaSync;
		
		libxml_use_internal_errors(true);
		$xml = simplexml_load_string($get['xml']);
		if (!$xml){
			return array( "code" => 200, "headers" => array('Content-type' => 'text/xml'), "body" => "<?xml version=\"1.0\" encoding=\"UTF-8\"?><webserver><status code='403'><message>Invalid XML Request</message></status></webserver>" );
		}
		
		if ( ! $xml->clientAuth == $mediaSync->config->global->clientKey){
			return array( "code" => 200, "headers" => array('Content-type' => 'text/xml'), "body" => "<?xml version=\"1.0\" encoding=\"UTF-8\"?><webserver><status code='403'><message>Access Denied - Invalid Client Key</message></status></webserver>" );
		}
		
		if ($xml->from == 'tv'){
			$folder = $mediaSync->config->storage->tvStore . '/';
		} else if ($xml->from == 'movie'){
			$folder = $mediaSync->config->storage->movieStore . '/';
		} else if ($xml->from == 'music'){
			$folder = $mediaSync->config->storage->musicStore . '/';
		} else {
			return array( "code" => 200, "headers" => array('Content-type' => 'text/xml'), "body" => "<?xml version=\"1.0\" encoding=\"UTF-8\"?><webserver><status code='403'><message>Invalid Media Browse Type</message></status></webserver>" );
		}
		
		if ($xml->type == 'file'){
			$folders = explode("/", $xml->file);
			$if = 0;
			$cf = count($folders);
			foreach ($folders as $f){
				$if++;
				if ($f == '..'){
					next;
				} else if ($f == '.'){
					next;
				} else {
					$folder .= $f;
					if ($if != $cf){
						$folder .= '/';
					}
				}
			}
			
			if (is_file($folder)){
				if ( unlink($folder) ){
					return array( "code" => 200, "headers" => array('Content-type' => 'text/xml'), "body" => "<?xml version=\"1.0\" encoding=\"UTF-8\"?><delete><status code='200'><message>OK</message></status></delete>" );
				} else {
					return array( "code" => 200, "headers" => array('Content-type' => 'text/xml'), "body" => "<?xml version=\"1.0\" encoding=\"UTF-8\"?><webserver><status code='500'><message>Can't delete file...</message></status></webserver>" );
				}
			} else {
				return array( "code" => 200, "headers" => array('Content-type' => 'text/xml'), "body" => "<?xml version=\"1.0\" encoding=\"UTF-8\"?><webserver><status code='500'><message>Invalid File</message></status></webserver>" );
			}
		} else if ($xml->type == 'folder'){
			$folders = explode("/", $xml->directory);
			
			foreach ($folders as $f){
				if ($f == '..'){
					next;
				} else if ($f == '.'){
					next;
				} else {
					$folder .= $f . '/';
				}
			}
			
			if (is_dir($folder)){
				if ($this->runlink($folder)){
					return array( "code" => 200, "headers" => array('Content-type' => 'text/xml'), "body" => "<?xml version=\"1.0\" encoding=\"UTF-8\"?><delete><status code='200'><message>OK</message></status></delete>" );
				} else {
					return array( "code" => 200, "headers" => array('Content-type' => 'text/xml'), "body" => "<?xml version=\"1.0\" encoding=\"UTF-8\"?><webserver><status code='500'><message>Can't delete folder...</message></status></webserver>" );
				}
			} else {
				return array( "code" => 200, "headers" => array('Content-type' => 'text/xml'), "body" => "<?xml version=\"1.0\" encoding=\"UTF-8\"?><webserver><status code='500'><message>Invalid Folder</message></status></webserver>" );
			}
		} else {
			return array( "code" => 200, "headers" => array('Content-type' => 'text/xml'), "body" => "<?xml version=\"1.0\" encoding=\"UTF-8\"?><webserver><status code='500'><message>Invalid Type</message></status></webserver>" );
		}
		
		return array( "code" => 200, "headers" => array(), "body" => '&nbsp;' );
	}
	
	function runlink($dir){
		if(!$dh = @opendir($dir)){
			return false;
		}
		while (false !== ($obj = readdir($dh))){
			if($obj == '.' || $obj == '..'){
				continue;
			}

			if (!@unlink($dir . '/' . $obj)){
				if ( ! $this->runlink($dir.'/'.$obj) ){
					return false;
				}
			}
		}

		closedir($dh);
   
		@rmdir($dir);
		
		return true;
	}
}

class WebServer {
	var $server;
	
	function WebServer(){
		global $mediaSync;
		
		$this->server = new HTTP_Server_File(0, (int)$mediaSync->config->webServer->port);
		$this->server->_driver->setDebugMode(false);
	}
	
	function startService(){
        setproctitle('mediasync-webserverd: Listening for Requests');
		$this->server->start();
	}
}

?>