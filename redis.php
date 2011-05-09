<?php
define('NL', sprintf('%s%s', chr(13), chr(10)));
class Redis {
	private $socket;
	public $host;
	public $port;

	public function __construct($host='localhost', $port = 6379) {
		$this->host   = $host;
		$this->port   = $port;
		session_write_close();
		$this->socket = fsockopen($this->host, $this->port, $errno, $errstr);
		if (!$this->socket) {
			throw new Exception("{$errno} - {$errstr}");
		}
		//stream_set_blocking($this->socket, 0);
	}

	public function __destruct() {
		fclose($this->socket);
	}

	/*
	 * The new unified request protocol as of 1.2 is of the general form:
	 * '*'<number of args> CR LF
	 * '$'<length of argument 1> CR LF
	 * <argument 1> CR LF
	 * ...
	 * ...
	 * <length of argument n> CR LF
	 * <argument n data>
	 *
	 * Example: SET xyz fooball becomes ->
	 * '*'3\r\n$3\r\nSET\r\n$3\r\nxyzr\nfooball\r\n 
	 */

	public function __call($name, $args) {
		/*
		if(is_array($args[count($args)-1])) {
			$args = $this->expandArgs($args);
		}
		*/
		array_unshift($args, strtoupper($name));
		$cmd    = sprintf('*%d%s%s%s', count($args), NL, $this->argsWithLengths($args), NL);
		$done   = 0;
		$cmdlen = strlen($cmd);
		for ($w = 0; $w < $cmdlen; $w += $done) {
			$done = fwrite($this->socket, substr($cmd, $w));
			if ($done === FALSE) {
				throw new Exception('Failed to write entire command to stream');
			}
		}

		$response = $this->handleReply();
		return $response;

	}

	private function handleReply(){
		$reply = trim(fgets($this->socket, 512));
		$responses = array(
			'+' => 'replySingle' , '-' => 'replyError'     , ':' => 'replyInt' , 
			'$' => 'replyBulk'   , '*' => 'replyMultiBulk'
		);
		$response_code = substr($reply, 0, 1);
		try {
			$func = array($this, $responses[$response_code]);
			$response = call_user_func_array($func, array($reply));
			return $response;
		} catch (Exception $e) {
			die("server response makes no sense to me: {$reply}");
		}
	}

	/******************
	 * Reply Handlers *
	 ******************/
	private function replySingle($reply){
		$response = substr(trim($reply), 1);
		return $response;
	}

	private function replyError($reply){
		throw new RedisException(substr(trim($reply), 4));
	}

	private function replyInt($reply){
		$response = intval(substr(trim($reply), 1));
		return $response;
	}

	private function replyBulk($reply){
		$response = null;
		if ($reply == '$-1') {
			break;
		}
		$response = $this->readSocketStream();
		return $response;
	}

	private function replyMultiBulk($reply){
		$count = substr($reply, 1);
		if ($count == '-1') {
			return null;
		}
		$response = array();
		for ($i = 0; $i < $count; $i++) {
			$bulk_head = trim(fgets($this->socket, 512));
			$size      = substr($bulk_head, 1);
			$response[] = ($size == '-1') 
				? null 
				: $this->readSocketStream(); 
		}
		return $reply;
	}

	/*******************
	 * Utility methods *
	 *******************/
	private function argsWithLengths($args){
		$cmd_parts = array_map(function($x){ 
			return sprintf('$%d\r\n%s\r\n', strlen($x), $x); }, 
			$args
		);
		return implode('\r\n', $cmd_parts);
	}

	private function expandArgs($args){
		$first = $args[0];
		$rev   = array_reverse($args);
		$out   = array();
		foreach($rev[0] as $k => $v){
			$out[] = $k;
			$out[] = $v;
		}
		array_unshift($out, $args[0]);
		return $out;
	}

	private function readSocketStream(){
		$so_far = 0;
		$block = "";
		do {
			$block  .= fread($this->socket, min($size - $so_far));
			echo $block;
			$so_far += $read_ptr;
		} while ($so_far < $size);
		fread($this->socket, 2); 
		return $block;
	}

}

$r = new Redis();
$r->info();
$r->set('testme', 'foobar');
