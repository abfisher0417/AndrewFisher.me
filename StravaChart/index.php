<?php
/*
 * index.php is a RESTful API Web Service that returns Strava activities
 * for given week.
 * 
 * @param int startInterval The week to return data as of in YYYYMMDD format
 * 
 */
define('IN_PHP', true);

require_once 'Utility.php';

// --- Initialize variables and functions
// Define API response codes and their related HTTP response
$api_response_code = array(
    0 => array('HTTP Response' => 400, 'Message' => 'Unknown Error'),
    1 => array('HTTP Response' => 200, 'Message' => 'Success'),
    2 => array('HTTP Response' => 403, 'Message' => 'HTTPS Required'),
    3 => array('HTTP Response' => 401, 'Message' => 'Authentication Required'),
    4 => array('HTTP Response' => 401, 'Message' => 'Authentication Failed'),
    5 => array('HTTP Response' => 404, 'Message' => 'Invalid Request'),
    6 => array('HTTP Response' => 400, 'Message' => 'Invalid Response Format')
);

/*
 * Deliver HTTP Response with JSON content type
 * @param string $api_response The desired HTTP response data
 * @return void
 */
function deliver_response($api_response) {
    // Define HTTP responses
    $http_response_code = array(
        200 => 'OK',
        400 => 'Bad Request',
        401 => 'Unauthorized',
        403 => 'Forbidden',
        404 => 'Not Found'
    );

    // Set HTTP Response
    header('HTTP/1.1 '.$api_response['status'].' '.$http_response_code[$api_response['status']]);

    // Set HTTP Response Content Type
	header('Content-Type: application/json; charset=utf-8');

    // Format data into a JSON response
    $json_response = json_encode($api_response);

    // Deliver formatted data
    echo $json_response;
 
    // End script process
    exit;
}

/*
 * Deliver HTTP Error Response with JSON content type
 * @param int $code The error code (1-6)
 * @param int $status The associated HTTP status
 * @param string $message The desired HTTP response message
 * @return void
 */
function deliver_error_response($code, $status, $message) {
	$response['code'] = $code;
   	$response['status'] = $status;
   	$response['data'] = $message;
   	deliver_response($response);
}

// --- Process Request
date_default_timezone_set("UTC");
	
$startInterval = $_GET['startInterval'];

if (is_null($startInterval)
	|| (!is_null($startInterval) && empty($startInterval))) {
    $day = date('w');
	$startInterval = date('Ymd', strtotime('-' . $day . ' days'));
}

if (!$startDateTime = DateTime::createFromFormat('Ymd', $startInterval)) {
   	deliver_error_response(5, $api_response_code[5]['HTTP Response'], 'Error parsing startInterval. Must be a valid date in YYYYMMDD format.');
}

$nextWeek = DateTime::createFromFormat('Ymd', $startInterval, new DateTimeZone("UTC"))->modify('+7 days');
$prevWeek = DateTime::createFromFormat('Ymd', $startInterval, new DateTimeZone("UTC"))->modify('-7 days');

$response['curWeek'] = $startDateTime->format('M j, Y');
$response['nextWeek'] = $nextWeek->format('Ymd');
$response['prevWeek'] = $prevWeek->format('Ymd');

// Connect to MySQL
$conn = new mysqli($servername, $username, $password, $database);
if ($conn->connect_error) {
	deliver_error_response(0, $api_response_code[0]['HTTP Response'], "Connection failed: " . $conn->connect_error);
}

// Select activities between start date and end date
$sql = "SELECT CASE WHEN type = 'Swim' OR type = 'Run' THEN type WHEN type = 'Ride' THEN 'Bike' ELSE 'Cross-Training' END AS type, startDate, SUM(movingTime) AS duration, SUM(distance) AS distance FROM Activities WHERE startDate >= " . $startDateTime->format('Ymd') . " AND startDate < " . $nextWeek->format('Ymd') . " GROUP BY type, startDate";
if (!$result = $conn->query($sql)) {
	deliver_error_response(0, $api_response_code[0]['HTTP Response'], "Select activities failed (" . $mysqli->errno . ") " . $mysqli->error);
}

if ($result->num_rows > 0) {
	$response['data'] = array();
	$i = 0;
    while ($row = $result->fetch_assoc()) {
    	$response['data'][$i]['type'] = $row['type'];
    	$response['data'][$i]['day'] = DateTime::createFromFormat('Ymd', $row['startDate'], new DateTimeZone("UTC"))->format('l');
    	$response['data'][$i]['duration'] = intval($row['duration']);
    	$response['data'][$i]['distance'] = round($row['distance'] * 0.000621371192, 1); // Convert meters to miles
    	$i++;
    }
}

$response['code'] = 1;
$response['status'] = $api_response_code[$response['code']]['HTTP Response'];
deliver_response($response);
?>