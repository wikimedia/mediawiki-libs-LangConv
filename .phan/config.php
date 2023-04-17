<?php

$cfg = require __DIR__ . '/../vendor/mediawiki/mediawiki-phan-config/src/config-library.php';
$cfg['target_php_version'] = '7.2';
$cfg['directory_list'][] = 'tests';

return $cfg;
