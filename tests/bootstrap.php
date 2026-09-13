<?php

$basePath = dirname(__DIR__);
$environmentFile = $basePath.'/.env';
$environmentTemplate = $basePath.'/.env.example';

if (! is_file($environmentFile) && is_file($environmentTemplate)) {
    copy($environmentTemplate, $environmentFile);
}

require $basePath.'/vendor/autoload.php';
