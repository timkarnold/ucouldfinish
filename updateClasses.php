<?php

// Time script
$mtime = microtime(); 
$mtime = explode(" ",$mtime); 
$mtime = $mtime[1] + $mtime[0]; 
$starttime = $mtime; 

require("lib/RollingCurl.php");
require("inc-database.php");

echo "<h1>Update Classes</h1>"; 

$baseURL = "http://ucouldfinish.com/app/getClass.php";

// PDO Setup / SQL statements
$sql = array(
	'get_subs' => 'SELECT class,term FROM subscriptions WHERE active=1 ORDER BY sub',
	'class_insert' => 'INSERT INTO classes VALUES (NOW(), :class_id, :available_seats, :term) ON DUPLICATE KEY UPDATE date = NOW(), available_seats = :available_seats, term = :term'
);

foreach ($sql as $stmnt_name => $stmnt_sql) {
	$sql[$stmnt_name] = $DB->prepare($stmnt_sql);
}

// Get Subscriptions
$sql['get_subs']->execute();
$subs = $sql['get_subs']->fetchAll();

// Prepare variables					
$classesInfo = array();
$i = 0;

function request_callback($response, $info, $request) {
	global $i, $classesInfo;
	
	// Extract class number from called URL
	$urlParts = parse_url($info['url']);
	$urlVars = explode('=', $urlParts['query']);
	$class = $urlVars[2];
	preg_match_all('!\d+!', $urlVars[1], $term);
	$term = implode(' ', $term[0]);
	
	$requestTime = round($info['total_time'],3);
	preg_match_all('!\d+!', $response, $seats);
	$seats = implode(' ', $seats[0]);
	
	// Update master array
	$classesInfo[$i] = array("id" => $class,
							"term" => $term,
							"seats" => $seats,
							"time" => $requestTime );
	$i++;

	echo "Class: $class Seats: $seats Term: $term Request Time: $requestTime<br />";
	// print_r($info);
    // print_r($request);
    // echo "<br /><hr /><br />".PHP_EOL;
}

unset($class); // break the reference with the last element

$rc = new RollingCurl("request_callback");
$rc->window_size = 20;
foreach ($subs as $sub) {
	$url = $baseURL.'?term='.$sub['term'].'&class='.$sub['class'];
    $request = new RollingCurlRequest($url);
    $rc->add($request);
}
$rc->execute();

// All classes have been fetched for code to reach this point

// Insert/update classes table entry
foreach ($classesInfo as $class) {
	//$classesInfo[$i] ("id" => $classNum[2], "seats" => $seats, "time" => $requestTime )
	$sql['class_insert']->bindValue(':class_id', $class['id'], PDO::PARAM_INT);
	$sql['class_insert']->bindValue(':available_seats', $class['seats'], PDO::PARAM_INT);
	$sql['class_insert']->bindValue(':term', $class['term'], PDO::PARAM_INT);
	$sql['class_insert']->execute();
}

$mtime = microtime(); 
$mtime = explode(" ",$mtime); 
$mtime = $mtime[1] + $mtime[0]; 
$endtime = $mtime; 
$totaltime = ($endtime - $starttime); 
$totaltime = round($totaltime, 3);
$totalclasses = count($subs);
echo "<br />Class update execution: Fetched $totalclasses classes in $totaltime seconds"; 

echo '<br /><br /><hr />';

echo "<h1>Notify Openings</h1>"; 

// Time script
$mtime = microtime(); 
$mtime = explode(" ",$mtime); 
$mtime = $mtime[1] + $mtime[0]; 
$notifytime = $mtime; 

$curl = curl_init();
curl_setopt ($curl, CURLOPT_URL, "http://ucouldfinish.com/app/notifyOpening.php");
curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);

$result = curl_exec ($curl);
curl_close ($curl);
echo $result;

$mtime = microtime(); 
$mtime = explode(" ",$mtime); 
$mtime = $mtime[1] + $mtime[0]; 
$endtime = $mtime; 
$totaltime = ($endtime - $notifytime); 
$totaltime = round($totaltime, 3);
echo "<br />Notify users execution: $totaltime seconds"; 

echo '<br /><br /><hr />';

$mtime = microtime(); 
$mtime = explode(" ",$mtime); 
$mtime = $mtime[1] + $mtime[0]; 
$endtime = $mtime; 
$totaltime = ($endtime - $starttime); 
$totaltime = round($totaltime, 3);
echo "<br />Total execution: $totaltime seconds"; 