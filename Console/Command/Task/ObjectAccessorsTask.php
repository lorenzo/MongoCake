<?php

App::uses('ConnectionManager', 'Model');
class ObjectAccessorsTask extends Shell {

    public $tasks = array('Template');


    public function execute() {
        list($plugin, $class) = pluginSplit($this->args[0], true);
        App::uses($class, $plugin . 'Model');
        $model = new $class;

        $missingMethods = array();
        $schema = $model->schema();
        foreach ($schema as $p => $v) {
            $method = 'get' . Inflector::camelize($p);
            if (!method_exists($model, $method)) {
                $missingMethods[$p][] = $method;
            }
            $method = 'set' . Inflector::camelize($p);
            if ($p == $model->primaryKey) {
                continue;
            }
            if (!method_exists($model, $method)) {
                $missingMethods[$p][] = $method;
            }
        }

        $this->out('<info>Will generate the following methods:</info>');
        $this->hr();
        foreach ($missingMethods as $p => $m) {
            $this->out('<info>'.$p.': </info>'. implode(', ', $m));
        }

        $this->Template->set('schema', $schema);
        $this->Template->set('properties', $missingMethods);
        $content = $this->Template->generate('classes', 'accessors');
        if ($this->appendContent($class, $content)) {
            $this->out('<success>Added property accessors to class</success>');
        }
    }

    public function appendContent($class, $content) {
        $reflection = new ReflectionClass($class);
        $lastLine = $reflection->getEndLine() - 1;
        $file = file($reflection->getFileName());
        $beginning = array_slice($file, 0, $lastLine);
        $end = array_slice($file, $lastLine);
        unset($file);
        return (bool) file_put_contents(
            $reflection->getFileName(),
            implode('', $beginning) . PHP_EOL . $content . PHP_EOL . implode('', $end)
        );
    }

}