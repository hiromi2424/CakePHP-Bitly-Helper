# Bitly Helper #

## Version ##

This was versioned as alpha.

## Introduction ##

BitlyHelper is a CakePHP helper for bitly api.

## Requirements ##

- CakePHP >= 1.2
- PHP >= 4
- json_decode() function is available. if you don't have it, install upgrade.php(http://upgradephp.berlios.de/) into your vendors diretory.

## Setup ##

With console:
	cd /path/to/plugins/
	git clone git://github.com/hiromi2424/CakePHP-Bitly-Helper.git bitly

or

	git submodule add git://github.com/hiromi2424/CakePHP-Bitly-Helper.git plugins/bitly


In controller's property section:
	var $helpers = array( ... , 'Bitly.Bitly');

In somewhere you should define:
	Configure::write('Bitly', array(
		'user_name' => 'your_acount_name',
		'api_token' => 'your_api_key_for_authentication',
	);

or

	$helpers = array(
		...
		'Bitly' => array(
			'user_name' => 'your_acount_name',
			'api_token' => 'your_api_key_for_authentication',
		),
		...
	);
## Usage ##

On your view,
	echo $this->Bitly->shorten($long_url_to_shoten);
