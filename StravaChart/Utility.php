<?php
/*
 * Utility.php defines MySQL and Strava constants to be used
 * by StravaChartBatchLoad.php and index.php.
 *
 */
if (!defined('IN_PHP')) {
	die("Hackers");
}

// MySQL
$servername = "";
$username = "";
$password = "";
$database = "";

// Strava
$clientID = '';
$clientSecret = '';
$accessToken = '';

$devIpAddresses = array("127.0.0.1","::1");

$minStartDateTime = '11/23/2014';
?>