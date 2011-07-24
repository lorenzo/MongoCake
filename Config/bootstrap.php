<?php

use Doctrine\Common\ClassLoader;

$mongoODMLocation = dirname(__DIR__) . DS . 'Vendor' . DS . 'mongodb_odm' . DS;
require  $mongoODMLocation . 'lib/vendor/doctrine-common/lib/Doctrine/Common/ClassLoader.php';

// ODM Classes
$classLoader = new ClassLoader('Doctrine\ODM\MongoDB', $mongoODMLocation . 'lib');
$classLoader->register();

// Common Classes
$classLoader = new ClassLoader('Doctrine\Common', $mongoODMLocation . 'lib/vendor/doctrine-common/lib');
$classLoader->register();

// MongoDB Classes
$classLoader = new ClassLoader('Doctrine\MongoDB', $mongoODMLocation . 'lib/vendor/doctrine-mongodb/lib');
$classLoader->register();