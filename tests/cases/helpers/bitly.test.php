<?php

App::import('Helper', 'Bitly.Bitly');

class BitlyHelperTestCase extends CakeTestCase {
	function start() {
		parent::start();
		$this->config = Configure::read('Bitly');
	}

	function startTest() {
		$this->_loadHelper();
	}

	function _loadHelper($settings = array()) {
		$this->Bitly = new BitlyHelper($settings);
	}

	function testConfig() {
		$this->skipIf(empty($this->config['user_name']) || empty($this->config['api_token']), __("To test configuration, define Configure::read('Bitly', array('user_name' => ..., 'api_token' => ...))", true));

		$b =& $this->Bitly;

		$this->assertEqual($b->user_name, $this->config['user_name']);
		$this->assertEqual($b->api_token, $this->config['api_token']);
	}

	function testGenerateUrl() {
		$b =& $this->Bitly;
		$b->user_name = "test_user_name";
		$b->api_token = "test_api_token";

		$this->assertEqual($b->generateUrl('api_name'), 'http://api.bit.ly/v3/api_name?login=test_user_name&apiKey=test_api_token');
		$this->assertEqual($b->generateUrl('api_name', array('testparam' => 'testvalue')), 'http://api.bit.ly/v3/api_name?testparam=testvalue&login=test_user_name&apiKey=test_api_token');
		$this->assertEqual($b->generateUrl('api_name', array('testparam' => 'testvalue', 'test_subarray' => array('hoge', 'piyo'))), 'http://api.bit.ly/v3/api_name?testparam=testvalue&test_subarray=hoge&test_subarray=piyo&login=test_user_name&apiKey=test_api_token');
		$this->assertEqual($b->generateUrl('api_name', array('testparam' => 'testvalue', array('hoge', 'piyo')), 'test_subarray'), 'http://api.bit.ly/v3/api_name?testparam=testvalue&test_subarray=hoge&test_subarray=piyo&login=test_user_name&apiKey=test_api_token');
		$this->assertEqual($b->generateUrl('api_name', array('testparam' => 'testvalue', array('hoge', 'piyo'), '_key' => 'test_subarray')), 'http://api.bit.ly/v3/api_name?testparam=testvalue&test_subarray=hoge&test_subarray=piyo&login=test_user_name&apiKey=test_api_token');
	}

	function testGenerateQuery() {
		$b =& $this->Bitly;

		$this->assertEqual($b->_generateQuery(array('test_key' => 'test value')), 'test_key=test+value');
		$this->assertEqual($b->_generateQuery(array('test value', 'test value2'), 'test_key'), 'test_key=test+value&test_key=test+value2');
		$this->assertEqual($b->_generateQuery(array('test_key' => 'test_value_hoge', array('test value', 'test value2')), 'test_key'), 'test_key=test_value_hoge&test_key=test+value&test_key=test+value2');
	}

	function testAuthenticateParams() {
		$b =& $this->Bitly;

		$this->assertEqual($b->_authenticateParams(), array('login' => $this->config['user_name'], 'apiKey' => $this->config['api_token']));
	}

	function testCaches () {
		$b =& $this->Bitly;

		$this->assertNull($b->__caches);
		$this->assertFalse($b->_storeCaches());

		$b->_loadCache();
		$this->assertEqual($b->__caches, array());

		$b->_loadCache('invalid cache');
		$this->assertEqual($b->__caches, array());
		$this->assertFalse($b->__cacheUpdated);

		$b->_saveCache('test', 'test cache');
		$this->assertEqual($b->__caches, array('test' => 'test cache'));
		$this->assertTrue($b->__cacheUpdated);

		$b->_storeCaches();
		$this->assertEqual(Cache::read($b->cache_key, $b->cache_config), array('test' => 'test cache'));
		$this->assertEqual($b->_loadCache('test'), 'test cache');
		$this->assertNull($b->_loadCache('invaild key'));
		$this->assertFalse($b->_storeCaches());

		$this->assertEqual($b->_storeCaches(), $b->storeCache());

		Cache::delete($b->cache_key);
	}

	function testGetDecode() {
		$this->skipIf(phpversion() < 5.2, __('This test requires PHP >= 5.2', true));
		$b =& $this->Bitly;

		$data = base64_encode('{"test_key": "test value"}');

		$expected = new stdClass;
		$expected->test_key = 'test value';
		$this->assertEqual($b->_getDecode('data://text/plain;base64,' . $data), $expected);

		$this->assertNull($b->_getDecode(false));
		$this->assertError();
	}

	function testGet() {
		$this->skipIf(!function_exists('json_decode'), __('BitlyHelper requires json_decode() function', true));
		$b =& $this->Bitly;

		$this->assertEqual($b->get('invaild_api', array()), null);

		$expected = new stdClass;
		$expected->status_code = 200;
		$expected->status_txt = "OK";
		$expected->data = new stdClass;
		$expected->data->valid = 0;
		$this->assertEqual($b->get('validate', array('x_login' => 'invalid_login', 'x_apiKey' => 'invalid_apiKey')), $expected);
	}

	function testPost() {
		$this->skipIf(!function_exists('json_decode'), __('BitlyHelper requires json_decode() function', true));
		$b =& $this->Bitly;

		$this->assertEqual($b->post('invaild_api', array()), null);

		$b->post('authenticate', array('x_login' => 'invalid_login', 'x_password' => 'invalid_password'));
		$this->assertEqual($b->client->request['line'], "POST /v3/authenticate HTTP/1.1\r\n");
		$this->assertEqual($b->client->request['body'], 'x_login=invalid_login&x_password=invalid_password&format=json&login=' . $this->config['user_name'] . '&apiKey=' . $this->config['api_token']);
	}

	function testShorten() {
		$this->skipIf(!function_exists('json_decode'), __('BitlyHelper requires json_decode() function', true));
		$b =& $this->Bitly;

		$this->assertPattern('|http://|', $b->shorten('http://example.com'));
		$this->assertTrue($b->__cacheUpdated);

		$b->domain = 'j.mp';
		$this->assertPattern('|http://j\.mp/|', $b->shorten('http://example.com'));
		$this->assertNull($b->shorten('example.com'));

		$result = $b->shorten('http://example.com', array('raw' => true));
		$this->assertEqual($result->status_code, 200);
		$this->assertEqual($result->status_txt, 'OK');
		$this->assertEqual($result->data->long_url, 'http://example.com');
		$this->assertPattern('|http://j\.mp/|', $result->data->url);

		Cache::delete($b->cache_key);
	}

	function testExpand() {
		$this->skipIf(!function_exists('json_decode'), __('BitlyHelper requires json_decode() function', true));
		$b =& $this->Bitly;

		$shortened = array();
		$shortened[] = $b->shorten('http://example.com', array('raw' => true));
		$shortened[] = $b->shorten('http://example.com/hogehoge', array('raw' => true));

		$this->assertEqual($b->expand($shortened[0]->data->url), 'http://example.com');
		$this->assertTrue($b->__cacheUpdated);

		$result = $b->expand(array($shortened[0]->data->url, $shortened[1]->data->url));
		$expected = array($shortened[0]->data->hash => 'http://example.com', $shortened[1]->data->hash => 'http://example.com/hogehoge');
		$this->assertEqual($result, $expected);

		Cache::delete($b->cache_key);
	}

	function testNoConfig() {
		$b =& $this->Bitly;
		Configure::write('Bitly', null);
		$this->_loadHelper();
		$b =& $this->Bitly;

		$this->assertNull($b->user_name);
		$this->assertNull($b->api_token);
	}

	function testAfterLayout() {
		$b =& $this->Bitly;

		$b->_saveCache('testest', 'testtt value');
		$b->afterLayout();
		$this->assertEqual(Cache::read($b->cache_key), array('testest' => 'testtt value'));

		Cache::delete($b->cache_key);
	}
}