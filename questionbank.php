<?php

/*
 * Question Bank
 */

/*------------------------------------------------------------------------------
(c) 2010 JISC-funded EASiHE project, University of Southampton
Licensed under the Creative Commons 'Attribution non-commercial share alike' 
licence -- see the LICENCE file for more details
------------------------------------------------------------------------------*/

// error reporting
error_reporting(E_ALL | E_STRICT);

// constants
require_once "include/constants.php";

// class autoloader
function __autoload($classname) {
	$path = "classes/$classname.class.php";
	if (dirname($path) == "classes" && file_exists($path)) {
		require_once $path;
		return;
	}
	$path = "eqiat/classes/$classname.class.php";
	if (dirname($path) == "eqiat/classes" && file_exists($path)) {
		require_once $path;
		return;
	}
	$path = "eqiat/classes/itemtypes/$classname.class.php";
	if (dirname($path) == "eqiat/classes/itemtypes" && file_exists($path)) {
		require_once $path;
		return;
	}
}

// set up include path
ini_set("include_path", ".:" . SITEROOT_LOCAL . "include");

// common functions
require_once "include/functions.php";
require_once "eqiat/include/functions.php";

// character encoding
mb_internal_encoding("UTF-8");

// default timezone
date_default_timezone_set("Europe/London");

// undo any magic quotes
unmagic();

// start sessions
session_start();

// serve the page
$page = isset($_GET["page"]) ? $_GET["page"] : "mainMenu";
switch ($page) {
	default:
		if (dirname("content/$page.php") == "content" && file_exists("content/$page.php"))
			include "content/$page.php";
		else
			notfound();
}

?>
