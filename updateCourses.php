<?php
	require_once("inc-database.php");
	define('URL', 'https://cs89.net.ucf.edu/psc/HEPROD/EMPLOYEE/HEPROD/c/COMMUNITY_ACCESS.CLASS_SEARCH.GBL');
	
	// Fake User-Agent (Firefox 9.0.1 on Windows 7)
	define('USER_AGENT', 'Mozilla/5.0 (Windows NT 6.1; rv:9.0.1) Gecko/20100101 Firefox/9.0.1');
	
	// Run function per term
	if(isset($_REQUEST['term'])) {
		if (isset($_REQUEST['dept'])) {
			getCourses($_REQUEST['term'],$_REQUEST['dept'], null);
		} 
		elseif(isset($_REQUEST['class'])) {
			$type = _searchType($_REQUEST['class']);
			$term = $_REQUEST['term'];
			if (($type == 'class_id') OR ($type == 'course') && (is_numeric($term))) {
				getCourses($term, null, $_REQUEST['class']);
			}
		} else {
			getCourses($_REQUEST['term'], null, null);
		}
	} else {
		$deptList = getCourses('0000', 'deptlist', null);
		echo '<ul>';
		foreach ($deptList as $dept => $val) {
			echo '<li><a href="?dept='.$val.'&term=1470">'.$val.'</a></li>';
		}
		echo '</ul>';
 	}
	
	function getCourses($term, $dept, $manualUpdate) {
		global $DB;

		define('TERM', $term);
		
		// PDO Setup / SQL statements
		$sql = array(
			'courses_insert' => 'INSERT INTO courses (class_id, course_number, course_prefix, course_title, instructor, date, class_capacity, term, times) VALUES (:class_id, :course_number, :course_prefix, :course_title, :instructor, NOW(), :class_capacity, :term, :times) ON DUPLICATE KEY UPDATE course_prefix = :course_prefix, course_title = :course_title, instructor = :instructor, class_capacity = :class_capacity, term = :term, times = :times',
			//'class_insert' => 'INSERT INTO DB_Class_12SP VALUES (NOW(), :class_id, :class_capacity, :available_seats) ON DUPLICATE KEY UPDATE date = NOW(), class_capacity = :class_capacity, available_seats = :available_seats'
		);
	
		foreach ($sql as $stmnt_name => $stmnt_sql) {
			$sql[$stmnt_name] = $DB->prepare($stmnt_sql);
		}

		list($ch, $cookie_file) = _ucf_init();
		
		curl_setopt($ch, CURLOPT_URL, URL);
		
		// STEP 1: Make initial form request to establish session and get ICSID value
		$result = curl_exec($ch);
	
		$departments = array();
	
		// Get departments
		if (preg_match('/<select.+?id=\'CLASS_SRCH_WRK2_ACAD_ORG\'.+?>(.+?)<\/select>/is', $result, $matches)) {
			if (preg_match_all('/value="(.+?)"/i', $matches[1], $matches_inner)) {
				$departments = $matches_inner[1];
			}
		}
	
		if (empty($departments)) {
			die('Failed to get a list of departments.');
		}
		
		if ($dept == 'deptlist') {
			return $departments;
		}
		
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
	
		// STEP 2: Choose Term
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
			'CLASS_SRCH_WRK2_STRM$142$' => TERM
		)));
	
		$result = curl_exec($ch);
	
		// Get new form state
		if (!preg_match('/oDoc\.win0\.ICStateNum\.value=([0-9]+)/is', $result, $matches)) {
			die('Failed to get form state.');
		}
	
		$state = trim($matches[1]);
		
		// STEP 2.5: Uncheck "Open Classes Only"
		curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query(array(
			'ICAJAX' => 1,
			'ICType' => 'Panel',
			'ICElementNum' => 0,
			'ICStateNum' => $state,
			'ICAction' => 'CLASS_SRCH_WRK2_SSR_OPEN_ONLY$chk',
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
			'CLASS_SRCH_WRK2_STRM$142$' => TERM,
			'CLASS_SRCH_WRK2_SSR_OPEN_ONLY$chk' => 'N'
		)));
	
		$result = curl_exec($ch);
	
		// Get new form state
		if (!preg_match('/oDoc\.win0\.ICStateNum\.value=([0-9]+)/is', $result, $matches)) {
			die('Failed to get form state.');
		}
	
		$state = trim($matches[1]);
	
		$total_courses = 0;
	
		// STEP 3: Get search results for each department (hit button "Search")
		
		if (isset($dept)) {
				unset($departments);
				$departments[] = $dept;
		} elseif (isset($manualUpdate)) {
			unset($departments);
			$departments[] = '';
		}
		
		foreach ($departments as $department) {
			$courses = array();
			
			if (isset($manualUpdate)) {
				$type = _searchType($manualUpdate);
				if ($type == 'class_id') {
					$action = 'CLASS_SRCH_WRK2_CLASS_NBR$120$';
					$query = $manualUpdate;
				} elseif ($type == 'course') {
					$action = 'CLASS_SRCH_WRK2_SUBJECT$71$';
					$action2 = 'CLASS_SRCH_WRK2_CATALOG_NBR$76$';
					$query = $manualUpdate;
					preg_match("/([a-zA-Z]+)(\\d+)/", str_replace(' ','', $query), $querySplit);
					$query = $querySplit[1]; // prefix
					$query2 = $querySplit[2]; // num
				}
			} else {
				$action = 'CLASS_SRCH_WRK2_ACAD_ORG';
				$query = $department;
			}
			
	
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
				 $action => $query,
				 $action2 => $query2
			)));
	
			$result = curl_exec($ch);
			
			//var_dump($result);
	
			$num_courses = preg_match_all('/<span.+?class=\'SSSHYPERLINKBOLD\'.+?>(.+?)<\/span>(.+?)<td\s+height=\'5\'/is', $result, $matches);
	
			if (!empty($num_courses)) {
	
				$total_courses += $num_courses;
	
				foreach ($matches[1] as $i => $title) {
	
					$title = trim($title);
					$course = array();
	
					// Get course ID and prefix from title
					if (preg_match('/^(\w+) (\w+) \- (.*)/i', $title, $matches_title)) {
						// Grouped course listing of sections by course name
	
						$course['prefix'] = $matches_title[1];
						$course['title'] = trim(strip_tags($matches_title[3]));
						$course['classes'] = array();
						
						preg_match_all('/<DIV.+?id=\'win0divDERIVED_CLSRCH_SSR_CLASSNAME_LONG\$[0-9]+?\'>(.+?)<td colspan=\'10\' rowspan=\'2\'  valign=\'top\' align=\'left\'>/is', $matches[2][$i], $sections);
						
						if (!empty($sections[0])) {
							// MULTIPLE SECTIONS
							// each section of a larger class name group
							foreach ($sections[0] as $id => $section) {

preg_match_all('/<span.+?id=\'MTG_DAYTIME\$[0-9]+\'>(.+?)<\/span>.+?<span.+?id=\'MTG_INSTR\$[0-9]+\'>(.+?)<\/span>.+?<span.+?id=\'FX_CLS_DET_WRK_CLASS_NBR\$[0-9]+\'>(.+?)<\/span>.+?<span.+?id=\'FX_CLS_DET_WRK_ENRL_CAP\$[0-9]+\'>(.+?)<\/span>.+?<span.+?id=\'FX_CLS_DET_WRK_AVAILABLE_SEATS\$(.+?)\'>(.+?)<\/span>/is', $section, $class_matches);
	
								foreach ($class_matches[0] as $c => $class_id) {
									$class = array();
									$class['instructor'] = trim(strip_tags($class_matches[2][$c]));
									$class['id'] = intval($class_matches[3][$c]);
									$class['capacity'] = intval($class_matches[4][$c]);
									$class['available'] = intval($class_matches[6][$c]);
									$class['times'] = trim(strip_tags($class_matches[1][$c]));
									
									$lastID = intval($class_matches[5][$c]);
			
									$course['classes'][$class['id']] = $class;
								
								}

							}
							$lastID = 1 + $lastID;
							preg_match('/<span.+?id=\'MTG_DAYTIME\$'.$lastID.'\'>(.+?)<\/span>.+?<span.+?id=\'MTG_INSTR\$'.$lastID.'\'>(.+?)<\/span>.+?<span.+?id=\'FX_CLS_DET_WRK_CLASS_NBR\$'.$lastID.'\'>(.+?)<\/span>.+?<span.+?id=\'FX_CLS_DET_WRK_ENRL_CAP\$'.$lastID.'\'>(.+?)<\/span>.+?<span.+?id=\'FX_CLS_DET_WRK_AVAILABLE_SEATS\$'.$lastID.'\'>(.+?)<\/span>/is', $matches[2][$i], $lastmatches);
							
							$class = array();
							$class['instructor'] = trim(strip_tags($lastmatches[2]));
							$class['id'] = intval($lastmatches[3]);
							$class['capacity'] = intval($lastmatches[4]);
							$class['available'] = intval($lastmatches[5]);
							$class['times'] = trim(strip_tags($lastmatches[1]));
	
	
							$course['classes'][$class['id']] = $class;
							
							}
							else {
								// SINGLE SECTION FOR THIS CLASS
								preg_match('/<span.+?id=\'MTG_DAYTIME\$[0-9]+\'>(.+?)<\/span>.+?<span.+?id=\'MTG_INSTR\$[0-9]+\'>(.+?)<\/span>.+?<span.+?id=\'FX_CLS_DET_WRK_CLASS_NBR\$[0-9]+\'>(.+?)<\/span>.+?<span.+?id=\'FX_CLS_DET_WRK_ENRL_CAP\$[0-9]+\'>(.+?)<\/span>.+?<span.+?id=\'FX_CLS_DET_WRK_AVAILABLE_SEATS\$[0-9]+\'>(.+?)<\/span>/is', $matches[2][$i], $classmatches);
							
							$class = array();
							$class['instructor'] = trim(strip_tags($classmatches[2]));
							$class['id'] = intval($classmatches[3]);
							$class['capacity'] = intval($classmatches[4]);
							$class['available'] = intval($classmatches[5]);
							$class['times'] = trim(strip_tags($classmatches[1]));
	
	
							$course['classes'][$class['id']] = $class;
							
							}
	
						$courses[$matches_title[2]] = $course; // [2] => 1101
						
					}
				}
			}
			
	
			// Update courses/classes DB
			foreach ($courses as $course_number => $course) {
	
				foreach ($course['classes'] as $class_id => $class) {
					echo '<strong>id</strong> '.$class_id.' <strong>coursenum</strong> '.$course_number.' <strong>prefix</strong> '.$course['prefix'].' <strong>title</strong> '.$course['title'].' <strong>times</strong> '.$class['times'].' <strong>instructor</strong> '.$class['instructor'].' <strong>capacity</strong> '.$class['capacity'].' <strong>term</strong> '.TERM."<br />".PHP_EOL;
	
					try {
						// Insert/update courses table entry
						
						$sql['courses_insert']->bindValue(':class_id', $class_id, PDO::PARAM_INT);
						$sql['courses_insert']->bindValue(':course_number', $course_number, PDO::PARAM_STR);
						$sql['courses_insert']->bindValue(':course_prefix', $course['prefix'], PDO::PARAM_STR);
						$sql['courses_insert']->bindValue(':course_title', $course['title'], PDO::PARAM_STR);
						$sql['courses_insert']->bindValue(':instructor', $class['instructor'], PDO::PARAM_STR);
						$sql['courses_insert']->bindValue(':class_capacity', $class['capacity'], PDO::PARAM_INT);
						$sql['courses_insert']->bindValue(':term', TERM, PDO::PARAM_INT);
						$sql['courses_insert']->bindValue(':times', $class['times'], PDO::PARAM_STR);
						$sql['courses_insert']->execute(); 
					} catch (Exception $e) {
						echo "Fail: ".$e->getMessage();
					}
					
					
	
					/* Insert/update classes table entry
					$sql['class_insert']->bindValue(':class_id', $class_id, PDO::PARAM_INT);
					$sql['class_insert']->bindValue(':class_capacity', $class['capacity'], PDO::PARAM_INT);
					$sql['class_insert']->bindValue(':available_seats', $class['available'], PDO::PARAM_INT);
					$sql['class_insert']->execute();
					*/ 
				}
			}
	
			echo 'Finished '.$department.' ('.$num_courses.")\n";
			flush();
	
			sleep(1);
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