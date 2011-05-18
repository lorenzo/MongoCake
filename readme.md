# Installation #

Change into your Plugin directory, and checkout the git repo:

	cd Plugin
	git clone git://github.com/lorenzo/MongoCake.git
	cd MongoCake
	git submodule update --init --recursive

# Configuration #

Use this MongoCake plugin like any other datasource, with its own configuration options:

	// Within Config/database.php
	public $default = array(
		'datasource' => 'MongoCake.CakeMongoSource',
		'server' => 'localhost', // Optional
	);

# Models #

Ensure that your models extend the CakeDocument class.

	class User extends CakeDocument {
	}

