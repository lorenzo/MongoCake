# Installation #

Change into your Plugin directory, and checkout the git repo:

	cd Plugin
	git clone git://github.com/lorenzo/MongoCake.git
	cd MongoCake
	git submodule update --init --recursive

# Configuration #

You first need to activate the plugin in CakePHP after placing it in the correct folder:

	CakePlugin::load('MongoCake', array('bootstrap' => true));

Use this MongoCake plugin like any other datasource, with its own configuration options:

	// Within Config/database.php
	public $default = array(
		'datasource' => 'MongoCake.CakeMongoSource',
		'server' => 'localhost', // Optional
		'database' => 'mydatabase', // Database to use
	);

# Models #

Ensure that your models extend the CakeDocument class.

	class User extends CakeDocument {
	}

