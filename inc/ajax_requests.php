<?php
/*************************************
/*	ajax_requests.php
/*	PURPOSE:  Specific to the FLO Wait Reduction
/*		project. Intended to process non-Admin
/*		AJAX requests for dialogs and dataTable objects.
/*	AUTHOR:		Michael Baxter
/*				michael@kp.org
/*	ORGANIZATION:
/*		Operations Information Management
/*		North Sacramento Valley TPMG
/****************************************/
include_once 'class.sqlidb.php';

$time_zone = ini_get('date.timezone');
date_default_timezone_set($time_zone);

if (isset($_SERVER['HTTP_X_REQUESTED_WITH'])
    AND strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') {
	// I'm AJAX!		
	$debug_it = false;
} else {
	// send debug statements to stymie hackers and 
	// enable debugging calls
	$debug_it = true;
}

$mySQLi = new MySQLi_DB($_sqli_conf);
$users = 'wb-users';
$user_meta = 'wb-user-meta';
$posts = 'wb-posts';
$post_meta = 'wb-post-meta';
$groups_table = 'wb-groups';
$appt_types = 'wb-appointment-types';

$viewColumns = array(
	'id'=>'ID',
	'appointment_date' => "Date",
	'provider' => "Provider",
	'location' => "Location",
	'ampm' => "AM/PM",
	'appointment_type' => "Appt Type",
	'notes' => "Notes"
);
$locations = array(
	"Sacramento"=>"Sacramento, Morse",
	"Roseville"=>"Roseville, Eureka"
);
function get_user_name($uid){
	global $users, $mySQLi;
	$sql = "SELECT CONCAT(lastname,', ',firstname) AS name FROM `$users` WHERE `$users`.`id`=$uid;";
	$names = $mySQLi->get_results($sql);
	if ($names === FALSE) {
		return '';
	}else{
		return $names[0]['name'];	
	}
}
function get_appt_types(){
	global $mySQLi, $appt_types;
	
	$sql = "SELECT * FROM `$appt_types` ORDER BY 'name';";
	$appts = $mySQLi->get_results($sql);
	
	if ($appts !== false && $mySQLi->error == ''){
		$out = '';
		foreach($appts as $row){
			$out[$row['id']] = $row['name'];	
		} // loop foreach appt
		return $out;
	} // end if appt found without error
	return NULL;
}
$appt_types = get_appt_types();


//**  General utility functions to support one or more of the active ajax functions  **/
function getMySQL_Today(){
	$phptime = strtotime('today');
	return date ("Y-m-d H:i:s", $phptime);
}
function getMySQL_Now(){
	$phptime = strtotime('now');
	return date ("Y-m-d H:i:s", $phptime);
}
function getMySQL_Midnight(){
	$phptime = strtotime('tomorrow');
	return date ("Y-m-d H:i:s", $phptime);
}
function toMySQLDate($date_in){
	$phptime = strtotime( $date_in );
	return date ("Y-m-d", $phptime);
}
function getHead($access_level){
	global $viewColumns;
	
	foreach($viewColumns as $key => $title){
		if ($key!='status') $aoCols[] = array('sTitle' => $title, "mData" => $key);
	}
	return $aoCols;
}
function getBody($data,$access_level){
	global $viewColumns, $myReturn, $appt_types, $locations;
	
	$aaData = array();
	$myReturn['body_data'] = $data;
	
	foreach($data as $row){
		$row['appointment_type'] = $appt_types[$row['appointment_type']];
		$row['location'] = $locations[$row['location']];
		
		$mysqldate = strtotime($row['appointment_date']);
		$row['appointment_date'] = date("d M Y", $mysqldate );
		$note = $row['notes'];
		$notes_words = explode(' ', $row['notes']);
		$row['notes'] = implode(' ', array_slice($notes_words, 0, 12));
		if (count($notes_words) >= 10 ) $row['notes'] .= '<br /><a class="more_note" title="'.$note.'" href="?action=view_notes&row='.$row['id'].'"><span style="font-size:-1">More ...</span></a>';
		$aaData[] = array_intersect_key($row, $viewColumns);
	}
	return $aaData;
}
//***	Primary AJAX functions, called by the SELECT CASE statements below, which evaluates	***//
//***	the requested ACTION and calls the appropriate function									***//
function get_table_data($access_level){
	global $mySQLi, $posts, $myReturn;
	
	
	$out['aoColumns'] = getHead($access_level);
	$qry = "SELECT * FROM `$posts` WHERE `$posts`.`type`='post' AND `$posts`.`status`='open' ORDER BY created;";
	$out['sql'] = $qry;	
	$result = $mySQLi->get_results($qry);
	$out['access_level'] = $access_level;
	$out['results'] = $result;
/*  For debugging only.  Passes security information for your MySQL connection	
	ob_start();
	var_dump($mySQLi);
	$out['mySQLi'] = ob_get_clean();
*/	
	if ($result !== false){
		$out['success'] = true;
		$out['aaData'] = getBody($result,$access_level);
	} else{
		$out['success'] = false;
		$out['tbody'] = '<tbody>';	
		$out['tbody'] .= '<tr><td colspan="'.((isset($access_level) && $access_level > 0) ? 6 : 7).'">';
		$out['tbody'] .= '(No records found)';
		$out['tbody'] .= '</td></tr>';
	} // end if results found;
	return $out;
} // end function get_table_data();
function add_entry($data){
	global $posts, $mySQLi;
	// be sure to add post meta. We at least need to record the author,
	// so we can work with security (user_access = 1 can only edit their own records)
	$out['success'] = false;
	$out['error'] = '';
	$access_level = $data['access_level']*1;
	$user_id = $data['user_id'];
	
	if ($access_level >= 1){
		unset($data['action']);
		unset($data['access_level']);
		unset($data['user_id']);
		$flds = array();
		$values = array();
		foreach($data as $key=>$value){
			$flds[] = str_replace('appt_','appointment_', $key);
			switch($key){
				case('appt_date'):
					$values[] = "'".date('Y-m-d H:i:s',strtotime($value))."'";
					break;	
				case('provider'):
					$values[] = "'".get_user_name($value)."'";
					break;
				default:
					$values[] = "'".$value."'";
					break;
			}
		}
		$flds[] = 'type';
		$values[] = "'post'";
		$flds[] = 'created';
		$values[] = 'CURRENT_TIMESTAMP';
		
		$sql = "INSERT INTO `$posts` (".implode(',',$flds).") VALUES (".implode(',',$values).");";
		$out['sql']=$sql;
		
		$myResults = $mySQLi->add_row($sql);
		$out['results'] = $myResults;
		if ($mySQLi->error != '') $out['error'] = $mySQLi->error_num.': '.$mySQLi->error;
		$post_id=$mySQLi->insert_id;
		if ($post_id != '' && $post_id != NULL )	{
			$out['success'] = true;
			$meta_results = update_post_meta($user_id,$post_id,NULL);
			$out = array_merge($out,$meta_results);
		}
			
	} else {
		$out['error'] = 'You have insufficient access';	
	}
	return $out;
}
function update_entry($data,$can_edit=false,$needs_lock=true){
	global $mySQLi, $posts, $post_meta,$viewColumns,$appt_types;
	$out['success'] = false;
	$lock_check = NULL;
	
	if ($can_edit===false)$can_edit = can_edit($data);
	if ($can_edit===true && $needs_lock==true) $lock_check = check_lock($data);
	
	if ($can_edit===false){
		$out['error'] = 'You have insufficient access to edit this record';
	}else if($needs_lock==true && $lock_check!==NULL && $lock_check['locked']==false){
		if(isset($lock_check['lock_error'])){
			$out['error'] = 'A problem was encountered while checking your right to update this record:<br />';
			$out['lock_error'] = $lock_check['error'];
		}else{
			$out['error'] = $lock_check['locked_by'].' has this record locked since '.$lock_check['locked_since'].'.  Please make note of your desired changes and try again later.';
		}
	}else{
		$post_id = $data['id'];
		$user_id = $data['user_id'];
		
		unset($data['action']);
		unset($data['access_level']);
		unset($data['id']);
		unset($data['user_id']);	
		
		$currSQL = "SELECT * FROM `$posts` WHERE `id`='$post_id'";
		$currRow = $mySQLi->get_results($currSQL);
		
		if ($currRow == false || $mySQLi->error!=''){
			$out['error'] =='Error '.$mySQLi->error_num.': '.$mySQLi->error;
			$out['currSQL']=$currSQL;
		}else{
			
			$orig = $currRow[0];
			unset($orig['id']);
			unset($orig['updated']);
		
			$orig['parent'] = $post_id;	
			$orig['type'] = 'revision';
			$flds = array_keys($orig);
			
			$buSQL = "INSERT INTO `$posts` (".implode(',',$flds).") VALUES ('".implode("','",$orig)."');";
			$out['buSQL'] = $buSQL;
	
			$backup = $mySQLi->add_row($buSQL);
			
			if ($mySQLi->error == ''){
				// be sure to set the parent, so it points to the original post
				$backupID = $mySQLi->insert_id;
				$out['backup_id']=$backupID;
				
				// if we're updating the status, we don't want to touch these fields
				$fldUpdate = (array_key_exists('status',$data) ? "status='".$data['status']."'" : '');
				if (array_key_exists('provider',$data)){
					$data['provider'] = get_user_name($data['provider']);
					$data['appointment_type'] = $data['appt_type'];
					$data['appointment_date'] = toMySQLDate($data['appt_date']);
					foreach($data as $key=>$value){
						if (array_key_exists($key,$viewColumns)) $fldUpdate .= ($fldUpdate==''?'':',')."$key='$value'";
					} // loop through all fields
				} // end if found provider field data
				
				if ($fldUpdate != '') { 
					$fldUpdate.=",updated=CURRENT_TIMESTAMP";
					$updateSQL = "UPDATE `$posts` SET $fldUpdate WHERE `$posts`.`id`='$post_id';";
					$out['updateSQL'] = $updateSQL;
					$updateRow = $mySQLi->update_row($updateSQL);
					if ($mySQLi->error != ''){
						$out['error'] = 'Error '.$mySQLi->error_num.': '.$mySQLi->error;	
					}else{
						$out['success'] = true;
						$meta_results = update_post_meta($user_id,$post_id,$backupID);
						if (is_array($meta_results) ) $out = array_merge($out,$meta_results);
					} // end if no error in query
				}else{
					$out['error'] = 'Could not update current record';
					$out['data'] = $data;
					$out['view_columns'] = $viewColumns;
				} // end if field updates have been initialized
			}else{
				$out['sql'] = $buSQL;		
				$out['error'] = 'Error '.$mySQLi->error_num.': '.$mySQLi->error;		
			} // end if last SQL query had an error
		} // end if we found requested record to update
	} // end if can_edit
	
	return $out;
} //end function updateEntry

function update_post_meta($user_id,$post_id,$backupID=NULL){
	global $mySQLi, $post_meta;
	
	// 
	if ($backupID==NULL){
		$post_sql = "INSERT INTO `$post_meta` (meta_key,meta_value,post_id,updated) VALUES ('author','$user_id','$post_id',CURRENT_TIMESTAMP);";
	}else{
		$post_sql = "INSERT INTO `$post_meta` (meta_key,meta_value,post_id,updated) VALUES ('updated_by','$user_id','$post_id',CURRENT_TIMESTAMP) ON DUPLICATE KEY UPDATE meta_value=VALUES(meta_value), updated=VALUES(updated);";
		$revision_sql = "INSERT INTO `$post_meta` (meta_key,meta_value,post_id,updated) VALUES ('author','$user_id','$backupID',CURRENT_TIMESTAMP);";
		$revision_meta_id = $mySQLi->add_row($revision_sql);
		if ($mySQLi->error !== '') $out['revision_meta_error'] = $mySQLi->error_num .': '.$mySQLi->error;
		$out['revision_meta_sql'] = $revision_sql;
	}
	$post_meta_id = $mySQLi->add_row($post_sql);
	if ($mySQLi->error !== '') $out['post_meta_error'] = $mySQLi->error_num .': '.$mySQLi->error;	
	
	if (isset($post_meta_id) && $post_meta_id!='') $out['post_meta_id'] = $post_meta_id;
	if (isset($revision_meta_id) && $revision_meta_id!='') $out['revision_meta_id'] = $revision_meta_id;
	$out['post_meta_sql'] = $post_sql;
	
	return $out;
}
// When users request a deletion, mark the document status as "trash"
function delete_entry($data){
	global $post_meta,$mySQLi;
	if (can_edit($data)===false){
		$out['error'] = 'You have insufficient access to delete this entry';
	}else{
		$data['status'] = 'trash';
		$post_id = $data['id'];
		$user_id = $data['user_id'];
		$out = update_entry($data, true, false);	
		
		if (!isset($out['error']) || $out['error']==''){	
			$sql = "INSERT INTO `$post_meta` (post_id,meta_key,meta_value,updated) VALUES ('$post_id','trash','$user_id',CURRENT_TIMESTAMP);";
			$ins = $mySQLi->add_row($sql);
			if ($ins === FALSE || $mySQLi->insert_id == 0) {
				$out['meta_data_error'] = "Failed to insert deletion metadata.";
			}else{
				//$out['empty_trash']=empty_user_trash(array('user_id'=>$user_id));
			}
		} // end if update process failed
	} // end if can edit
	return $out;
}
function book_entry($data){
	global $post_meta,$mySQLi;
	if (can_edit($data)===false){
		$out['error'] = 'You have insufficient access to edit this entry';
	}else{
		$post_id = $data['id'];
		$user_id = $data['user_id'];
		if (array_key_exists('status',$data)){
			$out = update_entry($data, false, true);		
		}else{
			$data['status'] = 'booked';
			$out = update_entry($data, true, false);	
		}
		if (!isset($out['error']) || $out['error']==''){
			$sql = "INSERT INTO `$post_meta` (post_id,meta_key,meta_value,updated) VALUES ('$post_id','booked','$user_id',CURRENT_TIMESTAMP);";
			$ins = $mySQLi->add_row($sql);
			if ($ins === FALSE || $mySQLi->insert_id == 0) $out['meta_data_error'] = "Failed to insert booking metadata.";
		} // end if update process failed
	} // end if can edit
	return $out;
}
function can_edit($data){
	global $posts, $post_meta, $mySQLi;

	$can_edit = false;
	
	$access_level = $data['access_level']*1;
	
	if ($access_level >= 4) $can_edit = true;
	
	if ($can_edit === false){
		$post_id = $data['id'];
		$user_id = isset($data['user_id']) ? $data['user_id']*1 : NULL;
	
		if (isset($user_id)  && $user_id!=0){			
			$sql = "SELECT * FROM `$post_meta` WHERE `$post_meta`.`meta_key`='author' AND `$post_meta`.`post_id`='$post_id' AND `$post_meta`.`meta_value`='$user_id';";
			
			$rows = $mySQLi->get_results($sql);
			
			// if you found a matching record can_edit=true
			if ($rows !== false) $can_edit = true;
		}
	} // end if can_edit because of access_level == false;
		
	if ($can_edit===false && (isset($data['user_id']) && $user_id!==0)){
		// get the selected provider's name
		$sql = "SELECT provider FROM `$posts` WHERE `id`='$post_id';";
		$results = $mySQLi->get_results($sql);
		
		// if you found a valid name (==no errors)
		if ($results!==FALSE){
			// compare current user name to selected provider
			$user_name = get_user_name($user_id);
			$provider = $results[0]['provider'];
			if ($provider == $user_name) $can_edit = true;
		} // end if we got posts
	} // end if can_edit because user is author == false

	return $can_edit;
}
function get_lock($user_id,$session_id,$post_id){
	global $mySQLi, $post_meta, $users;
	$lock_sql = "INSERT INTO `$post_meta` (post_id,meta_key,meta_value)  VALUES ($post_id,'lock','$user_id');";
	$result = $mySQLi->add_row($lock_sql);
	$now = strtotime('now');
	
	if ($mySQLi->insert_id == NULL || $mySQLi->insert_id == ''){
		$locked_sql = "SELECT CONCAT(u.firstname,' ',u.lastname) AS name,pm.updated FROM `$post_meta` pm INNER JOIN `$users` u ON u.id = pm.meta_value WHERE pm.meta_key='lock' AND pm.post_id='$post_id'";
		$results = $mySQLi->get_results($locked_sql);
		if ($results===false){
			$out['lock_sql'] = $lock_sql;
			$out['locked_sql']=$locked_sql;
			$out['locked_by'] = 'Could not obtain a lock';
		}else{
			
			$_now = date('F d, Y H:i:s',$now);
			$_locked_time = date('F d, Y H:m:i',strtotime($results[0]['updated']));
			
			$locked_since = strtotime($results[0]['updated']);
			
			$time_passed = $now - $locked_since;
			
			$minutes_passed = $time_passed / 60;
			
			$minutes_locked = round(abs($minutes_passed),2);
			
			$out['minutes_locked'] = $minutes_locked;
			$out['locked_by'] = $results[0]['name'];
			$out['locked_time'] = date( 'F d, Y H:i:s',strtotime($results[0]['updated']) );
		} // end if got results
	}else{
		$out['locked_by'] = $user_id;
		$out['locked_time'] = $now;
	} // end if lock inserted
	return $out;	
} // end function get lock
function check_lock($data){
	global $mySQLi, $post_meta, $users;
	
	$user_id = $data['user_id'];
	$post_id = $data['id'];
	$out['locked'] = false;
	
	$lock_sql = "SELECT meta_value,updated FROM `$post_meta` WHERE post_id='$post_id' AND `$post_meta`.`meta_key`='lock';";
	$results = $mySQLi->get_results($lock_sql);
	
	if ($mySQLi->error!=='') { 
		$out['locked_error']=$mySQLi->error_num.": ".$mySQLi->error;
	} elseif($mySQLi->row_count==0) {
		$out['locked_error']='This record has not been locked.  You cannot updated it.';
	}else{
		$locked_by = $results[0]['meta_value'];
		if ($locked_by != $user_id){
			$out['locked_by'] = $locked_by;
			$out['locked_since'] = $results[0]['updated'];
			$out['lock_check_sql'] = $lock_sql;
			$out['lock_check_results'] = $results;
		} else {
			$out['locked'] = true;
		} // edn if locked by user
	} // if got query results
	return $out;
}
function release_lock($data){
	global $mySQLi, $post_meta;
	
	$user_id = $data['user_id'];
	$post_id = $data['id'];
	$out['error'] = '';
	
	$release_sql = "DELETE FROM `$post_meta` WHERE `post_id`='$post_id' AND `meta_key`='lock' AND `meta_value`='$user_id';";
	
	$out['release_sql']=$release_sql;
	
	$mySQLi->delete_row($release_sql);
	if ($mySQLi->error !='') $out['error'] = $mySQLi->error_num.': '.$mySQLi->error;
//	delete_revisions(array('id'=>$post_id));
	
	return $out;
}
// Retrieve data for a specific row.
// most likely use ... Edit display
// IMPORTANT: 
// be sure to retrieve author information for use
// with security.
function get_entry($data){
	global $mySQLi, $posts, $post_meta, $appt_types;
	
	unset($data['action']);
	
	$user_id = $data['user_id'];
	$session_id = $data['session_id'];
	$post_id = $data['id'];
	$can_edit = can_edit($data);
	
	unset($data['user_id']);
	unset($data['id']);
	unset($data['session_id']);
	unset($data['access_level']);
	
	$out['success'] = false;
	$out['can_edit'] = $can_edit;
	
	$post_sql = "SELECT * FROM `$posts` WHERE `$posts`.`id`=$post_id;";
	$meta_sql = "SELECT * FROM `$post_meta` WHERE `$post_meta`.`post_id` = $post_id;";
	
	$out['post_sql'] = $post_sql;
	$out['meta_sql'] = $meta_sql;
	
	if ($can_edit === true) $out = array_merge($out, get_lock($user_id,$session_id,$post_id));
	$row = $mySQLi->get_results($post_sql);
	
	if ($row === false || $mySQLi->error != ''){
		$out['error'] = 'Error '.$mySQLi->error_num.': '.$mySQLi->error; 
	}else{
		if ($mySQLi->row_count == 0){
			$out['error'] = 'No records found';
		}else{
			$myFlds = $row[0];
			$mySQLDate = strtotime($myFlds['appointment_date']);
			$myFlds['appointment_date'] =  date("m/d/Y", $mySQLDate);
			// get the meta values for this record.
			$meta_rows = $mySQLi->get_results($meta_sql);
			if ($mySQLi->error !== '') {
				$out['meta_error'] = $mySQLi->error_num.": ".$mySQLi->error;
			} else {
				foreach($meta_rows as $meta_row){
					$myFlds[$meta_row['meta_key']] = $meta_row['meta_value'];
				}
			}			
			$out['success'] = true;
			$out['fields']=$myFlds;
		} // end if found entries
	} // end if error
	return $out;
}
// mvb (todo) - Still working out the algorithms ... 11/19
function delete_revisions($data){
	global $mySQLi, $posts, $post_meta;
	
	$post_id = $data['id'];
	$posts_sql = "DELETE p1 FROM `$posts` p1 INNER JOIN (SELECT id FROM `$posts` WHERE parent='$post_id' ORDER BY id DESC LIMIT 5,100) AS p2 ON p2.id=p1.id";
	$meta_sql = "DELETE pm FROM  `$post_meta` pm LEFT JOIN  `$posts` p ON p.id = pm.`post_id` WHERE p.id IS NULL";
	
	$p_del = $mySQLi->delete_row($posts_sql);
	if ($mySQLi->row_count > 0) $pm_del = $mySQLi->delete_row($meta_sql);
}
function log_user_access($data){
	global $mySQLi, $user_meta;
	
	if (isset($data['user_id']) && $data['user_id']!==''){
		$qry = "INSERT INTO `$user_meta` (user_id,meta_key,meta_value) VALUES ('".$data['user_id']."','last_accessed', CURRENT_TIMESTAMP) ".
			"ON DUPLICATE KEY UPDATE meta_value=CURRENT_TIMESTAMP;";
		
		$myResults = $mySQLi->add_row($qry);
		$out['results'] = $myResults;
		if ($mySQLi->error != '') $out['error'] = $mySQLi->error_num.': '.$mySQLi->error;
		return $out;
	}	
}
function empty_user_trash($data){
	global $mySQLi, $posts, $post_meta;
	
	$user_id=$data['user_id'];
	$today = date('Y-m-d',strtotime('today'));
	$out['today'] = $today;
	if (!isset($_SESSION['trash_dumped']) || $_SESSION['trash_dumped'] != $today){
		$ten_days_ago = date('Y-m-d',strtotime('-10 day'));
		$out['ten_days_ago'] = $ten_days_ago;
		// delete the current users trashed posts that were trashed more than 10 days ago
		// ... and their respective children posts.
		$trash_sql = "DELETE FROM `$posts` WHERE `$posts`.`id` IN (SELECT to_trash.id FROM `$post_meta` pm INNER JOIN(".
						"SELECT p1.* FROM `$posts` p1 INNER JOIN ".
						"(SELECT p2.* FROM `$posts` p2 WHERE p2.status='trash' ".
						"UNION DISTINCT ".
						"SELECT p4.* FROM `$posts` p3 JOIN `$posts` p4 ".
						"ON p3.id=p4.parent WHERE p3.status='trash' AND p3.updated < '$ten_days_ago' ".
						"ORDER BY id ASC) AS p5 ".
						"ON p1.id = p5.id) AS to_trash ON to_trash.id=pm.post_id ".
						"WHERE pm.meta_key='author' AND meta_value='$user_id');";
		// delete orphaned meta data				
		$meta_sql = "DELETE pm FROM  `$post_meta` pm LEFT JOIN  `$posts` p ON p.id = pm.`post_id` WHERE p.id IS NULL";
		
		$p_del = $mySQLi->delete_row($trash_sql);
		if ($mySQLi->row_count > 0) $pm_del = $mySQLi->delete_row($meta_sql);
		if ($mySQLi->error == '') {
			$m_del = $mySQLi->delete_row($meta_sql);
			if ($mySQLi->error == ''){
				$_SESSION['trash_dumped'] = $today;			
			} else {
				$out['trash_meta_error'] = $mySQLi->error_num.": ".$mySQLi->error;		
			}
		} else {
			$out['trash_error'] = $mySQLi->error_num.": ".$mySQLi->error;	
		}
		
		$out['trash_sql'] = $trash_sql;
		$out['meta_sql']=$meta_sql;
	};
	return $out;
} // end function
//***	Primary control.  Calls the function that matches the requested ACTION ***//
if (isset($_POST['action']) && $_POST['action']!=''){
	
	switch($_POST['action']){
		case 'add_entry':
			$myReturn = add_entry($_POST);
			break;
		case 'update_entry':
			if (isset($_POST['status'])){
				$myReturn = update_entry($_POST,true,false);
			}else{
				$myReturn = update_entry($_POST,false,true);
			}
			break;
		case 'delete_entry':
			$myReturn = delete_entry($_POST);
			break;
		case 'book_entry':
			$myReturn = book_entry($_POST);
			break;
		case 'get_entry':
			$myReturn = get_entry($_POST);
			if (isset($myReturn['minutes_locked']) && $myReturn['minutes_locked'] > 31){
				$data = array('user_id'=>$myReturn['fields']['lock'],'id'=>$myReturn['fields']['id']);
				release_lock($data);
				$myReturn = get_entry($_POST);	
			}
			break;
		case 'get_table_data':
			$myReturn = get_table_data($_POST['access_level']*1);
			break;
		case 'get_groups':
			$myReturn['groups'] = get_groups($_POST['nuid']);
			break;
		case 'release_lock':
			$myReturn = release_lock($_POST);
			break;
		case 'delete_revisions':
			$myReturn = delete_revisions($_POST);
			break;
		default:
			$myReturn['error'] = 'Not prepared to handle action "'.$_POST['action'].'"';
			break;
	} // end switch
	$myReturn['action'] = $_POST['action'];
	$myReturn['last_accessed'] = log_user_access($_POST);	
} else {
					
	$myReturn['error'] = 'No action requested.';

} // end if get toolbar

// output whatever string was built by the SELECT CASE statement above  //
echo json_encode($myReturn);
die();

?>