<?php
class RedisCache implements CacheInterface {

	private $scope;
	private $key;
	private $conn;
	private $expiration = 0;
	private $time = false;
	private $data = null;

	public function __construct() {
		if (!extension_loaded('redis')) {
			returnServerError('"redis" extension not loaded. Please check "php.ini"');
		}

		$host = Configuration::getConfig(get_called_class(), 'host');
		$port = Configuration::getConfig(get_called_class(), 'port');
		$password = Configuration::getConfig(get_called_class(), 'password');

		if (empty($host) && empty($port)) {
			returnServerError('Configuration for ' . get_called_class() . ' missing. Please check your ' . FILE_CONFIG);
		} else if (empty($host)) {
			returnServerError('"host" param is not set for ' . get_called_class() . '. Please check your ' . FILE_CONFIG);
		} else if (empty($port)) {
			returnServerError('"port" param is not set for ' . get_called_class() . '. Please check your ' . FILE_CONFIG);
		} else if (!ctype_digit($port)) {
			returnServerError('"port" param is invalid for ' . get_called_class() . '. Please check your ' . FILE_CONFIG);
		}

		$port = intval($port);

		if ($port < 1 || $port > 65535) {
			returnServerError('"port" param is invalid for ' . get_called_class() . '. Please check your ' . FILE_CONFIG);
		}
		$conn = new Redis();
		$conn->connect($host, $port) or returnServerError('Could not connect to redis server');

		$conn->auth($password);

		$this->conn = $conn;
	}

	public function loadData(){
		if ($this->data) return $this->data;
		$result = json_decode($this->conn->get($this->getCacheKey()), true);
		if ($result === false) {
			return null;
		}
		$this->time = $result['time'];
		$this->data = $result['data'];
		return $result['data'];
	}

	public function saveData($datas){
		$time = time();
		$object_to_save = json_encode(array(
			'data' => $datas,
			'time' => $time,
		));
		$result = $this->conn->set($this->getCacheKey(), $object_to_save, $this->expiration);

		if($result === false) {
			returnServerError('Cannot write the cache to redis server');
		}

		$this->time = $time;

		return $this;
	}

	public function getTime(){
		if ($this->time === false) {
			$this->loadData();
		}
		return $this->time;
	}

	public function purgeCache($duration){
		// Note: does not purges cache right now
		// Just sets cache expiration and leave cache purging for memcached itself
		$this->expiration = $duration;
	}

	/**
	* Set scope
	* @return self
	*/
	public function setScope($scope){
		$this->scope = $scope;
		return $this;
	}

	/**
	* Set key
	* @return self
	*/
	public function setKey($key){
		if (!empty($key) && is_array($key)) {
			$key = array_map('strtolower', $key);
		}
		$key = json_encode($key);

		if (!is_string($key)) {
			throw new \Exception('The given key is invalid!');
		}

		$this->key = $key;
		return $this;
	}

	private function getCacheKey(){
		if(is_null($this->key)) {
			returnServerError('Call "setKey" first!');
		}

		return 'rss_bridge_cache_' . hash('md5', $this->scope . $this->key . 'A');
	}
}
