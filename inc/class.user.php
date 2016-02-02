<?php
/*************************************
/*	class.user.php
/*	PURPOSE:  Retrieve and make available,
/*		user information stored in a MySQL database
/*	AUTHOR:		Michael Baxter
/*				michael@kp.org
/*	ORGANIZATION:
/*		Operations Information Management
/*		North Sacramento Valley TPMG
/****************************************/
//*****************************************/
// DEPENDENCY:
//	This class was designed to be used with
//	other classes capable of creating and
//	passing a MySQLi pointer.
//*****************************************/


// CONFIG PARAMETERS .. WORKING TOWARDS A GENERIC
// CLASS THAT CAN BE CONFIGURED AND RE-USED FOR
// MANY DIFFERENT APPLICATIONS

$tbl_user_table = 'wb-users';
$tbl_user_meta = 'wb-user-meta';
$tbl_user_groups = 'wb-groups';

//LDAP DEFINITIONS
if (! defined('BASEDN')) define("BASEDN","dc=cs,dc=msds,dc=kp,dc=org");
if (! defined('DN_CONTROLLER')) define("DN_CONTROLLER", "CNARDCSDC002.cs.msds.kp.org");
if (! defined('ALT_DN_CONTROLLER')) define("ALT_DN_CONTROLLER","CNNDCCSDC031.cs.msds.kp.org");

if (!class_exists('NVSA_USER')){
	class NVSA_USER{
//******* set private Class variables for internal use and processing ****//		
		
		private $db;
		
		private $fullname;
		
		private $firstname;
		
		private $lastname;
		
		private $nuid;
		
		private $email;
		
//		private $status;
		
		private $deleted;
		
		private $user_id;
		
		private $access_level;
				
		private $groups = array();
		
		private $group_access = array();
		
		private $meta = array();
		
		private $login_error;
		
		private $user_found = false;
		
		private $authorized = false;
		
		public $user_lookup;
		
		public $login_sql = '';
		
		public $last_error = '';
		
		// initialize our user object with MySQLi connection and $_POSTed fields //
		function __construct($session_db, $posted_fields){
			$this->db = $session_db;
			
			// use strip_tags to protect against html injection //			
			$this->nuid = strip_tags(isset($posted_fields['nuid']) ? $posted_fields['nuid'] : $posted_fields['NUID']);
			$pwd = strip_tags(isset($posted_fields['pwd']) ? $posted_fields['pwd'] : $posted_fields['password']); // assumes that we use 'pwd' as a field name
		
			// check if user is in our application database
			if ($this->get_user_info() == true){
				// if found, attempt login authorization
				// use local variable pwd to avoid storing secure information
				$this->authorized = $this->get_authorization($this->nuid, $pwd);
				
			}else{
				$this->login_error = 'User not found';
			}
		}
		
//***********		public methods for safely retrieving values from class variables   **************//
		public function fullname(){
			return trim($this->fullname);	
		}
		public function get_nuid(){
			if (isset($this->nuid))
				return trim($this->nuid);
			
			return '';	
		}
		public function last_name(){
			if (isset($this->lastname))
				return trim($this->lastname);	
				
			return '';
		}
		
		public function first_name(){
			if (isset($this->firstname))
				return trim($this->firstname);	
				
			return '';
		}
		
		public function email(){
			if (isset($this->email))
				return trim($this->email);
				
			return false;	
		}
/*		
		public function status(){
			if (isset($this->status))
				return $this->status;
				
			return false;
		}
*/		
		public function is_deleted(){
			if (isset($this->deleted))
				return $this->deleted;
				
			return false;
		}
		
		
		public function ID(){
			if (isset($this->user_id))
				return $this->user_id;
				
			return false;
		}
		
		
		public function user_access(){
			if (isset($this->access_level))
				return $this->access_level;
				
			return false;
		}	
		
		public function groups(){
			if ( !empty($this->groups) )
				return $this->groups;
				
			return NULL;
		}		
		
		public function user_meta(){
			if ( !empty($this->meta) )
				return $this->meta;
				
			return NULL;
		}
		
		public function login_error(){
			if (isset($this->login_error))
				return trim($this->login_error);	
				
			return '';
		}			
		
		public function is_found(){
			return $this->user_found;
		}
		
		public function is_authorized(){
			return $this->authorized;
		}
		public function get_login_info(){
		    if ($this->is_valid()){
    			return array(
    				'ID'				=>		$this->user_id,
    				'nuid' 			=>		$this->nuid,
    				'user_access'	=>		$this->access_level,
    				'first_name'		=>		$this->firstname,
    				'last_name' 		=>		$this->lastname,
    				'fullname'		=>		$this->fullname,
    				'groups'			=>		$this->groups,
    				'email'			=>		$this->email
    				);
		    } // end if is valid
		}
		public function is_valid(){
			$valid = true;
			if ($this->login_error() !== '' || $this->authorized === false) $valid = false;
			return $valid;
		}
//*******************  Private class functions to perform lookups and load session variables  ****************//

		//************************************************//
		// Function:  get_user_info							//
		//	purpose:	try to find user in MySQL db			//
		//	params: N/A									 	//
		//	return: (bool) found. False indicates			//
		//		we could not find user in MySQL db			//
		//************************************************//
		private function get_user_info(){
			global $tbl_user_table, $tbl_user_meta;
			try{
			$MySQLi = $this->db;
			$qry = "SELECT * FROM `$tbl_user_table` WHERE lcase(nuid)=lcase('".$this->nuid."') ORDER BY `deleted`,`id` ASC";
			$this->login_sql = $qry;
				
			// build and execute the query
			$users = $this->user_lookup = $MySQLi->query($qry);
			$found = false;

			if ($users->num_rows > 0){
				while($user = $users->fetch_assoc()){
					// just in case the user has been added and deleted before
					// ensure that the one we return is either the only result
					// .. or not the 'deleted' result
					if (!(isset($this->name) || $this->deleted==true)){
						$found = true;
						
						$this->firstname = $user['firstname'];
						
						$this->lastname = $user['lastname'];
						
						$this->fullname = $user['firstname'].' '.$user['lastname'];
						
						$this->nuid = $user['nuid'];
						
						$this->deleted = ($user['deleted'] == 0 ? false : true);
						
						$this->user_id = $user['id'];
	
						$this->meta['add_user'] = 'success..';	
						
						$this->meta['tbl_user_meta'] = $tbl_user_meta;
						
						if (isset($tbl_user_meta) && $tbl_user_meta !== ''){
							$this->meta['user_meta_tbl'] = true;
							$this->get_all_group_info();
							if ($this->deleted == false && !isset($this->access_level)){
								$this->get_user_meta();
							}else{
								$this->meta['is_deleted'] = $this->deleted;
								$this->meta['my_access_level'] = $this->access_level;
							} // end if deleted
						} else {
							$this->meta['user_meta_table'] = $tbl_user_meta;	
						}
					} //
				} // loop foreach
			} // end if empty search
			$this->user_found == $found;
			return $found;
			}catch(Exception $e){
				echo "Error in get_user_info: " .$e->getTraceAsString();	
			}
		}
		//************************************************//
		// Function:  get_user_meta							//
		//	purpose:	try to find user metadata in MySQL	//
		//	params: N/A									 	//
		//	return: N/A (no consequence)						//
		//************************************************//		
		private function get_user_meta(){
			global $tbl_user_meta;
			
			$db = $this->db;
			$sql = "SELECT * FROM `$tbl_user_meta` WHERE `user_id` = '".$this->user_id."' ORDER BY `id` ASC;";
			$rows = $db->query($sql);
			
			while($meta = $rows->fetch_assoc() ){
				$this->meta[ 'meta_key-'.$meta['meta_key'] ] = $meta['meta_value'];
				
				switch($meta['meta_key']){
					case('access_level'):
						$this->access_level = $meta['meta_value'];
						break;
					case('groups'):
						$user_groups = unserialize($meta['meta_value']);
						$this->access_level = 0;
						
						if (is_array($user_groups)){
							foreach($user_groups as $group){
								$access = $this->group_access[$group];
								$this->meta['access_level'] = $access;
								$this->meta['groups'] = $meta['meta_value'];
								if ($access > $this->access_level) $this->access_level = $access;
								$this->groups[] = $group;
							} // loop foreach
						} else {
							$this->groups[] = $user_groups;	
						}
						break;
					case ('email'):
						$this->email = $meta['meta_value'];
						break;
					default:
						$this->meta[$meta['meta_key']] = $meta['meta_value'];
						break;
				} // end switch
			} // loop foreach row
		} // end function get_user_meta
		//************************************************//
		// Function:  get_group_info							//
		//	purpose:	Get all group info from MySQL		//
		//	params: N/A									 	//
		//	return: N/A (no consequence)						//
		//************************************************//		
		private function get_all_group_info(){
			global $tbl_user_groups;

			$success = false;
			$mySQLi = $this->db;
			$sql = "SELECT * FROM `$tbl_user_groups` ORDER BY 'level' ASC;";
			$results = $mySQLi->query($sql);
		
			if ($results !== false) {
				$success = true;
				while ($row = $results->fetch_assoc()){
					$this->group_access[$row['name']] = $row['level'];
				}
				$this->meta['group_access'] = $this->group_access;
			}else{
				$this->last_error = $mySQLi->error;
			}

		}
		//****************************************************//
		// Function:  get_authorization							//
		//	purpose:	Use LDAP connection to Active Directory	//
		//		server to lookup use credentials.				//
		//	params: N/A									 		//
		//	return: (bool) Indicator of success or failure		//
		//****************************************************//	
		private function get_authorization($nuid, $pwd, $recursive=false){			
			
			// connect to controller
			$ad_conn = $recursive===true ? ldap_connect(ALT_DN_CONTROLLER) : ldap_connect(DN_CONTROLLER); 
			if ($ad_conn===false && $recursive===false) $ad_conn = ldap_connect(ALT_DN_CONTROLLER);
			
			if ($ad_conn===false && $recursive===true){
				$this->login_error = "Unable to connect to authentication server(s)";
				return false;	
			}
			
			// set options
			ldap_set_option($ad_conn, LDAP_OPT_PROTOCOL_VERSION, 3) or die ("Could not set ldap protocol"); // set version number
			ldap_set_option($ad_conn, LDAP_OPT_REFERRALS, 0) or die ("Could not set ldap referrals");
			
			// attempt to bind with AD via ldap
			$auth = ldap_bind($ad_conn, $nuid."@cs.msds.kp.org", $pwd);
			
			// if successful, just set flag.
			// otherwise, set error log too.
			if ($auth === true){
				$ret = true;
			}else{
				$ret = $recursive==true ? false : $this->get_authorization($nuid,$pwd,true);
				if ($this->login_error == '') $this->login_error = 'Invalid NUID or password';
			} // end if authorization succeeded
			return $ret;
		}
	}
}
?>