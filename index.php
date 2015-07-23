<?php

require_once('loader.php');

$content = file_get_contents('input.php');
$obfuscator = new Obfuscator($content);
$output = $obfuscator->obfuscate();
file_put_contents('output.php', $output);
