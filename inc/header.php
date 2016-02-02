<?php

if ( ! class_exists('kpnvly_bootstrap_enqueuer')){
    class kpnvly_bootstrap_enqueuer{
        function __construct(){
/*            wp_enqueue_style('bootstrap',get_stylesheet_directory_uri().'/bootstrap/css/bootstrap.min.css',array('jquery-ui'),'3.2','all'); */
            wp_enqueue_script('bootstrap', KAISER_PATH.'/bootstrap/js/bootstrap.min.js',array(),'3.2',false);
//            wp_enqueue_script('jquery-mobile',KAISER_PATH.'/assets/js/jquery.mobile-1.3.2.min.js',array('jquery'),'1.3.2',TRUE);
        }
    }
} // end if class_exists bootstrape_enqueuer

if (! function_exists('get_giw_root_path')){
    function get_giw_root_path(){
        $self = $_SERVER['SERVER_NAME'].$_SERVER['PHP_SELF'];
        $arr_path = explode('/',$self);

        $idx = array_search('gi-whiteboard',$arr_path);
        $idx = ($idx - count($arr_path))+1;

        $arr_adjusted = array_slice($arr_path,0,$idx);
        $out = 'http://'.implode('/',$arr_adjusted);

        return $out;
    }
}
if (! defined('GIW_ROOT_PATH')) DEFINE ('GIW_ROOT_PATH',get_giw_root_path());

date_default_timezone_set(get_option('timezone_string'));

// remove more_announcements. We don't want them in this application
function giw_dequeue_script() {
	wp_dequeue_script( 'more_announcements' );
	wp_dequeue_script( 'kpit_alerts_ajax' );
	wp_dequeue_script( 'show_homepage_slides' );
}
add_action( 'wp_print_scripts', 'giw_dequeue_script', 100 );


// enqueue all javascripts universal to this application
function giw_enqueue_everything(){
    $server = $_SERVER['HTTP_HOST'];
    
	wp_enqueue_script('jquery-ui');
	wp_enqueue_style("wp-jquery-ui");
	
	wp_enqueue_script('jquery-ui-core');
	wp_enqueue_style("wp-jquery-ui-core");
	
	wp_enqueue_script('jquery-ui-widget');
//	wp_enqueue_style("wp-jquery-ui-widget");

	wp_enqueue_script('jquery-ui-dialog');
//	wp_enqueue_style("wp-jquery-ui-dialog");
	
	wp_enqueue_script('jquery-ui-datepicker');
//	wp_enqueue_style("wp-jquery-ui-datepicker");
	
	wp_enqueue_script('jquery-ui-button');
//	wp_enqueue_style("wp-jquery-ui-button");
	
	//**		enqueued stylesheets always appear in the page header     **//
	wp_enqueue_style('jquery-ui',GIW_ROOT_PATH.'/css/jquery-ui.min.css');	
	wp_enqueue_style('dataTables',GIW_ROOT_PATH.'/css/jquery.dataTables.min.css',array('jquery-ui'));
	wp_enqueue_style('primary_styles',GIW_ROOT_PATH.'/css/style.css', array('jquery-ui'));
	
	wp_enqueue_script('responsive_datatables',GIW_ROOT_PATH.'/js/dataTables.responsive.min.js',array('dataTables'),'1.0.7',TRUE);
	wp_enqueue_style('responsiv_datatables',GIW_ROOT_PATH.'/css/responsive.dataTables.min.css',array(),'1.0.7','all');
	
	//**		enqueue scripts to appear in the page header     **//
	

	//**		enqueue scripts to appear in the page footer     **//

	// for use with dialog and other jQuery UI widgets
//	wp_enqueue_script('jquery');
	
//	wp_enqueue_script( 'jquery-ui','http://'.$server.'/utility/gi-whiteboard/js/jquery-ui.js',array('jquery'),'',true);
	wp_enqueue_script( 'dataTables',GIW_ROOT_PATH.'/js/jquery.dataTables.min.js',array('jquery-ui-core'),'',true);

	
	// jquery cookie support
	wp_enqueue_script('jquery-cookie',get_template_directory_uri().'/assets/js/jquery.cookie.js',array('jquery'),'2.2.0',TRUE);
	
	// user login support
	wp_enqueue_script( 'logging',GIW_ROOT_PATH.'/js/user_login.js',array('jquery'),'.5',true);

	
	//  NVLY field validation
	wp_enqueue_script( 'common_utils','http://'.($server=='localhost'?'cnrpwwebd001.rpw.ca.kp.org':$server).'/scripts/common_utils.min.js',array(),'1.5',true);
	
	$js_root = GIW_ROOT_PATH.'/js/';
	$parent_path = dirname($_SERVER["SCRIPT_FILENAME"]);
	$separator = stristr($parent_path,'/') === false ? '\\' : '/';
	$path_parts = explode($separator, $parent_path);
	$filename = array_pop($path_parts);
	switch ($filename){
		case 'users':
			$js_file = 'giw_user_functions.js';
			break;
		case 'groups':
			$js_file = 'giw_groups_functions.js';
			break;
		default:
			$js_file = 'giw_functions.js';
			break;
	}
	wp_enqueue_script('local',$js_root.$js_file,array('jquery'),'2.2.0',true);
	
} // end function enqueue_everything
add_action('wp_enqueue_scripts', 'giw_enqueue_everything');

add_filter('body_class', 'giwb_body_classes');

function giwb_body_classes($classes) {
	if ( isset($classes['error404']) ) unset($classes['error404']);
    if ( isset($_SESSION['user']) ) {
		$classes[] = 'logged-in';
		$classes[] = 'single-author';
	}
	$classes[] = 'page';
	$classes[] = 'singular';
	$classes[] = 'giwb';
    return $classes;
}

function getMySQL_Today(){
	$phptime = strtotime('today midnight');
	return date ("Y-m-d H:i:s", $phptime);
}

get_header();
?>