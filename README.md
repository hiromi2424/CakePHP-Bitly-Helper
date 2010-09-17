# Bitly Helper #

## Version ##

This was versioned as alpha.

## Introduction ##

BitlyHelper is a CakePHP helper for bitly api.

## Requirements ##

- CakePHP >= 1.2
- PHP >= 4
- json_decode() function is available. if you don't have it, install upgrade.php(http://upgradephp.berlios.de/) into you vendors diretory.

## Setup ##

With console:
	cd /path/to/plugin/
	git clone git://github.com/hiromi2424/CakePHP-Bitly-Helper.git bitly

or

	git submodule add git://github.com/hiromi2424/CakePHP-Bitly-Helper.git plugins/bitly


In controller's property section:
	var $helpers = array( ... , 'Bitly.Bitly');

## Usage ##

On your view,
	echo $this->Bitly->shorten($long_url_to_shoten);
