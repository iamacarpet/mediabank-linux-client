<?php

$cur = array();

class Downloader {
	
	function Downloader(){
		
	}
	
	function startService(){
		global $mediaSync;
		
		while (1) {
			if ( $this->checkQueue() ){
                setproctitle('mediasync_downloaderd: Checked Queue at ' . date('g:i:s A'));
				continue;
			} else {
                setproctitle('mediasync_downloaderd: Checked Queue at ' . date('g:i:s A'));
				sleep(300);
			}
		}
	}
	
	function sanitize($string = '', $is_filename = FALSE){
		// Replace all weird characters with dashes
		$string = preg_replace('/[^a-zA-Z0-9 ]+/', '-', $string);

		// Only allow one dash separator at a time (and make string lowercase)
		return preg_replace('/--+/u', '-', $string);
	}
	
	function checkQueue(){
		global $mediaSync;
        
        setproctitle('mediasync_downloaderd: Checking Queue');
		
		$xml = new SimpleXMLElement('<?xml version="1.0" encoding="UTF-8"?><queue></queue>');
		
		$xml->addChild('serverAuth', $mediaSync->config->global->serverKey);
		
		$xml->addChild('type', 'get');
		
		libxml_use_internal_errors(true);
		$response = simplexml_load_string($mediaSync->func->doPost('http://' . $mediaSync->config->global->serverIP . ':' . $mediaSync->config->global->serverPort . '/api/queue', array('xml' => $xml->asXML()) ));
		if (!$response){
			$mediaSync->err->logError('Unable to check queue - Connection Problem', 'mediasync_downloaderd');
			return false;
		} else {
			if ($response->status['code'] != '200'){
				$mediaSync->err->logError('Invalid XML Response - ' . $response->asXML(), 'mediasync_downloaderd');
				return false;
			} else {
				if ((int)$response->items > 0){
					if ($response->item['type'] == 'tv'){
						$this->markAsStarted('tv', $response->item->queueID);
						if ($this->downloadTV($response)){
							$this->markAsFinished('tv', $response->item->queueID);
							return true;
						} else {
							return false;
						}
					} else if ($response->item['type'] == 'movie'){
						$this->markAsStarted('movie', $response->item->queueID);
						if ($this->downloadMovie($response)){
							$this->markAsFinished('movie', $response->item->queueID);
							return true;
						} else {
							return false;
						}
					} else if ($response->item['type'] == 'music'){
						$this->markAsStarted('music', $response->item->queueID);
						if ($this->downloadMusic($response)){
							$this->markAsFinished('music', $response->item->queueID);
							return true;
						} else {
							return false;
						}
					} else {
						return false;
					}
				} else {
					return false;
				}
			}
		}
	}
	
	function downloadTV($xml){
		global $mediaSync;
		
		
		// Check we have the Show folder...
		if ( ! is_dir($mediaSync->config->storage->tvStore . '/' . $this->sanitize($xml->item->tv->show)) ){
			mkdir($mediaSync->config->storage->tvStore . '/' . $this->sanitize($xml->item->tv->show));
		}
		
		// Check we have the Season folder...
		if ( ! is_dir($mediaSync->config->storage->tvStore . '/' . $this->sanitize($xml->item->tv->show) . '/Season ' . $this->sanitize($xml->item->tv->season)) ){
			mkdir($mediaSync->config->storage->tvStore . '/' . $this->sanitize($xml->item->tv->show) . '/Season ' . $this->sanitize($xml->item->tv->season));
		}
		
		// Now we'll build the filename, what we're going to save it as...
		$filename = $mediaSync->config->storage->tvStore . '/' . $this->sanitize($xml->item->tv->show) . '/Season ' . $this->sanitize($xml->item->tv->season) . '/';
		$filename .= $this->sanitize($xml->item->tv->show) . ' - S';
		if ((int)$xml->item->tv->season < 10){
			$filename .= '0';
		}
		$filename .= $xml->item->tv->season . 'E';
		if ((int)$xml->item->tv->episode < 10){
			$filename .= '0';
		}
		$filename .= $xml->item->tv->episode . ' - ' . $this->sanitize($xml->item->tv->name, false) . '.' . $xml->item->server->fileExtension;
		
		// Now we'll build the URL to download from...
		
		$url = 'http://' . $xml->item->server->ip . ':' . $xml->item->server->port . '/get?id=' . $xml->item->server->fileID . '&authcode=' . $xml->item->server->authCode;
		
		if ( ! $this->downloadFile($url, $filename) ){
			$mediaSync->err->logError('Download TV Error - Queue ID: ' . $xml->item->queueID, 'mediasync_downloaderd');
			return false;
		} else {
			return true;
		}
	}
	
	function downloadMovie($xml){
		global $mediaSync;
		
		// Now we build the filename...
		$filename = $mediaSync->config->storage->movieStore . '/' . $this->sanitize($xml->item->movie->name, false) . ' (' . $xml->item->movie->year . ').' . $xml->item->server->fileExtension;
		
		// Now we build the URL to download from...
		$url = 'http://' . $xml->item->server->ip . ':' . $xml->item->server->port . '/get?id=' . $xml->item->server->fileID . '&authcode=' . $xml->item->server->authCode;
		
		if ( ! $this->downloadFile($url, $filename) ){
			$mediaSync->err->logError('Download Movie Error - Queue ID: ' . $xml->item->queueID, 'mediasync_downloaderd');
			return false;
		} else {
			return true;
		}
	}
	
	function downloadMusic($xml){
		global $mediaSync;
		
		// Check we have the Show folder...
		if ( ! is_dir($mediaSync->config->storage->musicStore . '/' . $this->sanitize($xml->item->music->artist)) ){
			mkdir($mediaSync->config->storage->musicStore . '/' . $this->sanitize($xml->item->music->artist));
		}
		
		// Check we have the Season folder...
		if ( ! is_dir($mediaSync->config->storage->musicStore . '/' . $this->sanitize($xml->item->music->artist) . '/' . $this->sanitize($xml->item->music->album)) ){
			mkdir($mediaSync->config->storage->musicStore . '/' . $this->sanitize($xml->item->music->artist) . '/' . $this->sanitize($xml->item->music->album));
		}
		
		// Now we'll build the filename...
		$filename = $mediaSync->config->storage->musicStore . '/' . $this->sanitize($xml->item->music->artist) . '/' . $this->sanitize($xml->item->music->album) . '/';
		if ((int)$xml->item->music->track < 10){
			$filename .= "0";
		}
		$filename .= $xml->item->music->track . ' - ' . $this->sanitize($xml->item->music->song, false) . '.' . $xml->item->server->fileExtension;
		
		// Build the URL to download from...
		$url = 'http://' . $xml->item->server->ip . ':' . $xml->item->server->port . '/get?id=' . $xml->item->server->fileID . '&authcode=' . $xml->item->server->authCode;
		
		if ( ! $this->downloadFile($url, $filename) ){
			$mediaSync->err->logError('Download Music Error - Queue ID: ' . $xml->item->queueID, 'mediasync_downloaderd');
			return false;
		} else {
			return true;
		}
	}
	
	function downloadFile($url, $fileName){
		$from = 0;

		$ch = curl_init();
		
		curl_setopt($ch, CURLOPT_URL, $url);

		if (file_exists($fileName)) {
			$from = filesize($fileName);
			curl_setopt($ch, CURLOPT_RANGE, $from . "-");
		}

		$fp = fopen($fileName, "a");

		if (!$fp) {
			return false;
		}
		
		if (flock($fp, LOCK_EX)){
			curl_setopt($ch, CURLOPT_FILE, $fp);
            
            curl_setopt($ch, CURLOPT_NOPROGRESS, false);
            curl_setopt($ch, CURLOPT_PROGRESSFUNCTION, 'markProgress');

			$result = curl_exec($ch);
		
			curl_close($ch);
			flock($fp, LOCK_UN);
		} else {
			return false;
		}
		fclose($fp);

		return true;
	}
	
	function markAsStarted($type, $queueID){
		global $mediaSync, $cur;
		
		$xml = new SimpleXMLElement('<?xml version="1.0" encoding="UTF-8"?><queue></queue>');
		
		$xml->addChild('serverAuth', $mediaSync->config->global->serverKey);
		
		$xml->addChild('type', 'update');
		
		$update = $xml->addChild('update');
		
		$update->addChild('type', $type);
		$update->addChild('queueID', $queueID);
		$update->addChild('started', "1");
		$update->addChild('completed', "0");
		$update->addChild('statusCode', "0");
        
        $cur['type'] = $type;
        $cur['queueID'] = $queueID;
        $cur['percent'] = 0;
        
        setproctitle('mediasync_downloaderd: Downloading File 0%');
		
		libxml_use_internal_errors(true);
		$response = simplexml_load_string($mediaSync->func->doPost('http://' . $mediaSync->config->global->serverIP . ':' . $mediaSync->config->global->serverPort . '/api/queue', array('xml' => $xml->asXML()) ));
		if (!$response){
			$mediaSync->err->logError('Unable to mark download as started - Connection Issue', 'mediasync_downloaderd');
			return false;
		} else {
			if ($response->status['code'] != '200'){
				$mediaSync->err->logError('Unable to mark download as started - Invalid XML - ' . $response->asXML(), 'mediasync_downloaderd');
				return false;
			}
		}
	}
	
	function markAsFinished($type, $queueID){
		global $mediaSync, $cur;
		
		$xml = new SimpleXMLElement('<?xml version="1.0" encoding="UTF-8"?><queue></queue>');
		
		$xml->addChild('serverAuth', $mediaSync->config->global->serverKey);
		
		$xml->addChild('type', 'update');
		
		$update = $xml->addChild('update');
		
		$update->addChild('type', $type);
		$update->addChild('queueID', $queueID);
		$update->addChild('started', "1");
		$update->addChild('completed', "1");
		$update->addChild('statusCode', "100");
        
        $cur = array();
		
		libxml_use_internal_errors(true);
		$response = simplexml_load_string($mediaSync->func->doPost('http://' . $mediaSync->config->global->serverIP . ':' . $mediaSync->config->global->serverPort . '/api/queue', array('xml' => $xml->asXML()) ));
		if (!$response){
			$mediaSync->err->logError('Unable to mark download as complete - Connection Issue', 'mediasync_downloaderd');
			return false;
		} else {
			if ($response->status['code'] != '200'){
				$mediaSync->err->logError('Unable to mark download as complete - Invalid XML - ' . $response->asXML(), 'mediasync_downloaderd');
				return false;
			}
		}
	}
}

function markProgress($download_size, $downloaded, $upload_size, $uploaded){
    global $mediaSync, $cur;
    
    if ($downloaded < 1){
        $percent = 0;
    } else {
        $percent = ($downloaded/$download_size)*100;
    }
    
    //$mediaSync->err->logError($percent);
    
    $type = $cur['type'];
    $queueID = $cur['queueID'];
    
    if (round($percent) > $cur['percent']){
        setproctitle('mediasync_downloaderd: Downloading File ' . round($percent) . '%');
    
        $xml = new SimpleXMLElement('<?xml version="1.0" encoding="UTF-8"?><queue></queue>');
    
	    $xml->addChild('serverAuth', $mediaSync->config->global->serverKey);
	
	    $xml->addChild('type', 'update');
	   
	    $update = $xml->addChild('update');
	
    	$update->addChild('type', $type);
    	$update->addChild('queueID', $queueID);
    	$update->addChild('started', "1");
    	$update->addChild('completed', "0");
    	$update->addChild('statusCode', $percent);
        
        $cur['percent'] = round($percent);
	
	    libxml_use_internal_errors(true);
    	$response = simplexml_load_string($mediaSync->func->doPost('http://' . $mediaSync->config->global->serverIP . ':' . $mediaSync->config->global->serverPort . '/api/queue', array('xml' => $xml->asXML()) ));
    	if (!$response){
    		$mediaSync->err->logError('Unable to mark download progress - Connection Issue', 'mediasync_downloaderd');
    		return false;
    	} else {
    		if ($response->status['code'] != '200'){
    			$mediaSync->err->logError('Unable to mark download progress - Invalid XML - ' . $response->asXML(), 'mediasync_downloaderd');
    			return false;
    		}
    	}
    }
}

?>