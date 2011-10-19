<?php

class DocumentBakeShell extends Shell {

    public $tasks = array(
		'DbConfig',
        'MongoCake.ObjectAccessors',
		'MongoCake.Proxies',
		'MongoCake.Hydrators'
    );

    public $actionMap = array(
        'G' => 'ObjectAccessors',
		'C' => 'Proxies',
		'H' => 'Hydrators'
    );

/**
 * The connection being used.
 *
 * @var string
 */
	public $connection = 'default';

/**
 * Assign $this->connection to the active task if a connection param is set.
 *
 * @return void
 */
	public function startup() {
		parent::startup();
		Configure::write('debug', 2);
		Configure::write('Cache.disable', 1);

		$task = Inflector::classify($this->command);
		if (isset($this->{$task}) && !in_array($task, array('DbConfig'))) {
			if (isset($this->params['connection'])) {
				$this->{$task}->connection = $this->params['connection'];
			}
		}
	}

    public function main() {
		$this->out(__d('cake_console', 'Interactive Bake Shell'));
		$this->hr();
		$this->out(__d('cake_console', '[G]etters and setters for class'));
		$this->out(__d('cake_console', '[P]roxy classes'));
		$this->out(__d('cake_console', '[H]ydrator classes'));
		$this->out(__d('cake_console', '[Q]uit'));

        $options = array_keys($this->actionMap);
		$options[] = 'Q';
		$action = strtoupper($this->in(__d('cake_console', 'What would you like to Bake?'), $options));
		if ($action === 'Q') {
		    return 0;
		}
		if (!in_array($action, $options)) {
		    $this->out(__d('cake_console', 'You have made an invalid selection'));
		} else {
		    $this->{$this->actionMap[$action]}->execute();
		}
    }


/**
 * get the option parser.
 *
 * @return void
 */
	public function getOptionParser() {
		$parser = parent::getOptionParser();
		return $parser->description(
			_('Utility shell similar to cake bake to provide useful command to get you started fast')
		)->addSubcommand('proxies', array(
			'help' => __('Generate proxy classes for your documents.'),
			'parser' => $this->Proxies->getOptionParser()
		))->addSubcommand('hydrators', array(
			'help' => __('Generate hydrator classes for your documents.'),
			'parser' => $this->Hydrators->getOptionParser()
		))->addOption('connection', array(
			'help' => __d('cake_console', 'Database connection to use in conjunction with `bake all`.'),
			'short' => 'c',
			'default' => 'default'
		));
	}

}