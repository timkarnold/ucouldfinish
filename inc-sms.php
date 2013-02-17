<?php

// less interesting database stuff removed

function yesNo($text) {
	 // Lowercase and sanitize message
 	$msg = strtolower(trim($text));
	$msg = preg_replace('/[^a-z0-9 "\']/', '', $msg);
	
	// Synonyms arrays
	$yes_arr = array('yes', 'y', 'yup', 'yep', 'yeah', 'ya', 'i did');
	$no_arr = array('no', 'n', 'nope', 'did not', 'nah', 'didnt');
	
	if (in_array($msg, $yes_arr)) {
		return 'yes';
	}
	elseif (in_array($msg, $no_arr)) {
		return 'no';
	}
	else {
		return false;
	}
}