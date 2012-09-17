<?php

/**
 * The view.  This file provides a "landing point" for the browser.
 * 
 * See MAVAX documentation for more info.
 */

require( "../includes.php" );

$vars = array();
foreach ( $_GET as $getkey => $getval )
{
	$vars[$getkey] = $getval;
}

foreach ( $_POST as $postkey => $postval )
{
	$vars[$postkey] = $postval;
}

$controller = new controller( $vars );

$controller->send_view();
