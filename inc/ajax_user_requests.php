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

$mySQLi = new MySQLi_DB($_sqli_conf);
$myReturn = array();
$users = 'wb-users';
$user_meta = 'wb-user-meta';
$group_db = 'wb-groups';

$viewColumns = array(
	'id'=>'User ID',
	'nuid'=>'NUID',
	'username' => "Name",
	'date_last_accessed' => "Date of Last Activity",
	'last_accessed' => "Last Activity"
);

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
function getLast_Accessed($id,$today){
	global $mySQLi,$user_meta;

	$meta_sql = "SELECT meta_value as last_accessed FROM `$user_meta` WHERE `$user_meta`.`user_id`='$id' AND `$user_meta`.`meta_key`='last_accessed' ORDER BY `$user_meta`.`meta_value` DESC;";
	$meta_rows = $mySQLi->get_results($meta_sql);
	$last_accessed['date'] = '';
	
	if ($mySQLi->error == '') {
	    if ($mySQLi->row_count > 0){
    		$last_accessed_text = $meta_rows[0]['last_accessed'];
    		$time_last_accessed = strtotime($last_accessed_text);
    		$last_accessed['text'] = $last_accessed_text;	
    
    		
    		if ($last_accessed_text != '') {
    			$last_accessed['text'] = (date('m-d-Y',$today)==date('m-d-Y',$time_last_accessed) ? 'Today ' : date('d M Y',$time_last_accessed)).
    				' '.date('g:i:s a',$time_last_accessed);
    			$last_accessed['date'] = date('Y/m/d H:i:s',$time_last_accessed);
    		} // end if last_accessed_text
		} // end if row_count > 0
	} else {
		$last_accessed['text'] = $mySQLi->error_num.': '.$mySQLi->error;
	}

	return $last_accessed;
}
//***	Primary AJAX functions, called by the SELECT CASE statements below, which evaluates	***//
//***	the requested ACTION and calls the appropriate function									***//
function get_table_data($user_access=0){
	global $mySQLi,$users,$user_meta;

	$sql = "SELECT * FROM `$users` u WHERE u.deleted='0' ORDER BY u.lastname,u.firstname;";
	$rows = $mySQLi->get_results($sql);
	$out['sql'] = $sql;
	$out['success']=true;
	$out['aoColumns'] = getHead($user_access);
	
	if ($mySQLi->error !== ''){
		$out['tbody'] = '<tbody><tr><td colspan="4">(ERROR ('.$mySQLi->error_num.'): '.$mySQLi->error.')</td></tr></tbody>';
	}else{
		if ($mySQLi->row_count == 0){
			$out['tbody'] = '<tbody><tr><td colspan="4">(No users defined)</td></tr></tbody>';
		}else{
			$out['tbody'] ='<tbody>';
			$today = strtotime('today');
			foreach($rows as $user){
				$last_accessed = getLast_Accessed($user['id'],$today);
				$out['aaData'][] = array(
					'id'=>$user['id'],
					'nuid'=>$user['nuid'],
					'username'=>$user['lastname'].', '.$user['firstname'],
					'date_last_accessed'=> $last_accessed['date'],
					'last_accessed'=>(isset($last_accessed['text']) ? $last_accessed['text'] : '')
					);
				$out['tbody'] .= '<tr user_id="'.$user['id'].'"><td class="nuid">'.$user['nuid'].'</td><td class="username">'.$user['lastname'].', '.$user['firstname'].'</td>';
					
				if($user_access== 10) 
				$out['tbody'] .= '<td class="actions"><a href="?action=edit&id='.$user['id'].
					'"  title="Edit '.$user['lastname'].', '.$user['firstname'].'" class="button edit"><img src="/utility/gi-whiteboard/img/btn-edit-user.png" /></a>'.
					'<a href="?action=delete&id='.$user['id'].'" title="Delete '.$user['lastname'].', '.$user['firstname'].'" class="button delete"><img src="/utility/gi-whiteboard/img/btn-delete-user.png" />'.
					'</a></td>';
					
				$out['tbody'] .= '</tr>';
			} // loop foreach user
			$out['tbody'] .= '</tbody>';
			return $out;
		} // end if error
	} // end if nothing found
} // end function
function getHead($access_level){
	global $viewColumns;
	
	foreach($viewColumns as $key => $title){
		if ($key!='status') $aoCols[] = array('sTitle' => $title, "mData" => $key);
	}
	return $aoCols;
}
function get_groups($nuid){
	global $mySQLi, $user_meta, $users, $group_db;
	
	$grp_qry = "SELECT * FROM `$group_db`;";
	$groups = $mySQLi->get_results($grp_qry);
	if ($mySQLi->error !=='' && $mySQLi->row_count < 1){
		$out = '( Error ('.$mySQLi->error_num.'): '.$mySQLi->error.' )';	
	}else if($mySQLi->row_count == 0){
		$out = '( No groups defned )';	
	} else {
		$out = '<ul style="list-style: none outside none !important;">';
		if ($nuid == ''){
			foreach($groups as $gp){
				$out .= '<li>';
				$out .= '<input type="checkbox" name="groups" id="group'.$gp['id'].'" value="'.$gp['name'].'" />';
				$out .= ' <label for="group'.$gp['id'].'" style="padding-left:15px">'.ucfirst($gp['name']).'</label></li>'."\n";
			}
		} else {
			$usr_qry = "SELECT um.* FROM `$user_meta` um INNER JOIN `$users` u ON u.id = um.user_id WHERE u.nuid='$nuid' AND um.meta_key='group_membership';";
			$meta_data = $mySQLi->get_results($usr_qry);
			$user_groups = array();
					
			if ($mySQLi->error == '' && $mySQLi->row_count > 0){
				foreach($meta_data as $meta_row){
					$meta_groups = json_decode($meta_row['meta_value']);
					$user_groups = array_merge($user_groups, $meta_groups);
				}
			} // end if user meta found
			foreach($groups as $gp){
				$out .= '<li>';
				$out .= '<input type="checkbox" name="groups" id="group'.$gp['id'].'" value="'.$gp['name'].
					( in_array( strtolower($gp['name']), $user_groups ) ? ' checked="checked" ' : ''). '" />';
				$out .= '<label for="'.$gp['id'].'" style="margin-left:15px;">'.ucfirst($gp['name']).'</label>'."</li>\n";
			}		
		} // end if no nuid
	} // end if error
	return $out;
}
function add_user($user_data){
	global $mySQLi,$users, $user_meta;
	
	if (isset($user_data['action'])) unset($user_data['action']);
	$out['user_data'] = $user_data;
	$nuid = $user_data['nuid'];
	$firstname = addslashes(trim($user_data['firstname']));
	$lastname = addslashes(trim($user_data['lastname']));
	$username = addslashes(trim($firstname.' '.$lastname));
	$user_exists = get_user_id($nuid) == NULL ? false : true;
	// unset the fields we use so the rest
	// can be passed to set meta values
	unset($user_data['nuid']);
	unset($user_data['firstname']);
	unset($user_data['lastname']);
	
	if ($user_exists){
		$out['success']=false;
		$out['error']='A user with that NUID already exists.';
	}else{
		$qry = "INSERT INTO `$users` (nuid,firstname,lastname,deleted) VALUES ('$nuid','$firstname','$lastname',0) ON DUPLICATE KEY UPDATE firstname=VALUES(firstname),lastname=VALUES(lastname), deleted=VALUES(deleted)";
		$ins = $mySQLi->add_row($qry);
		$out['sql'] = $qry;
		
		if ($mySQLi->error !== ''){
			$out['error'] = '<p>Error ('.$mySQLi->error_num.'): '.$mySQLi->error."</p>\n";
			$out['success'] = false;
		} else {
			$out['error'] = '';
			$out['success'] = true;
			// only set meta values if the rest succeeded
			$meta_out = add_user_meta($nuid,$user_data);
			$out = array_merge($out,$meta_out);
		} // end if insert error is blank
	} // end if user exists
	return $out;
}
function update_user($user_data){
	global $mySQLi,$users;
	
	if (isset($user_data['action'])) unset($user_data['action']);
	$out['user_data'] = $user_data;
	$id = $user_data['id'];
	$nuid = $user_data['nuid'];
	$firstname = addslashes(trim($user_data['firstname']));
	$lastname = addslashes(trim($user_data['lastname']));
	$username = trim($firstname.' '.$lastname);
	// unset the fields we use so the rest
	// can be passed to set meta values
	unset($user_data['nuid']);
	unset($user_data['fistname']);
	unset($user_data['lastname']);	
	
	//UPDATE MyGuests SET lastname='Doe' WHERE id=2"
	$qry = "UPDATE `$users` SET nuid='$nuid', firstname='$firstname', lastname='$lastname' WHERE `$users`.`id`='$id';";
	$ins = $mySQLi->update_row($qry);
	$out['sql'] = $qry;
	
	if ($mySQLi->error !== ''){
		$out['error'] = '<p>Error ('.$mySQLi->error_num.'): '.$mySQLi->error."</p>\n";
		$out['success'] = false;
	} else {
		$out['error'] = '';
		$out['success'] = true;
		// only set meta values if the rest succeeded
		$meta_out = add_user_meta($nuid,$user_data);
		$out = array_merge($out,$meta_out);
	}
	return $out;
}
function delete_user($id){
	global $mySQLi,$users,$user_meta;
	$qry = "DELETE u,um FROM `$users` AS u, `$user_meta` AS um WHERE u.`id`=um.`user_id` AND u.`id`='$id';";
	$out['sql'] = $qry;
	
	$del = $mySQLi->delete_row($qry);
	
	if ($mySQLi->error == ''){
		$out['success'] = true;
	} else {
		$out['error'] = '('.$mySQLi->error_num.')'.$mySQLi->error;
		$out['success']=false;
	}
	return $out;
}
function get_formatted_name($name_in){
	$parts = explode(' ',$name_in);
	$uc_parts = array();
	$out = '';
	foreach($parts as $p){
		$uc_parts[] = ucfirst($p);
	}
	$out = implode(' ', $uc_parts);
	return $out;
}
function add_user_meta($nuid,$meta_data){
	global $mySQLi,$users, $user_meta;
	
	// assume we're going to succeed and correct if necessary
	$out['user_meta'] = $meta_data;
	$out['meta_nuid'] = $nuid;
	$user_id = get_user_id($nuid);
	
	$out['meta_success'] = true;
	$out['meta_error'] = '';	

	$out['meta_user_id'] = $user_id;
	if (isset($meta_data['user_id']) ) unset($meta_data['user_id']);

	//loop counter
	$r = 1;
	foreach($meta_data as $meta_key =>$meta_value){
		if ($meta_key == 'groups'){
			$out['groups']=$meta_value;
			if (stristr($meta_value,',') === false){
				$groups[0] = trim($meta_value);
			} else {
				$groups = explode(',',$meta_value);
			}
			$meta_value = serialize($groups);
		}else{
			$meta_value = addslashes($meta_value);
		} // end if groups found
		$sql = "INSERT INTO `$user_meta` (user_id,meta_key,meta_value) VALUES ($user_id,'$meta_key','$meta_value') ON DUPLICATE KEY UPDATE meta_key=VALUES(meta_key),meta_value=VALUES(meta_value)";
		$out['meta_qry'.$r++] = $sql;
		$ins = $mySQLi->add_row($sql);
		
		if ($mySQLi->error == ''){
			$out[$meta_key] = $meta_value;
			$out[$meta_key.'_sql'] = $sql;
		}else{
			$out['meta_success'] = false;
			$out['meta_error'] = $mySQLi->error;
			break;
		}
	} // loop foreach meta
	return $out;
}
function get_user_data($id){
	global $mySQLi,$users,$user_meta,$myReturn;
	
	$sql = "SELECT * FROM `$users` WHERE `$users`.`id`='$id';";
	$user_data = $mySQLi->get_results($sql);
	$out['main_sql'] = $sql;
	
	if ($mySQLi->error ==''){
		foreach($user_data as $user){
			$out['user'] = $user;
			$out['firstname'] = $user['firstname'];
			$out['lastname'] = $user['lastname'];
			$out['name'] = $user['lastname'].', '.$user['firstname'];
			$out['fullname'] = trim($user['firstname'].' '.$user['lastname']);			
			$out['nuid'] = $user['nuid'];
			$out['deleted'] = $user['deleted'];
		}
		$out['success'] = true;		
		$meta_sql = "SELECT * FROM `$user_meta` WHERE `$user_meta`.`user_id`='$id';";
		$out['meta_sql'] = $meta_sql;
		$meta_data = $mySQLi->get_results($meta_sql);
		$out['meta_rows'] = $mySQLi->row_count;
		if ($mySQLi->error == '' && $mySQLi->row_count > 0){
			$r = 1;
			foreach ($meta_data as $meta){
				$out['meta'.$r++] = $meta;
				$meta_value = ($meta['meta_key'] == 'groups' ? unserialize($meta['meta_value']) : $meta['meta_value']);
				$out[$meta['meta_key']] = $meta_value;	
			}
		}
	} else {
		$myReturn['get_user_data'] = $sql;
		$out['error'] = "(".$mySQLi->error_num."):".$mySQLi->error;
		$out['success'] = false;
	}
	return $out;
}
function get_user_id($nuid){
	global $mySQLi,$myReturn,$users;
	
	$sql = "SELECT id FROM `$users` WHERE LCASE(`$users`.`nuid`)=LCASE('$nuid') LIMIT 1;";
	$myReturn['uid_sql'] = $sql;
	
	$rows = $mySQLi->get_results($sql);
	if ($mySQLi->error !== '' || $mySQLi->row_count < 1){
		return NULL;
	}else{
		$myReturn['uid_found'] = $rows[0];
		return $rows[0]['id'];
	}
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
//***	Primary control.  Calls the function that matches the requested ACTION ***//
if (isset($_POST['action']) && $_POST['action']!=''){
	$myAction = $_POST['action'];
	
	switch($myAction){
		case 'get_table_data':
			$myReturn['table_data'] = get_table_data($_POST['user_access']);
			break;
		case 'get_groups':
			$myReturn['groups'] = get_groups($_POST['nuid']);
			break;
		case 'update_user':
			unset($_POST['action']);
			$myReturn = update_user($_POST);
			break;
		case 'add_user':
			unset($_POST['action']);
			$myReturn = add_user($_POST);
			break;
		case 'delete_user':
			$myReturn = delete_user($_POST['id']);
			break;
		case 'get_user_data':
			$myReturn = get_user_data($_POST['id']);
			break;
		default:
			$myReturn['error'] = 'Not prepared to handle action "'.$_POST['action'].'"';
			break;
	} // end switch
	$myReturn['action'] = $myAction;
	$myReturn['last_accessed'] = log_user_access($_POST);	
} else {
					
	$myReturn['error'] = 'No action requested.';

} // end if get toolbar

// output whatever string was built by the SELECT CASE statement above  //
echo json_encode($myReturn);
die();

?>