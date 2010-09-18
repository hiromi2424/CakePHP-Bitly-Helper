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
	var $baseUri = 'http://api.bit.ly/v3/';

/**
 * domain for shorten api.
 * 'bit.ly' default or 'j.mp'
 *
 * @var string domain
 * @access public
 */
	var $domain = 'bit.ly';

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
 * Http client for post request.
 *
 * @var object HttpSocket
 * @access public
 */
	var $client;


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
	function shorten($longUrl, $params = array()){
		$raw = false;
		if (isset($params['raw'])) {
			$raw = $params['raw'];
			unset($params['raw']);
		}
		$params['domain'] = $this->domain;

		$longUrl = trim($longUrl);
		$cache = $this->_loadCache('shorten');
		$cacheKey = $longUrl . serialize($params);
		if (!empty($cache)) {
			if (isset($cache[$cacheKey])) {
				return $raw ? $cache[$cacheKey] : $cache[$cacheKey]->data->url;
			}
		} else {
			$cache = array();
		}

		$result = $this->get('shorten', compact('longUrl') + $params);
		if($result === null){
			return null;
		}
		$cache[$cacheKey] = $result;
		$this->_saveCache('shorten', $cache);
		return $raw ? $result : $result->data->url;
	}

/**
 * method for "expand" api. this uses cache.
 *
 * @param string $shortUrl url or hash to expand
 * @return mixed expanded url or null(failed)
 * @access public
 */
	function expand($shortUrl, $params = array()){
		$raw = false;
		if (isset($params['raw'])) {
			$raw = $params['raw'];
			unset($params['raw']);
		}

		$cache = $this->_loadCache('expand');

		$toGet = array();
		$cached = array();
		$updateCache = false;
		foreach ((array)$shortUrl as $index => $value) {
			$value = trim($value);
			$hash = preg_match('#http://(bit\.ly|j\.mp)/(.+)#', $value, $matches) ? $matches[2] : $value;
			if (!isset($cache[$hash])) {
				$toGet[] = $hash;
				$updateCache = true;
			} else {
				$cached[$hash] = $cache[$hash];
			}
		}

		if ($updateCache) {
			$result = $this->get('expand', array('hash' => $toGet));
			if($result === null){
				return null;
			}

			foreach ($result->data->expand as $data) {
				$cache[$data->hash] = $data;
				$cached[$data->hash] = $data;
			}

			$this->_saveCache('expand', $cache);
		}

		if (!$raw) {
			foreach ($cached as $hash => $data) {
				$cached[$hash] = $data->long_url;
			}
		}
		return count($cached) == 1 ? current($cached) : $cached;
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
 * api requrest with post method.
 *
 * @param string $api api name
 * @param array $params parameters for api
 * @return mixed object-mapped data or null(failed)
 * @access public
 */
	function post($api, $params){
		$params += array('format' => 'json');
		$url = $this->generateUrl($api, $params);
		$result = $this->_postDecode($url);

		if (!empty($result) && isset($result->status_code) && $result->status_code == 200) {
			return $result;
		}
		if ($this->retry) {
			$retry = $this->retry === true ? 1 : intval($this->retry);
			$i = 0;
			do {
				$result = $this->_postDecode($url);
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
 * post contents from url and parse it.
 *
 * @param string $url url
 * @return mixed object-mapped data or null(failed)
 * @access public
 */
	function _postDecode($url) {
		$uri = parse_url($url);
		if (!$uri) {
			return null;
		}
		$data = $uri['query'];
		unset($uri['query']);

		App::import('Core', 'HttpSocket');
		$this->client = new HttpSocket;
		$result = $this->client->post($uri, $data);
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
 * @return mixed genareted url or null
 * @access public
 */
	function generateUrl($api, $params = array(), $key = null){
		$url = $this->baseUri . $api . "?";
		$params += $this->_authenticateParams();

		if (isset($params['_key']) && $key === null) {
			$key = $params['_key'];
			unset($params['_key']);
		}
		$url .= $this->_generateQuery($params, $key);
		return $url;
	}

/**
 * generating uri queries from parameters.
 *
 * @param array $params parameters for api
 * @param string $key key for same query name(ex. hoge=1&hoge=3)
 * @return mixed genareted uri query or null
 * @access protected
 */

	function _generateQuery($params, $key = null) {
		if (empty($params)) {
			return null;
		}

		if (Set::numeric(array_keys($params)) && $key !== null) {
			foreach ($params as $index => $param) {
				$params[$index] = urlencode($key) . "=" . urlencode($param);
			}
			return implode('&', $params);
		}

		foreach ($params as $index => $param) {
			if (is_array($param)) {
				if (Set::numeric(array_keys($param))) {
					if ($key === null) {
						$key = $index;
					}
					$params[$index] = $this->_generateQuery($param, $key);
				} else {
					trigger_error(__('parameters cannot be complicated array', true));
					continue;
				}
			} else {
				$params[$index] = urlencode($index) . "=" . urlencode($param);
			}
		}
		return implode('&', $params);
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