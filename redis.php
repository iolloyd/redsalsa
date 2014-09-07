<?php

define('NL', sprintf('%s%s', chr(13), chr(10)));

/**
 * Class Rdis
 */
class Rdis {

	protected $socket;
	protected $host;
	protected $port;

    /**
     * @param string $host
     * @param int $port
     */
    public function __construct($host='localhost', $port = 6379) {
		$this->host   = $host;
		$this->port   = $port;
		if (!$this->socket = fsockopen($this->host, $this->port, $errno, $errstr)) {
			throw new Exception("{$errno} - {$errstr}");
		}
	}

    /**
     *
     */
    public function __destruct() {
		fclose($this->socket);
	}

    /**
     * @param $name
     * @param $args
     * @return mixed
     * @throws Exception
     */
    public function __call($name, $args) {
		array_unshift($args, strtoupper($name));
		$cmd = sprintf('*%d%s%s%s', count($args), NL, $this->argsWithLengths($args), NL);
		$commandLength = strlen($cmd);
		for ($w = 0; $w < $commandLength; $w += $done) {
			$done = fwrite($this->socket, substr($cmd, $w));
			if ($done === FALSE) {
				throw new Exception('Failed to write entire command to stream');
			}
		}

		$response = $this->handleReply();
		return $response;
	}

    /**
     * @return mixed
     */
    protected function handleReply(){
		$reply = trim(fgets($this->socket, 512));
		$responses = array(
			'+' => 'replySingle' , '-' => 'replyError'     , ':' => 'replyInt' , 
			'$' => 'replyBulk'   , '*' => 'replyMultiBulk'
		);
		$response_code = substr($reply, 0, 1);
		try {
			$func = [$this, $responses[$response_code]];
			$response = call_user_func_array($func, [$reply]);
			return $response;
		} catch (Exception $e) {
			die("server response makes no sense to me: {$reply}");
		}
	}

	protected function replySingle($reply){
		$response = substr(trim($reply), 1);
		return $response;
	}

	protected function replyError($reply){
		throw new Exception(substr(trim($reply), 4));
	}

	protected function replyInt($reply){
		$response = intval(substr(trim($reply), 1));
		return $response;
	}

	protected function replyBulk($reply){
		$response = null;
		if ($reply == '$-1') {
			return null;
		}
		$response = $this->readSocketStream($reply);
		return $response;
	}

	protected function replyMultiBulk($reply){
		$count = substr($reply, 1);
		if ($count == '-1') {
			return null;
		}
		$response = array();
		for ($i = 0; $i < $count; $i++) {
			$read = trim(fgets($this->socket, 512));
			$size = substr($read, 1);
			$response[] = ($size == '-1') 
				? null 
				: $this->readSocketStream($read); 
		}
		return $this->asHashArray($response);
	}

	/*******************
	 * Utility methods *
	 *******************/

    /**
     * @param $args
     * @return string
     */
    protected function argsWithLengths($args){
		$cmd_parts = array_map(function($x){ 
			return sprintf("$%d%s%s", strlen($x), NL, $x); }, 
			$args
		);
		return implode(NL, $cmd_parts).NL;
	}

    /**
     * @param array $values
     * @return array
     */
    protected function asHashArray(array $values){
		$pairs = array_chunk($values, 2);
		$out   = array();
		foreach ($pairs as $pair){
			$out[$pair[0]] = $pair[1];
		}
		return $out;
	}

    /**
     * @param $read
     * @param int $so_far
     * @return string
     */
    protected function readSocketStream($read, $so_far=0){
		$response = "";
		$size  = substr($read, 1);
		do {
			$amt_to_read = min(1024, ($size - $so_far));
			$response   .= fread($this->socket, $amt_to_read);
			$so_far     += $amt_to_read;
		} while ($so_far < $size);
		fread($this->socket, 2); 
		return $response;
	}

}

