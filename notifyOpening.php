<?php

require('inc-sms.php');
require("inc-database.php");

// Setup Nexmo
$nexmo_sms = new NexmoMessage(NEXMO_API_KEY, NEXMO_API_SECRET);
define('NEXMO_NUMBER', 15555555555);


// STEP 1: Get Active Subscriptions
$subscriptions = getSubs();

// STEP 1.5: Delay users who haven't hit update threshhold
$delayedsubs = delayUsers($subscriptions);

// STEP 2: Get Classes With Active Subscriptions
$openSeatSubs = getClassesInSystem($delayedsubs);

// STEP 3: Get User Info & Preferences for those to be notified
$userInfo = getUserInfo($openSeatSubs);

// STEP 4: Notify them!
notifySeatOpen($openSeatSubs, $userInfo);

// -- FUNCTIONS --

// STEP 1: Get Active Subscriptions
function getSubs() {
	global $sql, $DB;
	
	// Fetch all subscriptions
	$sql['get_subs'] = 'SELECT * FROM subscriptions WHERE active=1 AND status<>2 ORDER BY sub';
	$sql['get_subs'] = $DB->prepare($sql['get_subs']);
	$sql['get_subs']->execute();
	$subs = $sql['get_subs']->fetchAll();
	
	if (count($subs) == 0) {
		die('No subscriptions active.');
	}
	
	echo count($subs) . ' subscriptions active.<br />';
	
	return $subs;
}

// Step 1.5: Remove those who haven't hit the time threshold
function delayUsers($subs) {
	// Filter out by update_delay
	foreach ($subs as $sub => $val) {
		$updatedLast = strtotime($val['updated']);
		$timeSinceUpdate = time() - $updatedLast; // to get the time since that moment
		$update_delay = $val['update_delay']-60; // time buffer to allot for cron update limit
		if ($timeSinceUpdate >= $update_delay) {
			$val['delayed'] = $timeSinceUpdate; // add delay time to array for debugging
			$delayedSubs[] = $val; // build new array with active users over threshold
			_updateLastCheck($val['sub']); // UPDATE the subscription with the new update time
		}
	}
	if (count($delayedSubs) == 0) {
		die('No subscriptions met update threshold.');
	}
	
	echo count($delayedSubs) . ' subscriptions past update threshold.<br />';
	return $delayedSubs;
}

// STEP 2: Get Classes With Active Subscriptions
function getClassesInSystem($subs) {
	global $sql, $DB;
	

	// Prepare the subscription list to search classes
	$i = 0;
	foreach ($subs as $sub => $val) {
		if ($i == 0) {
			$classSearch = $val['class'];
		} else {
			$classSearch .= ' , ' . $val['class'];
		}
		$i++;
	}
	
	
	// Get classes with active subscriptions
	// Future bug possibility - if class_id isn't unique, 'term' needs to be cross checked with sub
	$sql['get_classes'] = 'SELECT class_id, available_seats FROM classes WHERE class_id IN ( '.$classSearch.' )';
	$sql['get_classes'] = $DB->prepare($sql['get_classes']);
	$sql['get_classes']->execute();
	$classes = $sql['get_classes']->fetchAll();
	
	if (count($classes) == 0) {
		die('No active class/user pairs in system.');
	}
	
	echo 'Of those, ' . count($classes) . ' classes are in the system.<br />';
	
	// Figure out which have open seats and who is trying to get in
	$matched = array();
	foreach ($classes as $class => $val) {
		if ($val['available_seats'] > 0) {
			foreach ($subs as $sub => $sval) {
				if ($val['class_id'] == $sval['class']) {
					// Matched available class to user!
					$course = _getCourseInfo($val['class_id']);
					$matched[] = array(	'class_id' => $val['class_id'],
										'fb' => $sval['fb'],
										'seats' => $val['available_seats'],
										'course_prefix' => $course['course_prefix'],
										'course_number' => $course['course_number'] );
				}
			}
		}
	}
	
	echo 'Of those, ' . count($matched) . ' matched open seats to users.<br />';
	
	return $matched;
}

// STEP 3: Get User Info & Preferences for those to be notified
function getUserInfo($openSeatSubs) {
	if (count($openSeatSubs) > 0) {
		global $sql, $DB;
		
		// Prepare the subscription list to search classes
		$i = 0;
		foreach ($openSeatSubs as $sub => $val) {
			if ($i == 0) {
				$userSearch = $val['fb'];
			} else {
				$userSearch .= ' , ' . $val['fb'];
			}
			$i++;
		}
		
		$sql['get_users'] = 'SELECT * FROM users WHERE fbid IN ( '.$userSearch.' )';
		$sql['get_users'] = $DB->prepare($sql['get_users']);
		$sql['get_users']->execute();
		$users = $sql['get_users']->fetchAll();
		
		return $users;
	} else {
		return false;
	}
}

// STEP 4: Notify the user!
function notifySeatOpen($seats, $users) {
	foreach($seats as $seat => $sval) {
		foreach($users as $user => $uval) {
			if ($sval['fb'] == $uval['fbid']) {
				_notifyUser($sval, $uval);
			}
		}
	}
}

// Individual notification routine
function _notifyUser ($seat, $user) {
	if ($user['notify_pref'] == 1) {
		_textUser($seat, $user);
	} 
	elseif ($user['notify_pref'] == 2) {
		_emailUser($seat, $user);
	}
	elseif ($user['notify_pref'] == 3) {
		_textUser($seat, $user);
		_emailUser($seat, $user);
	} else {
		// log this
		echo "No preference set, unable to notify!";
	}
	
}

function _textUser ($seat, $user) {

	global $nexmo_sms, $sql, $DB;

	$message = $seat['course_prefix'].' '.$seat['course_number'].' ('.$seat['class_id'].') is open!';
				
	if ($seat['seats'] == 1) {
		$message .= " There's only one seat available so hurry!";
	} 
	elseif ($seat['seats'] <= 5) {
		$message .= " There's only ".$seat['seats']." seats available so hurry!";
	} else {
		$message .= " There are ".$seat['seats']." seats available.";
	}
	
	$message .= ' http://my.ucf.edu';
				
	echo "<br /><hr /><br />".$message."<br /><br />";
	
	// Send notification
	$info = $nexmo_sms->sendText( $user['phone'], NEXMO_NUMBER, $message );
	echo $nexmo_sms->displayOverview($info);
	
	// Log activity to SMS table	
	$to = $info->messages[0]->to;
	$messageid = $info->messages[0]->messageid;

	$sql['sms_log'] = 	'INSERT INTO  `sms` (  `direction` ,  `datetime` ,  `to` ,  `msisdn` ,  `messageId` ,  `text`, `type` )  VALUES
						 (\'outgoing\', NOW(), '.$to.', '.NEXMO_NUMBER.', '.$DB->quote($messageid).', '.$DB->quote($message).', 2)';
	
	$sql['sms_log'] = $DB->prepare($sql['sms_log']);
	$sql['sms_log']->execute();
	
	// Update subscription status
	$sql['update_sub_status'] = 'UPDATE subscriptions SET status=2 WHERE fb='.$user['fbid'].' AND class='.$seat['class_id'];
	$sql['update_sub_status'] = $DB->prepare($sql['update_sub_status']);
	$sql['update_sub_status']->execute();
	
	// Throttling so messages make it through
	sleep(1);
	
	// Send yes/no enrollment verification
	$message = "Text us back if you get in (yes/no) so we can update your account!";
	echo "<br />".$message."<br /><br />";
	$info = $nexmo_sms->sendText( $user['phone'], NEXMO_NUMBER, $message );
	echo $nexmo_sms->displayOverview($info);
	
	// Throttling so messages make it through
	sleep(1);
}

function _getCourseInfo($class) {
	global $sql, $DB;

	$sql['get_course_info'] = 'SELECT course_prefix, course_number FROM courses WHERE class_id='.$class;
	$sql['get_course_info'] = $DB->prepare($sql['get_course_info']);
	$sql['get_course_info']->execute();
	$course = $sql['get_course_info']->fetch();
	return $course;
}

function _updateLastCheck($subID) {
	global $sql, $DB;
	
	$sql['update_sub_update'] = 'UPDATE subscriptions SET updated=NOW() WHERE sub='.$subID;
	$sql['update_sub_update'] = $DB->prepare($sql['update_sub_update']);
	$sql['update_sub_update']->execute();
}