<?php
/*                                                                                                   
Plugin Name: Error Logging Plugin
Description: Log errors to a separate database in mu in order to determine issues that you put hooks in for in the other areas where you are seeing issues
Author: Bob Patterson
Version: 0.3
*/
if (!class_exists("Error_Logging")) {
	define( 'Error_LoggingPath', path_join( WP_PLUGIN_DIR, basename( dirname( __FILE__ ) ) ) . '/' );
	define( 'Error_LoggingUrl',	path_join( WP_PLUGIN_URL, 'error_logging/' ) );

	class Error_Logging {		
		var $table_name;
		var $keys = array (
			'category',
			'log_type',
			'message',
		);
		var $calls		= 0;
		const KEEP_DAYS	= 7;
		function __construct( ) {
			register_activation_hook( __FILE__, array($this,'on_activate')); 
			add_filter('error_logging',array($this,'add_error_to_log')); //hook for adding data to the table
			add_action('admin_menu',array($this, 'error_logging_settings_menu'));
			add_action('wp_ajax_filter_logs',array($this, 'filter_results'));
			global $wpdb;
			$this->table_name = "{$wpdb->prefix}error_logging"; //the new table name will be wp_*blog id*_error_logging
		}
		// Activation function to create the table for the blog_id
		function on_activate() {
			global $wpdb;
			$wpdb->query("CREATE TABLE IF NOT EXISTS {$this->table_name} (
				`id` int(11) NOT NULL AUTO_INCREMENT,
				`time_stamp` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
				`category` varchar(50) DEFAULT NULL,
				`log_type` varchar(10) DEFAULT NULL,
				`message` varchar(255) DEFAULT NULL,
				PRIMARY KEY (`id`),
				KEY `time_stamp` (`time_stamp`),
				KEY `category` (`category`)
			)");
		}
		// Adds errors to the database table for this blog
		function add_error_to_log($log_data = array( )) {
			global $wpdb;
			$types = ""; 
			$values = "";
			$magic_quotes = ini_get('magic_quotes_gpc');
			foreach ($log_data as $log_type => $log_value) { //parse log data from the array to be inserted
				if( !in_array( $log_type, $this->keys ) ) {
					continue;
				}
				if( $magic_quotes ) {
					$values = stripslashes( $log_value );
				}
				$log_value = mysql_real_escape_string( $log_value );
				$types = $log_type.",".$types;
				$values = "'".$log_value."',".$values;
			}
			// remove trailing commas from the SQL statement
			$types = rtrim( $types, ',' );
			$values = rtrim( $values, ',' );
			$sql = "INSERT INTO ".$this->table_name."(".$types.") VALUES (".$values.")";
			$this->rotate_log_table();
			$this->calls++;
			return $wpdb->query($sql);
		}
		// Deletes old log entries so the table doesn't get too big
		private function rotate_log_table() {
			if( $this->calls > 0 ) {
				return;
			}
			$random_val = rand(0,1000);
			if($random_val == 500){
				global $wpdb;
				$lock = rand();
				$sql = $wpdb->prepare("INSERT IGNORE INTO {$wpdb->prefix}options SET `option_name` = 'error_log_delete_lock', `option_value` = '$lock', `autoload` = 'no'");
				if($wpdb->query($sql)){
					$sql = $wpdb->prepare("SELECT option_value FROM {$wpdb->prefix}options where option_name='error_log_delete_lock'");
					$results = $wpdb->$results($sql);
					if ($results['key'] == $lock) {
 						$sql = $wpdb->prepare( "DELETE FROM {$this->table_name} WHERE time_stamp < (CURDATE() - INTERVAL %d DAY)", Error_Logging::KEEP_DAYS );
						$return = $wpdb->query( $sql );
						$sql = $wpdb->prepare("DELETE FROM {$wpdb->prefix}options WHERE option_name='error_log_delete_lock'");	
						$return = $wpdb->query( $sql );
					}else {
			  			return 0;
					}
				return $return;
				}else{
			  		return 0;
				}
			}else{
			  	return 0;
			}
		}
		// Return the rows from the error table, no more than 500 lines
		function return_log( $number_of_errors , $field = '' , $regex = '' ) {
		        global $wpdb;
			if (( $number_of_errors > 500 )||($number_of_errors == NULL)) { //make sure we are not returning more than 500 errors to be displayed
				$number_of_errors = 500;
			}
			if (($regex != NULL)&&($field != NULL)){
				return $wpdb->get_results("SELECT time_stamp,category,log_type,message FROM ".$this->table_name." WHERE ".$field." REGEXP \"".$regex."\" ORDER BY id DESC LIMIT ".$number_of_errors, OBJECT); 

			}else{
				return $wpdb->get_results("SELECT time_stamp,category,log_type,message FROM ".$this->table_name." ORDER BY id DESC LIMIT ".$number_of_errors, OBJECT); 
			}
		}
		function error_logging_settings_menu() {
		  add_submenu_page('options-general.php', 'Error Logging Options', 'Error Logging Options', 6, __FILE__, array($this, 'options_page'));
		}
		//applies a filter to the results and returns the new set of data
		function filter_results() {
			$result = array();

			if (!empty($_POST['field'])) {
  				$field = $_POST['field'];
 			} else {
  				$field = NULL;
  				$errors[]="please enter a field";
 			}

			if (!empty($_POST['regex'])) {
  				$regex = $_POST['regex'];
 			} else {
  				$regex = NULL;
  				$errors[]="please enter a regex";
 			}
			
			if (!empty($_POST['amount'])) {
  				$amount = $_POST['amount'];
 			} else {
  				$amount = NULL;
  				$errors[]="please enter a amount";
 			}
			
			$error_log_results = $this->return_log( $amount , $field, $regex );		
	
			$result['html'] = '<table class="sort" border="0" cellspacing="10"><tr><th>time_stamp</th><th>category</th><th>log_type</th><th>message</th></tr>';
			foreach ( $error_log_results as $error_line) {
  				$result['html'] = $result['html']. ' <tr>';
  				foreach ( $error_line as $error_type => $error) {
	 				$result['html'] = $result['html']. ' <td>'.$error.'</td>';
  				}
  				$result['html'] = $result['html']. ' </tr>';
			}
			echo json_encode($result);
			exit;
		}
		//displays the admin page with a defult of 200 entires
		function options_page() {		  
			$error_log_results = $this->return_log('200');
			echo '<div class="wraper">';
			include ('form.php');
			echo '<div id="replace">';
			echo '<table class="sort" border="0" cellspacing="10"><tr><th>time_stamp</th><th>category</th><th>log_type</th><th>message</th></tr>';
			foreach ( $error_log_results as $error_line) {
			        echo '<tr>';
				foreach ( $error_line as $error_type => $error) {
					echo '<td>'.$error.'</td>'; 
				}
				echo '</tr>';
			}
			echo '</table>';
			echo '</div>';
			echo '</div>';
		}
	}
}
global $Error_Logging;
if(class_exists( 'Error_Logging' ) ) {
	global $Error_Logging;
	$Error_Logging = new Error_Logging();
}