<?php

/*
 Plugin Name: ZEPSON SMS
 Plugin URI: https://github.com/Zepson-SMS
 Description: A Wordpress Plugin for Zepson SMS API. Send SMS from your Wordpress Website Dashboard using the zepson sms Bulk SMS API.
 Version: 1.0.0
 Author: ZEPSON TECHNOLOGIES
 Author URI: http://www.zepsonsms.co.tz
 Text Domain: ZEPSON-SMS-Plugin
 License: The MIT License (MIT)
 */

require_once plugin_dir_path( __FILE__ ) . 'src/vendor/autoload.php';

use ZepsonSms\SDK\ZepsonSms;

global $zepson_sms_database_version;
$zepson_sms_database_version = '1.0.0';
 
register_activation_hook( __FILE__, 'zepson_sms_activate');
register_activation_hook( __FILE__, 'zepson_sms_install');
register_activation_hook( __FILE__, 'zepson_sms_plugin_create_database');

register_deactivation_hook( __FILE__, 'zepson_sms_plugin_remove_database' );

add_action('admin_init', 'zepson_sms_redirect');
add_action( 'admin_menu', 'zepson_sms_create_menu' );
add_action('admin_enqueue_scripts', 'zepson_sms_scripts');
add_action( 'plugin_action_links_' . plugin_basename( __FILE__ ), 'zepson_sms_plugin_action_links' );

add_filter( 'plugin_row_meta', 'zepson_sms_prefix_append_support_and_faq_links', 10, 4 );
	
function zepson_sms_install() {
	global $wp_version;  
	if ( version_compare( $wp_version, '5.2', '<' ) ) {
		wp_die( 'This plugin requires WordPress version 5.2 or higher. Please update your WordPress and try again.' );		  
	}		
}
	
function zepson_sms_activate() {
	add_option('zepson_sms_do_activation_redirect', true);
}
	
function zepson_sms_redirect() {
	if (get_option('zepson_sms_do_activation_redirect', false)) {
		delete_option('zepson_sms_do_activation_redirect');
		exit( wp_redirect("admin.php?page=zepson_sms_settings") );
	}
}

function zepson_sms_plugin_create_database() {

	global $wpdb;		
	global $zepson_sms_database_version;

	$charset_collate = $wpdb->get_charset_collate();
	$table_name = $wpdb->prefix . 'zepson_sms_plugin_saved_contacts';
	
	$sql = "CREATE TABLE $table_name (
		id mediumint(9) NOT NULL AUTO_INCREMENT,
		time datetime DEFAULT '0000-00-00 00:00:00' NOT NULL,
		contact_name varchar(225) NOT NULL,
		contact_phone varchar(225) NOT NULL,
		contact_group varchar(225) DEFAULT 'general' NOT NULL,
		UNIQUE KEY id (id)
	) $charset_collate;";
	
	require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );
	dbDelta( $sql );
		
	$date = date("F j, Y, g:i a");
	$zepson_sms_contact_groups = ['general' => $date ];

	add_option( 'zepson_sms_database_version', $zepson_sms_database_version );
	add_option( 'zepson_sms_contact_groups', $zepson_sms_contact_groups );
}


function zepson_sms_plugin_remove_database() {
	global $wpdb;
	
	$table_name = $wpdb->prefix . 'zepson_sms_plugin_saved_contacts';
	$sql = "DROP TABLE IF EXISTS $table_name;";
	$wpdb->query($sql);
	delete_option('zepson_sms_database_version');
	delete_option('zepson_sms_options');
	delete_option('zepson_sms_contact_groups');
}    
	  
function zepson_sms_create_menu() {
  
	add_menu_page( 'ZEPSON SMS Plugin Page', 'ZEPSON SMS Plugin',
	'manage_options', 'zepson_sms_main_menu', 'zepson_sms_main_plugin_page', 
	plugins_url( '/images/logo.png', __FILE__ ), 25 );
  
	add_submenu_page('zepson_sms_main_menu', 'ZEPSON SMS Plugin Page', 'General', 'manage_options', 'zepson_sms_main_menu' );
	
	add_submenu_page( 'zepson_sms_main_menu', 'ZEPSON SMS Plugin Settings Page',
	'Settings', 'manage_options', 'zepson_sms_settings',
	'zepson_sms_settings_page' );
	add_submenu_page( 'zepson_sms_main_menu', 'ZEPSON SMS Plugin Support Page',
	'Support', 'manage_options', 'zepson_sms_support', 'zepson_sms_support_page' );	
	
	add_action( 'admin_init', 'zepson_sms_register_settings' );
  
}


function zepson_sms_plugin_action_links( $links ) {
	$links = array_merge(array(
		'<a href="' . esc_url( admin_url( 'admin.php?page=zepson_sms_main_menu' ) ) . '">' . __( 'Send SMS', 'textdomain' ) . '</a>',
		'<a href="' . esc_url( admin_url( 'admin.php?page=zepson_sms_settings' ) ) . '">' . __( 'Settings', 'textdomain' ) . '</a>'
	), $links );
	return $links;
}


function zepson_sms_prefix_append_support_and_faq_links( $links_array, $plugin_file_name, $plugin_data, $status ) {
    if ( strpos( $plugin_file_name, basename(__FILE__) ) ) {
        $links_array[] = '<a href="#">FAQ</a>';
        $links_array[] = '<a href="#">Support</a>';
    }
  
    return $links_array;
}


function zepson_sms_register_settings() {
	$the_nonce = zepson_sms_clean_name($_POST['zepson_sms_settings_group']);
	if ( $the_nonce ){
		if ( ! wp_verify_nonce( $the_nonce, 'zepson_sms-settings-group' ) 
		) {	 
		print 'Sorry, your nonce did not verify.';
		exit;	 
		} else {
			register_setting( 'zepson_sms-settings-group', 'zepson_sms_options',
			'zepson_sms_sanitize_options' );
		}  
	}
}
 
function zepson_sms_sanitize_options( $input ) {
	 
	$input['option_api_key'] = sanitize_text_field( $input['option_api_key'] );
	$input['option_shortcode'] = sanitize_text_field( $input['option_shortcode'] );
	return $input;  
}


function zepson_sms_main_plugin_page(){
	?>
<div class="wrap" style="border: 10px; border-radius: 15px; padding:20px;">
	<h2>ZEPSON SMS General Page</h2>   

	<?php
		$the_tab = zepson_sms_clean_name($_GET[ 'tab' ]);
		if( isset( $the_tab ) ) {	$active_tab = $the_tab;}
		if (!$the_tab || $the_tab == '') { $the_tab = 'send_sms'; $active_tab = 'send_sms';}		
	?>				
	
	<h2 class="nav-tab-wrapper">
        <a href="?page=zepson_sms_main_menu&tab=send_sms" class="nav-tab <?php echo $active_tab == 'send_sms' ? 'nav-tab-active' : ''; ?>">Send SMS</a>
        <a href="?page=zepson_sms_main_menu&tab=add_contacts" class="nav-tab <?php echo $active_tab == 'add_contacts' ? 'nav-tab-active' : ''; ?>">Add Contacts</a>
        <a href="?page=zepson_sms_main_menu&tab=manage_contacts" class="nav-tab <?php echo $active_tab == 'manage_contacts' ? 'nav-tab-active' : ''; ?>">Manage Contacts</a>
    </h2>
 	
	
	<?php
		if( $active_tab == 'send_sms' ) {
			zepson_sms_send_the_sms();
		?>
				
			<form method="post">
				<table class="form-table">
					<tr valign="top">
						<th scope="row">Source of Contacts</th>
						<td><select name="from" id="from">
							<option selected value="1">Enter Below</option>
							<option value="3">Stored Contacts</option>
							<option value="4"><a href = "red">Upload New CSV File</a></option>
						</select></td>
					</tr>
					<tr valign="top" id="contact_group_row" class="disappear">
						<th scope="row">Contact Group</th>
						<td>
							<input type="text" list="contact_group_list" id="contact_group_input" name="contact_group_input" value="" placeholder="Enter the Group" autocomplete="off"/>
							<datalist id="contact_group_list">

							<?php
							$zepson_sms_contact_groups = get_option( 'zepson_sms_contact_groups' );

							foreach ($zepson_sms_contact_groups as $zepson_sms_contact_group => $date) {
									echo '<option value="'.esc_html(ucwords($zepson_sms_contact_group)).'">';
								}
							?>

							</datalist>
							<p id ="bad_group_input" class="at-warning"></p>
						</td>						
					</tr>					
					<tr valign="top" id="contact_row">
						<th scope="row">Contacts</th>
						<td><textarea name="contacts" id="contacts" cols= "50" rows= "10" placeholder="Enter numbers here"></textarea></td>
						<td colspan = "2">Separate the numbers via a comma and makes sure its a complete number including country code. E.g. <strong> +255 712 345 678, +255 712 345 678, +255 712 345 678, +255 712 345 678 </strong></td>
					</tr>
					<tr valign="top" id="warning_row">
						<td colspan = "3" class="at-warning" id = "number_error"></td>
					</tr>
					<tr valign="top">
						<th scope="row">SMS Message</th>
						<td><textarea name="sms_messages" id="sms_messages" cols= "50" rows= "5" placeholder="Enter SMS  here..."></textarea>
						</br>
						<p id="statement_of_word_count" class="my-p left"></p>
						</br>
						<p class="my-p left"> Word Count : <span id="word_count"></span></p>
						<p class="my-p right"> SMS Count : <span id="sms_count"></span></p>
						</td>
					</tr>
				</table>  
				<p class="submit">
					<input type="hidden" id="source_of_contacts" name="source_of_contacts" value="1">
					<input id="sms_submit" type="submit" class="button-primary"
					 value="Send Message" disabled="true"/>
					<?php wp_nonce_field( 'zepson_sms_main_plugin_page', 'zepsonsms_send_sms' ); ?>
				</p>  
			</form>
			
		<?php
        } else if( $active_tab == 'add_contacts' ) {
			zepson_sms_uploaded_csv_file();
		?>
						
			<form method="post" enctype="multipart/form-data">
				<table class="form-table">
					<tr valign="top">
						<th scope="row">Contact Group (if none is provided the default is <strong>General</strong> contact group)</th>
						<td>
							<input  type="text" list="contact_group_list" id="contact_group" name="contact_group" value="" placeholder="Enter the Group" autocomplete="off"/>
							<datalist id="contact_group_list">

							<?php
							$zepson_sms_contact_groups = get_option( 'zepson_sms_contact_groups' );

							foreach ($zepson_sms_contact_groups as $zepson_sms_contact_group => $date) {
									echo '<option value="'.esc_html(ucwords($zepson_sms_contact_group)).'">';
								}
							?>

							</datalist>
						</td>						
					</tr>
					<tr valign="top" id="upload">
						<th colspan = "2">
							<br>
							<input type='file' id='csv_upload' name='csv_upload' accept=".csv, application/vnd.ms-excel, application/csv, application/x-csv, text/csv, text/comma-separated-values, text/x-comma-separated-values"></input>
						</th>
					</tr>					
				</table>  
				<p class="submit">
				<?php wp_nonce_field( 'file_validate_submit', 'csv_file_submit' ); ?>
				<input id="upload_submit" type="submit" class="button-primary"
					 value="Upload" disabled/>
				</p>  
			</form>	
		<?php
			
        } else if( $active_tab == 'manage_contacts' ) {
			zepson_sms_manage_the_contacts();

        } 
	?>	
</div>
<?php
}

function zepson_sms_uniqidReal($lenght = 10) {
    if (function_exists("random_bytes")) {
        $bytes = random_bytes(ceil($lenght / 2));
    } elseif (function_exists("openssl_random_pseudo_bytes")) {
        $bytes = openssl_random_pseudo_bytes(ceil($lenght / 2));
    } else {
        throw new Exception("no cryptographically secure random function available");
    }
    return substr(bin2hex($bytes), 0, $lenght);
}

function zepson_sms_clean_name($data) {
	$data = trim($data);
	$data = stripslashes($data);
	$data = htmlspecialchars($data);
	$data = sanitize_text_field($data);
	return $data;
}

function zepson_sms_manage_the_contacts(){

	global $wpdb;

	$table_name = $wpdb->prefix . 'zepson_sms_plugin_saved_contacts';	

	$contact_group = zepson_sms_clean_name($_GET['group']);
	
	$zepson_sms_contact_groups = get_option( 'zepson_sms_contact_groups' );

	if (!$contact_group || $contact_group == '' || !array_key_exists($contact_group, $zepson_sms_contact_groups)){

		$group_to_delete = zepson_sms_clean_name($_GET['delete_group']);

		if ($group_to_delete && $group_to_delete != 'general' ){

			$delete_group = urldecode($group_to_delete);

			$zepson_sms_contact_groups = get_option( 'zepson_sms_contact_groups' );
			
			$delete_contact = $wpdb->get_results( "DELETE FROM $table_name WHERE id = '$delete_group'" );	

			unset($zepson_sms_contact_groups[$delete_group]); 

			update_option( 'zepson_sms_contact_groups', $zepson_sms_contact_groups );	

		}

		$zepson_sms_contact_groups = get_option( 'zepson_sms_contact_groups' );

		echo '
		</br>
		<input type="text" id="myInput" onkeyup="myFunction()" placeholder="Search for groups..." title="Type in a name">

		<table id="myTable">
			<tr class="header">
				<th>Contact Groups</th>
				<th>Date of Creation</th>
				<th>Manage Group</th>
				<th>Delete Group</th>
			</tr>';
			

			foreach ($zepson_sms_contact_groups as $zepson_sms_contact_group => $date) {
				echo '
					<tr>
						<td>'.esc_html(ucwords($zepson_sms_contact_group)).'</td>
						<td>'.esc_html($date).'</td>
						<td><a href="?page=zepson_sms_main_menu&tab=manage_contacts&group='.esc_html(urlencode($zepson_sms_contact_group)).'"> Manage This Group </a></td>
						<td><a href="?page=zepson_sms_main_menu&tab=manage_contacts&delete_group='.esc_html(urlencode($zepson_sms_contact_group)).'"> <img src="'. esc_url(plugins_url( '/images/delete.png', __FILE__ )). '"></a></th>
					</tr>			
				';
			}

		echo'
		</table>';
	}
	else{
		
		$contact_group = urldecode($contact_group);

		if (zepson_sms_clean_name($_GET['delete'])){

			$delete_id = zepson_sms_clean_name($_GET['delete']);
			
			$delete_contact = $wpdb->get_results( "DELETE FROM $table_name WHERE id = '$delete_id'" );	

		}

		$retrieved_contact_datas = $wpdb->get_results( "SELECT * FROM $table_name WHERE contact_group = '$contact_group'" );

		$count_of_retrieved_contact_datas = count($retrieved_contact_datas);

		$row_number = 0;

		echo '
			</br>
			
			<table class="results-table">
				<tr class="table-head">
					<th colspan="4" class="sub-table mid"> Contact Group : '.ucwords(esc_html($contact_group)).'</th>
				</tr>
				<tr class="table-head">
					<th class="sub-table"> </th>
					<th class="sub-table">Phone Number</th>
					<th class="sub-table">Name</th>
					<th class="sub-table">Delete</th>
				</tr>';		

				foreach ($retrieved_contact_datas as $retrieved_contact_data){

					++$row_number;

					echo '	
						<tr>
							<th class="sub-table">'.esc_html($row_number).'</th>
							<th class="sub-table">'.esc_html($retrieved_contact_data->contact_phone).'</th>
							<th class="sub-table">'.esc_html($retrieved_contact_data->contact_name).'</th>
							<th class="sub-table"><a href="?page=zepson_sms_main_menu&tab=manage_contacts&group='.urlencode($contact_group).'&delete='.urlencode($retrieved_contact_data->id).'"><img src="'. esc_url(plugins_url( '/images/delete.png', __FILE__ )). '"></a></th>
						</tr>';
				}

				echo '				
				<tr class="table-head">
					<th class="sub-table" colspan = "4"> </th>
				</tr>
			</table>';		
	}
}

function zepson_sms_send_the_sms(){
	$zepson_sms_options = get_option( 'zepson_sms_options' );

	 
	$apiKey = $zepson_sms_options['option_api_key']; 
	$from = $zepson_sms_options['option_shortcode'];

	if ($apiKey == ''){				
		$string = '<script type="text/javascript">';
		$string .= 'window.location = "?page=zepson_sms_main_menu"';
		$string .= '</script>';

		echo esc_url($string);		
	}

	if ( ! empty( $_POST ) ) {
		
		$the_nonce = zepson_sms_clean_name($_POST['zepsonsms_send_sms']);

		if ( 
			! isset( $the_nonce ) 
			|| ! wp_verify_nonce( $the_nonce, 'zepson_sms_main_plugin_page' ) 
		) {		 
			
		   print 'Sorry, the nonce did not verify.';
		   exit;		 
		}

		$post_from = zepson_sms_clean_name($_POST['from']);

		if ( $post_from == '1'){
			
			$recipients = sanitize_text_field($_POST['contacts']);
	
		}
		else if  ($post_from == '3'){

			global $wpdb;

			$contact_group = zepson_sms_clean_name($_POST['contact_group_input']);
	
			$table_name = $wpdb->prefix . 'zepson_sms_plugin_saved_contacts';	
	
			$retrieved_contact_datas = $wpdb->get_results( "SELECT * FROM $table_name WHERE contact_group = '$contact_group'" );

			$new_contact_recepients = '';

			foreach ($retrieved_contact_datas as $retrieved_contact_data){
				$new_contact_recepients .= $retrieved_contact_data->contact_phone . ',' ;
			}

			$recipients =  strval(rtrim($new_contact_recepients,','));
		}

		$message = sanitize_text_field($_POST['sms_messages']);
		//urlencode($message);
		$message = urlencode($message);
		$base_url = "https://portal.zepsonsms.co.tz/api/v3/sms/send?";
		
		 
		$param='&sender_id='.$from.'&recipient='.$recipients.'&type=plain&message='.$message;
		$url =  $base_url.$param;
		try {

		$curl = curl_init();

		curl_setopt_array($curl, array(
		CURLOPT_URL =>  $url,
		CURLOPT_RETURNTRANSFER => true,
		CURLOPT_ENCODING => '',
		CURLOPT_MAXREDIRS => 10,
		CURLOPT_TIMEOUT => 0,
		CURLOPT_FOLLOWLOCATION => true,
		CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
		CURLOPT_CUSTOMREQUEST => 'POST',
		CURLOPT_HTTPHEADER => array(
			'Authorization: Bearer '.$apiKey,
		),
		));

		$response = curl_exec($curl);

		curl_close($curl);
		print_r($response);
		$response_json =  json_decode($response);
 
			$responseData = $response_json->status;
			 
			
			echo '
			</br>
			<table class="results-table"> 
				<tr class="table-head">
				 
					<th class="sub-table">Status</th>
					 
				</tr>';
			 
				$status = $responseData ->status;
				if ($responseData != 'success'){
					$status = '<span  class="at-warning">Failed : ' . esc_html($responseData) . '</span>';
				}
				echo'						
					<tr>
						 
						<td class="sub-table">'.$status.'</td>
						 
					</tr>
				';	
			 
			echo '				
				 		
			</table>';

		} catch (Exception $e) {
			echo '
			</br>
			<table class="results-table">
				<tr class="table-head">
					<th class="sub-table ">Error sending SMS</th>
				</tr>						
				<tr>
					<td class="sub-table">'.esc_html($e->getMessage()).'</td>
				</tr>			
			</table>';
		}
	}
}


function zepson_sms_uploaded_csv_file(){
	
	if(isset($_FILES['csv_upload'])){
		
		if ( 
			! isset( $_POST['csv_file_submit'] ) 
			|| ! wp_verify_nonce( $_POST['csv_file_submit'], 'file_validate_submit' ) 
		) {
		 
		   print 'Sorry, your nonce did not verify.';
		   exit;		 
		}

			$csv = $_FILES['csv_upload'];

			$file_name = zepson_sms_clean_name($csv['name']);
			$file_tmp = $csv['tmp_name'];
			$fle_location = plugin_dir_path( __FILE__ ) . 'uploads/'.zepson_sms_uniqidReal().'_'.$file_name;
			
			global $wpdb;
						
			$table_name = $wpdb->prefix . 'zepson_sms_plugin_saved_contacts';	

			$ext = pathinfo($file_name, PATHINFO_EXTENSION);

			$mimes = ['application/vnd.ms-excel','text/plain','text/csv','application/csv', 'application/x-csv', 'text/comma-separated-values', 'text/x-comma-separated-values'];
			
			if(in_array($csv['type'],$mimes) && $ext == 'csv'){
				$red = move_uploaded_file($file_tmp,$fle_location);
				if( $red ) {					
						$file_success = 'yes';
					} else {
						echo "Not uploaded because of error #". $csv["error"];
					}

				try {
					$csv_contacts = array_map('str_getcsv', file($fle_location));

					$the_contact_group = zepson_sms_clean_name($_POST['contact_group']);

					if ( $the_contact_group == '' || !$the_contact_group){
						$the_contact_group = 'general';
					}

					$contact_group = ucwords($the_contact_group);

					$db_contact_group = strtolower($contact_group);

					if ($db_contact_group != 'general'){						
						
						$zepson_sms_contact_groups = get_option( 'zepson_sms_contact_groups' );

						$date = date("F j, Y, g:i a");

						$zepson_sms_contact_groups += [$db_contact_group => $date];
					
						update_option( 'zepson_sms_contact_groups', $zepson_sms_contact_groups );						 

					}
					
					echo '
					</br>
					<table class="results-table">
						<tr class="table-head">
							<th colspan="3" class="sub-table mid"> Contact Group : '.strtoupper($contact_group).'</th>
						</tr>
						<tr class="table-head">
							<th class="sub-table"> </th>
							<th class="sub-table">Phone Number</th>
							<th class="sub-table">Name</th>
						</tr>';

					$total_contacts = count($csv_contacts);					
					$invalid_numbers = 0;
					$row_number = 0;

					foreach ($csv_contacts as $csv_contact) {

						++$row_number;

						$csvColumns = count($csv_contact);

						if ( $csvColumns < 1 || $csvColumns > 2 ){
							echo '
								<tr>
									<td class="sub-table">'.esc_html($row_number).'</td>
									<td class="sub-table" colspan = "2"><span class="at-warning"><strong>Not Uploaded</strong> : Row Number <strong>'.esc_html($row_number).'</strong> has too few or too many columns. Only One or Two Columns (Phone or Phone and Name are Accepted).</span></td>
								</tr>';
							
							++$invalid_numbers;
							continue;
						}

						$phone_number =  zepson_sms_clean_name(str_replace(' ', '', $csv_contact[0]));
						$name = zepson_sms_clean_name($csv_contact[1]);
						
						if ($csvColumns == 1){
							$name = 'Not Provided';
						}

						if (!preg_match("/^\+(?:[0-9] ?){11,14}[0-9]$/", $phone_number)) {
							echo '
								<tr>
									<td class="sub-table">'.esc_html($row_number).'</td>
									<td class="sub-table" colspan = "2"><span class="at-warning"><strong>Not Uploaded</strong> : Row Number <strong>'.esc_html($row_number).'</strong> has an invalid phone number - '.$phone_number.'.</span></td>
								</tr>';

							++$invalid_numbers;
							continue;
						}

						$wpdb->insert( 
							$table_name, 
							array( 
								'time' => current_time( 'mysql' ), 
								'contact_name' => $name, 
								'contact_phone' => $phone_number, 
								'contact_group' => $db_contact_group
							) 
						);

						echo'						
							<tr>
								<td class="sub-table">'.esc_html($row_number).'</td>
								<td class="sub-table">'.esc_html($phone_number).'</td>
								<td class="sub-table">'.esc_html($name).'</td>
							</tr>
						';	
					}

					$uploaded_contacts = $total_contacts - $invalid_numbers;
						
					echo '				
						<tr class="table-head">
							<th class="sub-table" colspan = "3"> Contacts Uploaded : '. esc_html($uploaded_contacts).' / '.esc_html($total_contacts) .' &nbsp;&nbsp;&nbsp;Failed Uploads : '.esc_html($invalid_numbers).'  &nbsp;&nbsp;&nbsp;Successful Uploads : '.esc_html($uploaded_contacts).'  &nbsp;&nbsp;&nbsp;Total Contacts Provided : '.esc_html($total_contacts).' </th>
						</tr>
					</table>';
				}
				catch (exception $e) {
					print_r ($e);
				}
			} else {
				echo "Error uploading file because it is not a valid csv file.";
			}
			
	}
}

function zepson_sms_settings_page() { 
?>
<div class="wrap" style="border: 10px; border-radius: 15px; padding:20px;">
	<h2>ZEPSON SMS Plugin Options</h2>   
	<p>You will need to get these details from your AfricasTalking account. You can check the website for more details here. <a href="https://africastalking.com/sms/bulksms">https://africastalking.com/sms/bulksms</a></p>
	
	<?php settings_errors(); ?>
	
	<form method="post" action="options.php">
		<?php settings_fields( 'zepson_sms-settings-group' ); ?>
		<?php $zepson_sms_options = get_option( 'zepson_sms_options' ); ?>
		<table class="form-table">
			 
			<tr valign="top">
				<th scope="row">API Key</th>
				<td><input type="text"
				name="zepson_sms_options[option_api_key]"
				value="<?php echo esc_attr(
				$zepson_sms_options['option_api_key'] ); ?>" /></td>
			</tr>  
			<tr valign="top">
				<th scope="row">Sender ID</th>
				<td><input type="text"
				name="zepson_sms_options[option_shortcode]"
				value="<?php echo esc_attr(
				$zepson_sms_options['option_shortcode'] ); ?>" required /></td>
			</tr>
		</table>  
		<p class="submit">
			<?php wp_nonce_field( 'zepson_sms-settings-group', 'zepson_sms_settings_group' ); ?>
			<input type="submit" class="button-primary"
			 value="Save Changes" />
		</p>  
	</form>
</div>

<?php
}

function zepson_sms_support_page() { 
?>
<div class="wrap" style="border: 10px; border-radius: 15px; padding:20px;">
	<h2>ZEPSON SMS Support Details</h2>   
	<p>For any enquiries, visit our website at <a href="https://zepsonsms.co.tz">Zepson SMS Website</a> or whatsapp ++255752771650</p>

	<table class="form-table">
		<tr valign="top">
			<td scope="row" style="width:30%">Bug Reports</td>
			<td scope="row">Bug reports for the Zepson SMS Wordpress Plugin are welcomed in the <a href="https://github.com/Zepson-SMS/zepsonsms-wordpress-plugin">repository on GitHub</a> Please note that GitHub is not a support forum, and that issues that aren’t properly qualified as bugs will be closed.</td>
		</tr>
		<tr valign="top">
			<td scope="row" style="width:30%">Email</td>
			<td scope="row">support@zepsonsms.co.tz</td>
		</tr>
		</table>  		
</div>

<?php
}
 
function zepson_sms_scripts($hook) {
	
    if ( 'toplevel_page_zepson_sms_main_menu' != $hook ) {
        return;
	}
	
	$active_tab = zepson_sms_clean_name($_GET[ 'tab' ]);
 
	wp_enqueue_style('boot_css', plugins_url('css/main.css',__FILE__ ));
	
	if ($active_tab == 'send_sms' || !$active_tab || $active_tab == '' ){
		wp_enqueue_script('main_js', plugins_url('js/main.js',__FILE__ ));
	}
	else if ($active_tab == 'add_contacts') {
		wp_enqueue_script('upload_js', plugins_url('js/upload.js',__FILE__ ));
	}
	else if ($active_tab == 'manage_contacts') {
		wp_enqueue_script('manage_js', plugins_url('js/manage.js',__FILE__ ));
	}
}