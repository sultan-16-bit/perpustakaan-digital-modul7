<?php

// Paksa SCRIPT_NAME ke /index.php agar Laravel tidak menambahkan prefix /api pada seluruh rute web
$_SERVER['SCRIPT_NAME'] = '/index.php';
$_SERVER['SCRIPT_FILENAME'] = __DIR__ . '/../public/index.php';

require __DIR__ . '/../public/index.php';