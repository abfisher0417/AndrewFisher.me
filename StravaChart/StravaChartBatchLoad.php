<?php
/*
 * StravaChartBatchLoad.php fetches the latest Strava activities
 * for an athlete represented by the access token. Retrieved activities
 * are insertted into the Activities database table.
 * 
 * This script should be scheduled to run every one to three hours
 * using WGET.
 * 
 * Pre-Requisites:
 * 1 - Strava client ID, secret, and public access token
 * 2 - MySQL database with two tables:
 * CREATE TABLE IF NOT EXISTS `Activities` (
 *   `id` bigint(20) unsigned NOT NULL COMMENT 'Strava activity ID',
 *   `name` varchar(100) COLLATE utf8_bin NOT NULL COMMENT 'Activity name',
 *   `distance` float NOT NULL COMMENT 'Distance in meters',
 *   `movingTime` mediumint(8) unsigned NOT NULL COMMENT 'Moving time in seconds',
 *   `elapsedTime` mediumint(8) unsigned NOT NULL COMMENT 'Elapsed time in seconds',
 *   `type` varchar(25) COLLATE utf8_bin NOT NULL COMMENT 'Activity type (e.g., swim, ride, run)',
 *   `startDate` int(10) unsigned NOT NULL COMMENT 'Start date in yyyymmdd format'
 * ) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_bin COMMENT='Strava activities for one user';
 * 
 * ALTER TABLE `Activities`
 *  ADD PRIMARY KEY (`id`);
 * 
 * CREATE TABLE IF NOT EXISTS `RefreshLog` (
 * `id` int(11) NOT NULL COMMENT 'Internal primary key',
 *   `startDateTime` datetime NOT NULL COMMENT 'Start time of the refresh',
 *   `endDateTime` datetime DEFAULT NULL COMMENT 'End time of the refresh',
 *   `status` tinyint(1) unsigned NOT NULL
 * ) ENGINE=InnoDB AUTO_INCREMENT=24 DEFAULT CHARSET=utf8 COLLATE=utf8_bin;
 * 
 * ALTER TABLE `RefreshLog`
 *  ADD PRIMARY KEY (`id`);
 * 
 * ALTER TABLE `RefreshLog`
 * MODIFY `id` int(11) NOT NULL AUTO_INCREMENT COMMENT 'Internal primary key',AUTO_INCREMENT=24;
 */
define('IN_PHP', true);

require_once 'StravaApi.php';
require_once 'Utility.php';

// Connect to MySQL
$conn = new mysqli($servername, $username, $password, $database);
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Get last refresh date of data
$sql = "SELECT MAX(endDateTime) AS lastRefresh FROM RefreshLog WHERE status = 1";
if (!$result = $conn->query($sql)) {
	die("Fetch lastRefresh failed (" . $mysqli->errno . ") " . $mysqli->error);
}

$lastRefresh = null;
if ($result->num_rows > 0) {
    $row = $result->fetch_assoc();
    $lastRefresh = $row["lastRefresh"];
}

$lastRefreshDateTime = new DateTime(($lastRefresh != null) ? $lastRefresh : $minStartDateTime, new DateTimeZone("UTC"));
$lastRefreshSeconds = $lastRefreshDateTime->getTimestamp();

// Log that refresh begins
$sql = "INSERT INTO RefreshLog (startDateTime, status) VALUES (UTC_TIMESTAMP(), 0)";
if (!$result = $conn->query($sql)) {
	die("Unable to log that refresh began (" . $mysqli->errno . ") " . $mysqli->error);
}

// Connect to the Strava API
$api = new StravaApi($clientID, $clientSecret);

$validateToken = $api->tokenExchange($accessToken);
if ($validateToken != null) {
	// Fetch activities after the last refresh
	$activityList = $api->get('athlete/activities', $accessToken, array('after' => $lastRefreshSeconds));
	if ($activityList != null) {
		// Insert fetched activities
		if (!($stmt = $conn->prepare("INSERT INTO Activities (id, name, distance, movingTime, elapsedTime, type, startDate) VALUES (?, ?, ?, ?, ?, ?, ?)"))) {
    		die("INSERT INTO Activities prepare failed: (" . $mysqli->errno . ") " . $mysqli->error);
		}
		
		$id = 0;
		$name = "";
		$distance = 0.0;
		$movingTime = 0;
		$elapsedTime = 0;
		$type = "";
		$startDate = 0;
		
		if (!$stmt->bind_param("isdiisi", $id, $name, $distance, $movingTime, $elapsedTime, $type, $startDate)) {
    		die("INSERT INTO Activities binding parameters failed: (" . $stmt->errno . ") " . $stmt->error);
		}
		
		foreach($activityList as $key => $val) {
			$id = $val->id;
			$name = $val->name;
			$distance = $val->distance;
			$movingTime = $val->moving_time;
			$elapsedTime = $val->elapsed_time;
			$type = $val->type;
			$startDate = (new DateTime($val->start_date_local, new DateTimeZone("UTC")))->format('Ymd');
			if (!$stmt->execute()) {
    			die("INSERT INTO Activities failed: (" . $stmt->errno . ") " . $stmt->error);
			}
		}
	}
}

// Log that refresh finished
$sql = "SELECT MAX(id) AS maxID FROM RefreshLog WHERE status=0";
if (!$result = $conn->query($sql)) {
	die("Unable to log that refresh finished select failed (" . $mysqli->errno . ") " . $mysqli->error);
}

$maxID = 0;
if ($result->num_rows > 0) {
    $row = $result->fetch_assoc();
    $maxID = $row["maxID"];
}

$sql = "UPDATE RefreshLog SET endDateTime = UTC_TIMESTAMP(), status = 1 WHERE id = " . $maxID;
if (!$result = $conn->query($sql)) {
	die("Unable to log that refresh finished update failed  (" . $mysqli->errno . ") " . $mysqli->error);
}

// Close MySQL
$conn->close();
?>