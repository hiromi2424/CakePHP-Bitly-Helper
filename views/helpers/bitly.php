<?php

if (!function_exists('json_decode')) {
	if (!App::import('Vendor', 'upgrade')) {
		trigger_error(__('BitlyHelper requires PHP 5 >= 5.2.0, PECL json >= 1.2.0. If you heavily want to use this helper without such a environment, install upgrade.php(http://upgradephp.berlios.de/) into your vendors directory', true));
	}
}

/**
 * BitlyHelper. Convenience methods for bitly api.
 *
 * PHP versions 4 and 5 , CakePHP => 1.2
 *
 * @copyright     Copyright 2010, hiromi
 * @package       bitly
 * @version       alpha
 * @license       MIT License (http://www.opensource.org/licenses/mit-license.php)
 */


/**
 * BitlyHelper.
 * Convenience methods for bitly api.
 * This requires PHP 5 >= 5.2.0, PECL json >= 1.2.0. If you heavily want to use this helper without such a environment, install upgrade.php(http://upgradephp.berlios.de/) into your vendors directory.
 *
 * @package       bitly
 */

class BitlyHelper extends AppHelper {

/**
 * Base uri for bitly api.
 *
 * @var string uri
 * @access public
 */
	var $baseUri = "http://api.bit.ly/v3/";

/**
 * login param for api
 *
 * @var string login name
 * @access public
 */
	var $user_name;

/**
 * apiKey param for api.
 *
 * @var string api key
 * @access public
 */
	var $api_token;


/**
 * Private cache substance.
 *
 * @var mixed caches
 * @access private
 */
	var $__caches = null;

/**
 * flag for cache was updated.
 *
 * @var boolean cache was updated
 * @access private
 */
	var $__cacheUpdated = false;

/**
 * cache config name for this helper.
 *
 * @var string config name
 * @access public
 */
	var $cache_config = 'default';

/**
 * Turns on or off auto storing caches.
 * To store it manually, specify false and use storeCache() method.
 *
 * @var boolean auto stroring or not
 * @access public
 */
	var $auto_store_cache = true;

/**
 * Cache key for this helper.
 *
 * @var string cache key
 * @access public
 */
	var $cache_key = 'bitly';


/**
 * Count for retry to access api when request was failed.
 * Given true, this retries once.
 * Given numeric, this retries integral times.
 * Given false, it doesn't retry.
 *
 * @var mixed retry times
 * @access public
 */
	var $retry = true;

/**
 * Constructing and configuration.
 * "Bitly" is user configuration key.
 *
 * @param array $settings specified settings
 * @return void
 * @access private
 */
	function __construct($settings = array()){
		$config = Configure::read('Bitly');
		if (empty($config)) {
			$config = array();
		}

		$this->_set($config);
		$this->_set($settings);
	}

/**
 * afterLayout callback. auto storing caches.
 *
 * @return void
 * @access public
 */
	function afterLayout() {
		if ($this->auto_store_cache) {
			$this->_storeCaches();
		}
	}

/**
 * method for "shorten" api. this uses cache.
 *
 * @param string $longUrl url to shorten(requires scheme like http://)
 * @return mixed shortened url or null(failed)
 * @access public
 */
	function shorten($longUrl){
		$longUrl = trim($longUrl);
		$cache = $this->_loadCache('shorten');
		if (!empty($cache)) {
			if (isset($cache[$longUrl])) {
				return $cache[$longUrl];
			}
		} else {
			$cache = array();
		}

		$result = $this->get('shorten', compact('longUrl'));
		if($result === null){
			return null;
		}
		$cache[$longUrl] = $result->data->url;
		$this->_saveCache('shorten', $cache);
		return $result->data->url;
	}

/**
 * api requrest with get method.
 *
 * @param string $api api name
 * @param array $params parameters for api
 * @return mixed object-mapped data or null(failed)
 * @access public
 */
	function get($api, $params){
		$params += array('format' => 'json');
		$url = $this->generateUrl($api, $params);
		$result = $this->_getDecode($url);

		if (!empty($result) && isset($result->status_code) && $result->status_code == 200) {
			return $result;
		}
		if ($this->retry) {
			$retry = $this->retry === true ? 1 : intval($this->retry);
			$i = 0;
			do {
				$result = $this->_getDecode($url);
				if(!empty($result) && isset($result->status_code) && $result->status_code == 200){
					return $result;
				}
				$i++;
			} while($i < $retry);
		}
		
		return null;
	}

/**
 * get contents from url and parse it.
 *
 * @param string $url url
 * @return mixed object-mapped data or null(failed)
 * @access public
 */
	function _getDecode($url) {
		$result = file_get_contents($url);
		if (empty($result)) {
			return null;
		}
		$result = json_decode($result);
		return $result;
	}

/**
 * generating url from api name and parameters.
 *
 * @param string $api api name
 * @param array $params parameters for api
 * @return genareted url
 * @access public
 */
	function generateUrl($api, $params){
		$url = $this->baseUri . $api . "?";
		$params += $this->_authenticateParams();
		foreach ($params as $key => $param) {
			$params[$key] = $key . "=" . urlencode($param);
		}
		$url .= implode('&', $params);
		return $url;
	}

/**
 * returning authenticate parameters.
 *
 * @return array authenticate parameters
 * @access protected
 */	
	function _authenticateParams(){
		$result = array(
			'login'  => $this->user_name,
			'apiKey' => $this->api_token,
		);
		return $result;
	}

/**
 * loading cache
 *
 * @param string $name key for internal method
 * @return mixed cache when it exists, otherwise null
 * @access protected
 */
	function _loadCache($name = null) {
		if ($this->__caches === null) {
			$this->__caches = Cache::read($this->cache_key, $this->cache_config);
			if (empty($this->__caches)) {
				$this->__caches = array();
			}
		}

		if (!empty($this->__caches[$name])) {
			return $this->__caches[$name];
		}
		return null;
	}

/**
 * save cache internally.
 *
 * @param string $name key for internal method
 * @param mixed $cache cache to save
 * @return void
 * @access protected
 */
	function _saveCache($name, $cache) {
		$this->_loadCache($name);
		$this->__caches[$name] = $cache;
		$this->__cacheUpdated = true;
	}

/**
 * storing internal caches.
 *
 * @return boolean success
 * @access protected
 */
	function _storeCaches() {
		if ($this->__caches === null) {
			return false;
		}
		if ($this->__cacheUpdated) {
			$this->__cacheUpdated = false;
			return Cache::write($this->cache_key, $this->__caches, $this->cache_config);
		}
		return false;
	}

/**
 * alias for _storeCaches().
 *
 * @return boolean success
 * @access public
 */
	function storeCache() {
		return $this->_storeCaches();
	}
}