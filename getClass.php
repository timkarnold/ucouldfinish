<?php
	header('Content-type: text/plain');
	header("Cache-Control: no-cache, must-revalidate"); // HTTP/1.1
	header("Expires: Sat, 26 Jul 1997 05:00:00 GMT"); // Date in the past

	// Fake User-Agent (Firefox 9.0.1 on Windows 7)
	define('USER_AGENT', 'Mozilla/5.0 (Windows NT 6.1; rv:9.0.1) Gecko/20100101 Firefox/9.0.1');
	
	// GET Term and Class
	if((isset($_REQUEST['class'])) && (isset($_REQUEST['term']))) {
		getClass($_REQUEST['class'], $_REQUEST['term']);
	} else {
		echo "vars not specified";
	}
	
	function getClass($class_id, $term) {
	
		define('URL', 'https://cs89.net.ucf.edu/psc/HEPROD/EMPLOYEE/HEPROD/c/COMMUNITY_ACCESS.CLASS_SEARCH.GBL');

		list($ch, $cookie_file) = _ucf_init();
		
		curl_setopt($ch, CURLOPT_URL, URL);
		
		// STEP 1: Make initial form request to establish session and get ICSID value
		$result = curl_exec($ch);
		
		// ICSID
		if (!preg_match('/<input.+?name=\'ICSID\'.+?value=\'(.+?)\'/is', $result, $matches)) {
			die('Failed to get session ID (ICSID).');
		}
	
		$ICSID = trim($matches[1]);
		
		// Get initial form state
		if (!preg_match('/<input.+?name=\'ICStateNum\'.+?value=\'(.+?)\'/is', $result, $matches)) {
			die('Failed to get form state.');
		}
	
		$state = trim($matches[1]);
		
		// All requests from now on will be POST
		curl_setopt($ch, CURLOPT_POST, true);
		
		// STEP 2: Choose Term (MyUCF Refreshes after)
		curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query(array(
			'ICAJAX' => 1,
			'ICType' => 'Panel',
			'ICElementNum' => 0,
			'ICStateNum' => $state,
			'ICAction' => 'CLASS_SRCH_WRK2_STRM$142$',
			'ICXPos' => 0,
			'ICYPos' => 0,
			'ICFocus' => '',
			'ICSaveWarningFilter' => 0,
			'ICChanged' => -1,
			'ICResubmit' => 0,
			'ICSID' => $ICSID,
			'ICModalWidget' => 0,
			'ICZoomGrid' => 0,
			'ICZoomGridRt' => 0,
			'ICModalLongClosed' => '',
			'ICActionPrompt' => 'false',
			'ICFind' => '',
			'ICAddCount' => '',
			'CLASS_SRCH_WRK2_STRM$142$' => $term
		)));
	
		$result = curl_exec($ch);
	
		// Get new form state
		if (!preg_match('/oDoc\.win0\.ICStateNum\.value=([0-9]+)/is', $result, $matches)) {
			die('Failed to get form state.');
		}
	
		$state = trim($matches[1]);
		
		// STEP 3: Fill out form and search
	
			curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query(array(
				'ICAJAX' => 1,
				'ICType' => 'Panel',
				'ICElementNum' => 0,
				'ICStateNum' => $state,
				'ICAction' => 'CLASS_SRCH_WRK2_SSR_PB_CLASS_SRCH',
				'ICXPos' => 0,
				'ICYPos' => 578,
				'ICFocus' => '',
				'ICSaveWarningFilter' => 0,
				'ICChanged' => -1,
				'ICResubmit' => 0,
				'ICSID' => $ICSID,
				'ICModalWidget' => 0,
				'ICZoomGrid' => 0,
				'ICZoomGridRt' => 0,
				'ICModalLongClosed' => '',
				'ICActionPrompt' => 'false',
				'ICFind' => '',
				'ICAddCount' => '',
				'CLASS_SRCH_WRK2_CLASS_NBR$120$' => $class_id
			)));
	
			$result = curl_exec($ch);
			
			// echo $result;
			
			if (preg_match_all('/The search returns no results that match the criteria specified./', $result, $matches)) {
				echo '0';
			}
			
			if (!preg_match_all('/<span.+?class=\'SSSHYPERLINKBOLD\'.+?>(.+?)<\/span>(.+?)<td\s+height=\'5\'/is', $result, $matches)) {
				die('Failed to find class in search.');
			}
			
			//<span class="PSEDITBOX_DISPONLY" id="FX_CLS_DET_WRK_AVAILABLE_SEATS$0">6</span>
			
			if (preg_match_all('/<span.+?id=\'FX_CLS_DET_WRK_AVAILABLE_SEATS\$[0-9]+\'>(.+?)<\/span>/', $result, $seats)) {
				echo $seats[1][0];
			}
					
		_ucf_finish($ch, $cookie_file);
		
	}
		

	// Internal: Initialize UCF compatible cURL handle
	function _ucf_init() {

		// Temp cookie jar file
		$cookie_file = tempnam(sys_get_temp_dir(), 'cookie');

		// Default cURL options
		$curl_options = array();
		$curl_options[CURLOPT_CONNECTTIMEOUT] = 900;
		$curl_options[CURLOPT_TIMEOUT] = 3600;
		$curl_options[CURLOPT_RETURNTRANSFER] = true;
		$curl_options[CURLOPT_FOLLOWLOCATION] = true;
		$curl_options[CURLOPT_AUTOREFERER] = true;
		$curl_options[CURLOPT_SSL_VERIFYPEER] = false;
		$curl_options[CURLOPT_SSL_VERIFYHOST] = false;
		$curl_options[CURLOPT_USERAGENT] = USER_AGENT;
		$curl_options[CURLOPT_COOKIEJAR] = $cookie_file;
		$curl_options[CURLOPT_ENCODING] = ''; // Accept-Encoding header (empty string for all supported encodings)

		// Initialize cURL handle (session)
		$ch = curl_init();

		curl_setopt_array($ch, $curl_options);

		return array($ch, $cookie_file);
	}

	// Internal: Finalize/finish cURL session
	function _ucf_finish($ch, $cookie_file) {

		// End cURL session
		curl_close($ch);
		@unlink($cookie_file);
	}

	// Internal: Get common UCF website POST parameters
	function _ucf_common_post() {

		return array(
			'ICAJAX' => 1,
			'ICType' => 'Panel',
			'ICElementNum' =>	0,
			'ICXPos' => 0,
			'ICYPos' => 0,
			'ICFocus' => '',
			'ICSaveWarningFilter' => 0,
			'ICChanged' => -1,
			'ICResubmit' => 0,
			'ICModalWidget' => 0,
			'ICZoomGrid' => 0,
			'ICZoomGridRt' => 0,
			'ICModalLongClosed' => '',
			'ICActionPrompt' => 'false',
			'ICFind' => '',
			'ICAddCount' => ''
		);
	}