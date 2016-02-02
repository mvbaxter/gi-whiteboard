<?php
/*************************************
/*	sessions.php
/*	PURPOSE:  Create and maintain user session information,
/*		including connections to the database used by this
/*		application, and LDAP connections to the KP Domain
/*		controllers for User Authentication.
/*	AUTHOR:		Michael Baxter
/*				michael@kp.org
/*	ORGANIZATION:
/*		Operations Information Management
/*		North Sacramento Valley TPMG
/****************************************/
require_once('class.sqlidb.php');
require_once('class.user.php');

// declare session info and begin using to store session based info,
//	like nuid, user access, capabilities, facility preferences, etc.
//	like a mini cache file..

//***************************************//
//  Global variables for use throughout  //
//***************************************//
$login_error = '';
$session_db = null;
$logged_in_user = NULL;
$json = array();

//*********************************************//
// Main session handling workflow operations.	//
// Calling to any of several helper functions  //
// below.											//
//*********************************************//
try{
	// if you cannot get a connection to the database
	// you cannot do anything else... Start processing.
	$mySQLi = new MySQLi_DB($_sqli_conf);

	if ( $mySQLi->is_valid() ){ // you've connected		
		// get a pointer to the database for use in all future operations
		$session_db = $mySQLi->getLink();
		
		
		// insert or update last accessed on each logged-in session refresh
		if (isset($_COOKIE['nvsa_session'])){
			$kp_session =  (array) json_decode(base64_decode($_COOKIE['nvsa_session']));
			if (!isset($logged_in_user)) $logged_in_user = new NVSA_USER($session_db, $kp_session );
			$qry = "INSERT INTO `wb-user-meta` (user_id,meta_key,meta_value) VALUES ('".$logged_in_user->ID()."','last_accessed', CURRENT_TIMESTAMP)".
				"ON DUPLICATE KEY UPDATE meta_value=CURRENT_TIMESTAMP;";
			
			$session_db->query($qry);
		}
		
		// note: tuck all echo statments away so they cannot accidentally fire
		// 	when this file is included. Only available upon "Action" request
		if ( isset($_POST['action']) && $_POST['action']!=='' ) {
			// if attempting to login
			if ( $_POST['action']=='login' ) {
//				if ( user_logged_in(true)==true )
//					$_SESSION['session_id'] = $mySession->session_lock;
				$json['logged_in'] = user_logged_in(true);
				$json['login_html'] = get_login_link(); // there is no else because the login_error would be set in the function user_logged_in
				echo json_encode($json);
			}elseif ( $_POST['action'] == 'logout'){
				if (logged_out()==true)
					$json['login_html'] = get_login_link();
				echo json_encode($json);
			}// end if login			
		} // end if action set
	} else {		
		ob_start();
		echo "<p>";
		$mySQLi->print_error();
		echo "</p>\n";
		$json['login_error'] = ob_get_clean();
	}
	
} catch (Exception $e) {
	$json['login_error'] = 'Session_mgr exception: '.$e->getTraceAsString();
	echo json_encode($json);
}

//*********************************************//
//  Function:  logged_out							//
//	params: none.. either logout or fail.		//
//	return: (bool) success. False indicates		//
//		a failure occured, and should not		//
//		happen.										//
//*********************************************//
function logged_out(){
	global $logged_in_user;
	
	$success = false;

	try{
		setcookie('nvsa_session','',time()-86400,"/",$_SERVER['SERVER_NAME']); // expired yesterday (delete)
		$logged_in_user = NULL;
		/*
		foreach($_SESSION as $name => $val){
			// clear all session variables on logout
			unset($_SESSION[$name]);
		} // loop foreach
		// destroy the entire session object after clearing variables
		session_destroy();
		*/
		$success = true;
	} catch (Exception $e){
		$json['login_error'] = 'Logout :: Caught exception: '.$e->getMessage();
		$success = false;
	}
	return $success;
} // end function logout
//*********************************************//
// Function:  get_login_link						//
//	params: N/A									//
//	return: (bool) success. False indicates		//
//		a failure occured, and should not		//
//		happen.										//
//*********************************************//
function get_login_link(){
	global $session_db,$logged_in_user;
	
	// if this function is called with a persisted session
	// we need to use the cookie to re-establish the $logged_in_user;
	if (isset($_COOKIE['nvsa_session']) && !isset($logged_in_user)) {
		$kp_session =  (array) json_decode(base64_decode($_COOKIE['nvsa_session']));
		$logged_in_user = new NVSA_USER( $session_db, $kp_session);
	}
	
	// if the user is logged in...
	if ( isset($logged_in_user) && $logged_in_user->is_valid() && !(isset($_POST['action']) && $_POST['action'] == 'logout') ){
		$txt = 'Hello <strong>'.$logged_in_user->fullname().'</strong>&nbsp;';
		return $txt.'<a title="Logout" href="?action=logout" id="logout" class="login">Logout</a>';
	}else{
		return '<a title="Login" href="?action=login" id="login" class="login">Login</a>';
	}
} // end function get_login_link
//************************************************//
// Function:  user_logged_in							//
//	purpose:	Report whether the user is logged	//
//		in, trying to login if requested.			//
//	params: login_requested = Indicates whether 	//
//		inquiring, or trying to login.				//
//	return: (bool) success. False indicates			//
//		either an error or a login failure			//
//************************************************//
function user_logged_in($login_requested=false){	
	global $session_db, $login_error, $login_sql, $login_results, $json, $logged_in_user;
	$success = false;
	$login_cookie = '';
	
	// is the user is logged in ...
	if ( isset($_COOKIE['nvsa_session']) ) {
		$kp_session = (array) json_decode(base64_decode($_COOKIE['nvsa_session']));
		if (!isset($logged_in_user)) $logged_in_user = new NVSA_USER($session_db,$kp_session);
		$success = true;
	} else { // otherwise ...
		// if login has been requested
		if ($login_requested){		
			// if the nuid and password fields have been submitted
			if ( isset($_POST['nuid']) && isset($_POST['pwd']) ){
				// try to get a new NVSA_USER object
				$logged_in_user = new NVSA_USER($session_db,$_POST);
	
				// if successfule, set session variables
				if ( $logged_in_user->is_valid() ){
					
					$login_info = $logged_in_user->get_login_info();
					unset($_POST['action']);
					
					$login_cookie = base64_encode( json_encode( array_merge($_POST,$login_info) ) );
					
					setcookie('nvsa_session',$login_cookie, 0, '/',$_SERVER['SERVER_NAME']);
					
					$success = true;
					
				}else{
					// if unsuccessful, pass the login error to local error variable
					$login_error = $logged_in_user->login_error();
					$login_sql = $logged_in_user->login_sql;
					$login_results = $logged_in_user->user_lookup;
				}
//**** the following elseif statements are safety nets against failures to trap data entry via JS ****//
			} elseif ( isset($_POST['nuid']) ){ // if only nuid was set
				
				$login_error = 'Please submit a valid NUID and try again';
				
			}elseif ( isset($_POST['pwd']) ){ // if only pwd
				
				$login_error = 'Please submit a valid NUID and try again';
				
			}else{ // nothing was submitted
				if ( isset($_POST['submitted']) ){
					$login_error = 'Please complete both NUID and PASSWORD and try again'	;
				} // end if
			} // end if nuid or pwd were submitted .. if not, just continue
		} else {
			$success = false;
		} // end if nuid and pwd submitted
	} // end if session contains user
	$json['logged_in'] = $success;
	$json['login_error'] = $login_error;
	$json['login_query'] = $login_sql;
	$json['login_results'] = $login_results;
	$json['login_cookie'] = $login_cookie;
	return $success;
} // end function is_user_logged_in

?>