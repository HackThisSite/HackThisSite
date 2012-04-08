<?php
class certs extends mongoBase {
	
	const HASH      = 'md5';
	const PREFIX    = 'cert_';
	private $redis;
	
    public function __construct($connection) {
        $this->redis = $connection;
    }
    
    public function add($cert) {
		return $this->redis->set($this->getKey($cert),
			Session::getVar('_id') . ':' . trim($cert));
	}
	
	public function get($certKey, $cut = true) {
		$cert = $this->redis->get($certKey);
		return ($cut ? substr($cert, strpos($cert, ':') + 1) : $cert);
	}
	
	public function removeCert($certKey) {
		$this->redis->del($certKey);
	}
	
	public function check($cert) {
		$info = $this->redis->get($this->getKey($cert));
		
		if ($info == false) return null;
		return substr($info, 0, strpos($info, ':'));
	}
	
	public static function getKey($cert) {
		return self::PREFIX . hash(self::HASH, trim($cert));
	}
    
}