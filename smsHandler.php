<?php

require('inc-database.php');
require('inc-sms.php');

// Setup Nexmo
$sms = new NexmoMessage(NEXMO_API_KEY, NEXMO_API_SECRET);
define('NEXMO_NUMBER', 15555555555);

// Inbound text detected
 if ($sms->inboundText()) {
 
 	// Get info on this user
 	$user = lookupUser($sms->from);
 	
 	// Is message to confirm enrollment and end sub?
 	foreach ($user['classes'] as $class => $val) {
 		if (($val['status'] == 2) AND ($val['active'] == 1)) {
 			// Active subscription waiting for confirmation
 			$msgType = 'conf';
 			$class = $val['class'];
 			//$subscription = $val['sub'];
 		}
 	}
 	
 	if ($msgType == 'conf') {
 		// Determine if it was a yes or no
 		$yesNo = yesNo($sms->text);
 		
 		if ($yesNo == 'yes') {
 			_endSub($user['fbid'], $class);
 			logSMS(3, $sms); // Log the incoming text as confirmation
 		}
 		elseif ($yesNo == 'no') {
 			_reactivate_sub($user['fbid'], $class);
 			logSMS(4, $sms); // Log the incoming text as enrollment failed
 		}
 		$reply = confResponse($yesNo);
 	} else {
 		$reply = "Not sure what you're messaging about...";
 		logSMS(0, $sms); // Log the incoming text as unknown
 	}
	
	// Send the outgoing text
	echo $reply;
	$sms->reply($reply);
	
	
}

function confResponse($yesNo) {
	$reply = array(	'false' => 'Sorry, I didn\'t get that.. please use Yes or No responses.',
					'yes' => 'Great! Glad we could help. We took you off the list for this class. Thanks for using U Could Finish!',
					'no' => 'Oh no, sorry to hear it.. We\'ll keep looking for you and let you know when it opens again!' );		
					
	if ($yesNo == 'yes') { 
		return $reply['yes']; 
	}
	elseif ($yesNo == 'no') { 
		return $reply['no']; 
	} else { 
		return $reply['false']; 
	}
}