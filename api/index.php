<?php

function assert_failure() {
	$text = "A OJS error (assertion fault) occured. Maybe in Your journal settings You have to set up the setting 'Base new article's copyright year on' wich you can find under journal - setup - 3. sumissions.";
	error_log($text, E_USER_ERROR);
	ob_start();
	debug_print_backtrace();
	error_log(ob_get_clean());
	throw new Exception($text);
}

assert_options(ASSERT_CALLBACK, 'assert_failure');

$includePath = dirname(__FILE__) . '/';
include(realpath("$includePath../lib/php_default_api/index.php"));
?>
