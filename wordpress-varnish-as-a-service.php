<?php
/*
Plugin Name: UCF WordPress Varnish as a Service
Version: 1.3.1
Author: Joan Artés
Author URI: http://joanartes.com/
Plugin URI: http://joanartes.com/wordpress-varnish-as-a-service/
GitHub Plugin URI: UCF/UCF-WordPress-Varnish-as-a-Service
GitHub Plugin URI: https://github.com/UCF/UCF-WordPress-Varnish-as-a-Service
Description: A plugin for purging Varnish cache when content is published or edited. It works with HTTP purge and Admin Port purge. Works with Varnish 2 (PURGE) and Varnish 3 (BAN) versions. Based on WordPress Varnish and Plugin Varnish Purges.
*/
if ( ! defined( 'WPINC' ) ) {
    die;
}

if ( ! class_exists( 'WPVarnish' ) ) {
	class WPVarnish {
		public $commenter;
		public $is_multisite=false;
		public $is_network_activated=false;
		public $plugin_group='wp-varnish-aas';

		/**
		 * __construct
		 * Set default values and options for plugin.
		 * Should be backward compatible back to PHP4.
		 * @since v2.0.0
		 **/
		public function __construct() {
			global $post;

			if ( !function_exists( 'is_plugin_active_for_network' ) ) {
				// Makes sure the plugin is defined before trying to use it
				require_once ABSPATH . '/wp-admin/includes/plugin.php';
			}
			if ( is_multisite() ) {
				$this->is_multisite=true;
			}
			if ( is_multisite() && is_plugin_active_for_network( plugin_basename( __FILE__ ) ) ) {
				$this->is_network_activated=true;
				add_action('network_admin_menu', array(&$this, 'WPVarnishAdminMenu'));
			}
			add_action('admin_menu', array(&$this, 'WPVarnishAdminMenu'));
			register_activation_hook( __FILE__, array( &$this, 'activate' ) );
			register_deactivation_hook( __FILE__, array( &$this, 'deactivate' ) );
			add_action('admin_init', array(&$this, 'WPVarnishLocalization'));
			add_action('edit_post', array(&$this, 'WPVarnishPurgePost'), 99);
			add_action('edit_post', array(&$this, 'WPVarnishPurgeCommonObjects'), 99);
			add_action('comment_post', array(&$this, 'WPVarnishPurgePostComments'),99);
			add_action('edit_comment', array(&$this, 'WPVarnishPurgePostComments'),99);
			add_action('trashed_comment', array(&$this, 'WPVarnishPurgePostComments'),99);
			add_action('untrashed_comment', array(&$this, 'WPVarnishPurgePostComments'),99);
			add_action('deleted_comment', array(&$this, 'WPVarnishPurgePostComments'),99);
			add_action('deleted_post', array(&$this, 'WPVarnishPurgePost'), 99);
			add_action('deleted_post', array(&$this, 'WPVarnishPurgeCommonObjects'), 99);
			add_action('add_attachment', array(&$this, 'WPVarnishPurgeAttachment'), 99);
			add_action('edit_attachment', array(&$this, 'WPVarnishPurgeAttachment'), 99);
			add_action('delete_attachment', array(&$this, 'WPVarnishPurgeAttachment'), 99);
			add_action('wp_update_nav_menu', array(&$this, 'WPVarnishPurgeAll'), 99);
			add_action('wp_delete_nav_menu', array(&$this, 'WPVarnishPurgeAll'), 99);
			add_action('wp_add_nav_menu_item', array(&$this, 'WPVarnishPurgeAll'), 99);
			add_action('wp_update_nav_menu_item', array(&$this, 'WPVarnishPurgeAll'), 99);
			add_action('update_option_category_base', array(&$this, 'WPVarnishPurgeAll'), 99);
			add_action('update_option_tag_base', array(&$this, 'WPVarnishPurgeAll'), 99);
			add_action('permalink_structure_changed', array(&$this, 'WPVarnishPurgeAll'), 99);
			add_filter('wp_get_current_commenter', array(&$this, "wp_get_current_commenter_varnish"));
		}
		// Wordpress function 'get_site_option' and 'get_option'
		function get_this_plugin_option($option_name) {
			if($this->is_network_activated === true) {
				// Get network site option
				return get_site_option($option_name);
			}
			else {
				// Get blog option
				return get_option($option_name);
			}
		}
		// Wordpress function 'delete_this_plugin_option' and 'delete_option'
		function delete_this_plugin_option($option_name) {
			if($this->is_multisite === true) {
				// Delete network site option
				return delete_site_option($option_name);
			}
			else {
				// Delete blog option
				return delete_option($option_name);
			}
		}
		// Wordpress function 'add_this_plugin_option' and 'add_option'
		function add_this_plugin_option($option_name, $default_value, $depricated=null, $autoload=null ) {
			#Set default value for extra parameters
			if ($depricated === null){
				$depricated = '';
			}
			if ($autoload === null){
				$autoload = 'yes';
			}
			if($this->is_multisite === true) {
				// Add network site option
				return add_site_option($option_name, $default_value);
			}
			else {
				// Add blog option
				return add_option($option_name, $default_value, $depricated, $autoload);
			}
		}
		// Wordpress function 'update_site_option' and 'update_option'
		function update_this_plugin_option($option_name, $option_value) {
			if($this->is_network_activated === true) {
				// Update network site option
				return update_site_option($option_name, $option_value);
			}
			else {
			// Update blog option
			return update_option($option_name, $option_value);
			}
		}
		function activate() {
			#Default values
			$wpv_addr_optval_1 = "127.0.0.1";
			$wpv_port_optval_1 = "80";
			$wpv_secret_optval_1 = "";
			$wpv_timeout_optval_1 = 5;
			$wpv_use_adminport_optval_1 = 0;
			$wpv_use_version_optval_1 = 3;
			$wpv_server_optval_1 = 0;
			$wpv_addr_optval_2 = "127.0.0.1";
			$wpv_port_optval_2 = "80";
			$wpv_secret_optval_2 = "";
			$wpv_timeout_optval_2 = 5;
			$wpv_use_adminport_optval_2 = 0;
			$wpv_use_version_optval_2 = 3;
			$wpv_server_optval_2 = 0;
			$wpv_addr_optval_3 = "127.0.0.1";
			$wpv_port_optval_3 = "80";
			$wpv_secret_optval_3 = "";
			$wpv_timeout_optval_3 = 5;
			$wpv_use_adminport_optval_3 = 0;
			$wpv_use_version_optval_3 = 3;
			$wpv_server_optval_3 = 0;
			$this->add_this_plugin_option("wpvarnish_addr_1", $wpv_addr_optval_1);
			$this->add_this_plugin_option("wpvarnish_port_1", $wpv_port_optval_1);
			$this->add_this_plugin_option("wpvarnish_secret_1", $wpv_secret_optval_1);
			$this->add_this_plugin_option("wpvarnish_timeout_1", $wpv_timeout_optval_1);
			$this->add_this_plugin_option("wpvarnish_use_version_1", $wpv_use_version_optval_1);
			$this->add_this_plugin_option("wpvarnish_use_adminport_1", $wpv_use_adminport_optval_1);
			$this->add_this_plugin_option("wpvarnish_server_1", $wpv_server_optval_1);
			$this->add_this_plugin_option("wpvarnish_addr_2", $wpv_addr_optval_2);
			$this->add_this_plugin_option("wpvarnish_port_2", $wpv_port_optval_2);
			$this->add_this_plugin_option("wpvarnish_secret_2", $wpv_secret_optval_2);
			$this->add_this_plugin_option("wpvarnish_timeout_2", $wpv_timeout_optval_2);
			$this->add_this_plugin_option("wpvarnish_use_version_2", $wpv_use_version_optval_2);
			$this->add_this_plugin_option("wpvarnish_use_adminport_2", $wpv_use_adminport_optval_2);
			$this->add_this_plugin_option("wpvarnish_server_2", $wpv_server_optval_2);
			$this->add_this_plugin_option("wpvarnish_addr_3", $wpv_addr_optval_3);
			$this->add_this_plugin_option("wpvarnish_port_3", $wpv_port_optval_3);
			$this->add_this_plugin_option("wpvarnish_secret_3", $wpv_secret_optval_3);
			$this->add_this_plugin_option("wpvarnish_timeout_3", $wpv_timeout_optval_3);
			$this->add_this_plugin_option("wpvarnish_use_version_3", $wpv_use_version_optval_3);
			$this->add_this_plugin_option("wpvarnish_use_adminport_3", $wpv_use_adminport_optval_3);
			$this->add_this_plugin_option("wpvarnish_server_3", $wpv_server_optval_3);
		}
		function deactivate() {
			$this->delete_this_plugin_option("wpvarnish_addr_1");
			$this->delete_this_plugin_option("wpvarnish_port_1");
			$this->delete_this_plugin_option("wpvarnish_secret_1");
			$this->delete_this_plugin_option("wpvarnish_timeout_1");
			$this->delete_this_plugin_option("wpvarnish_use_version_1");
			$this->delete_this_plugin_option("wpvarnish_use_adminport_1");
			$this->delete_this_plugin_option("wpvarnish_server_1");
			$this->delete_this_plugin_option("wpvarnish_addr_2");
			$this->delete_this_plugin_option("wpvarnish_port_2");
			$this->delete_this_plugin_option("wpvarnish_secret_2");
			$this->delete_this_plugin_option("wpvarnish_timeout_2");
			$this->delete_this_plugin_option("wpvarnish_use_version_2");
			$this->delete_this_plugin_option("wpvarnish_use_adminport_2");
			$this->delete_this_plugin_option("wpvarnish_server_2");
			$this->delete_this_plugin_option("wpvarnish_addr_3");
			$this->delete_this_plugin_option("wpvarnish_port_3");
			$this->delete_this_plugin_option("wpvarnish_secret_3");
			$this->delete_this_plugin_option("wpvarnish_timeout_3");
			$this->delete_this_plugin_option("wpvarnish_use_version_3");
			$this->delete_this_plugin_option("wpvarnish_use_adminport_3");
			$this->delete_this_plugin_option("wpvarnish_server_3");
		}
		function wp_get_current_commenter_varnish($commenter) {
			if (get_query_var($this->query)) {
				return $commenter;
			} else {
				return array('comment_author' => '', 'comment_author_email' => '', 'comment_author_url' => '');
			}
		}
		function WPVarnishLocalization() {
			load_plugin_textdomain($this->plugin_group,false,dirname(plugin_basename(__FILE__)).'/lang/');
		}
		function WPVarnishPurgeCommonObjects() {
			$this->WPVarnishPurgeObject("/");
			$this->WPVarnishPurgeObject("(.*)/feed/(.*)");
			$this->WPVarnishPurgeObject("(.*)/trackback/(.*)");
			$this->WPVarnishPurgeObject("/page/(.*)");
		}
		function WPVarnishPurgeAll() {
			$this->WPVarnishPurgeObject("(.*)");
		}
		function WPVarnishPurgePost($wpv_postid) {
			$wpv_url = get_permalink($wpv_postid);
			$wpv_permalink = str_replace(get_bloginfo("wpurl"),"",$wpv_url);
			$this->WPVarnishPurgeObject($wpv_permalink);
			$this->WPVarnishPurgeObject($wpv_permalink."page/(.*)");
		}
		function WPVarnishPurgePostComments($wpv_commentid) {
			$comment = get_comment($wpv_commentid);
			$wpv_commentapproved = $comment->comment_approved;
			if ($wpv_commentapproved == 1 || $wpv_commentapproved == 'trash') {
				$wpv_postid = $comment->comment_post_ID;
				$this->WPVarnishPurgeObject('comments_popup='.$wpv_postid);
			}
		}
		function WPVarnishPurgeAttachment($wpv_attachmentid) {
			$wpv_url = wp_get_attachment_url($wpv_attachmentid);
			if ($wpv_url) {
				// Handle removing all variants of the media file (i.e. image-100x100.jpg)
				$wpv_parsed_media_url = parse_url($wpv_url);
				$wpv_path_parts = pathinfo($wpv_parsed_media_url['path']);
				$wpv_ban_url = $wpv_path_parts['dirname'] . '/' . $wpv_path_parts['filename'] . '.*\.' . $wpv_path_parts['extension'];
				$this->WPVarnishPurgeObject($wpv_ban_url);
			}
		}
		function WPVarnishAdminMenu() {
			$page_title = 'Varnish as a Service Configuration';
			$menu_title = 'Varnish aaS';
			$menu_slug  = 'WPVarnish';
			$file_name  = 'options.php';
			if($this->is_network_activated === true) {
				add_submenu_page('settings.php', __($page_title,$this->plugin_group), $menu_title, 'manage_network', $menu_slug, array(&$this, 'WPVarnishAdmin'));
			}
			#Add option pages anyway, even for multisites.
			add_options_page(__($page_title,$this->plugin_group), $menu_title, 'manage_options', $menu_slug, array(&$this, 'WPVarnishAdmin'));
		}
		function WPVarnishAdmin() {
			if($this->is_network_activated === true) {
				if ( !current_user_can( 'manage_network' ) ) {
					wp_die( __( 'You do not have permission to access this page.' ) );
				}
			} else {
				if ( !current_user_can( 'manage_options' ) ) {
					wp_die( __( 'You do not have permission to access this page.' ) );
				}
			}
			if($_SERVER["REQUEST_METHOD"] == "POST") {
				if(isset($_POST['wpvarnish_admin'])) {
					if(isset($_POST["wpvarnish_addr_1"]))
						$this->update_this_plugin_option("wpvarnish_addr_1", trim(strip_tags($_POST["wpvarnish_addr_1"])));
					if(isset($_POST["wpvarnish_port_1"]))
						$this->update_this_plugin_option("wpvarnish_port_1", (int)trim(strip_tags($_POST["wpvarnish_port_1"])));
					if(isset($_POST["wpvarnish_secret_1"]))
						$this->update_this_plugin_option("wpvarnish_secret_1", trim(strip_tags($_POST["wpvarnish_secret_1"])));
					if(isset($_POST["wpvarnish_timeout_1"]))
						$this->update_this_plugin_option("wpvarnish_timeout_1", (int)trim(strip_tags($_POST["wpvarnish_timeout_1"])));
					if(isset($_POST["wpvarnish_use_adminport_1"]))
						$this->update_this_plugin_option("wpvarnish_use_adminport_1", 1);
					else
						$this->update_this_plugin_option("wpvarnish_use_adminport_1", 0);
					if(isset($_POST["wpvarnish_use_version_1"]))
						$this->update_this_plugin_option("wpvarnish_use_version_1", $_POST["wpvarnish_use_version_1"]);
					if(isset($_POST["wpvarnish_server_1"]))
						$this->update_this_plugin_option("wpvarnish_server_1", 1);
					else
						$this->update_this_plugin_option("wpvarnish_server_1", 0);
					if(isset($_POST["wpvarnish_addr_2"]))
						$this->update_this_plugin_option("wpvarnish_addr_2", trim(strip_tags($_POST["wpvarnish_addr_2"])));
					if(isset($_POST["wpvarnish_port_2"]))
						$this->update_this_plugin_option("wpvarnish_port_2", (int)trim(strip_tags($_POST["wpvarnish_port_2"])));
					if(isset($_POST["wpvarnish_secret_2"]))
						$this->update_this_plugin_option("wpvarnish_secret_2", trim(strip_tags($_POST["wpvarnish_secret_2"])));
					if(isset($_POST["wpvarnish_timeout_2"]))
						$this->update_this_plugin_option("wpvarnish_timeout_2", (int)trim(strip_tags($_POST["wpvarnish_timeout_2"])));
					if(isset($_POST["wpvarnish_use_adminport_2"]))
						$this->update_this_plugin_option("wpvarnish_use_adminport_2", 1);
					else
						$this->update_this_plugin_option("wpvarnish_use_adminport_2", 0);
					if(isset($_POST["wpvarnish_use_version_2"]))
						$this->update_this_plugin_option("wpvarnish_use_version_2", $_POST["wpvarnish_use_version_2"]);
					if(isset($_POST["wpvarnish_server_2"]))
						$this->update_this_plugin_option("wpvarnish_server_2", 1);
					else
						$this->update_this_plugin_option("wpvarnish_server_2", 0);
					if(isset($_POST["wpvarnish_addr_3"]))
						$this->update_this_plugin_option("wpvarnish_addr_3", trim(strip_tags($_POST["wpvarnish_addr_3"])));
					if(isset($_POST["wpvarnish_port_3"]))
						$this->update_this_plugin_option("wpvarnish_port_3", (int)trim(strip_tags($_POST["wpvarnish_port_3"])));
					if(isset($_POST["wpvarnish_secret_3"]))
						$this->update_this_plugin_option("wpvarnish_secret_3", trim(strip_tags($_POST["wpvarnish_secret_3"])));
					if(isset($_POST["wpvarnish_timeout_3"]))
						$this->update_this_plugin_option("wpvarnish_timeout_3", (int)trim(strip_tags($_POST["wpvarnish_timeout_3"])));
					if(isset($_POST["wpvarnish_use_adminport_3"]))
						$this->update_this_plugin_option("wpvarnish_use_adminport_3", 1);
					else
						$this->update_this_plugin_option("wpvarnish_use_adminport_3", 0);
					if(isset($_POST["wpvarnish_use_version_3"]))
						$this->update_this_plugin_option("wpvarnish_use_version_3", $_POST["wpvarnish_use_version_3"]);
					if(isset($_POST["wpvarnish_server_3"]))
						$this->update_this_plugin_option("wpvarnish_server_3", 1);
					else
						$this->update_this_plugin_option("wpvarnish_server_3", 0);
					?>
						<div class="updated"><p><?php echo __('Settings Saved!',$this->plugin_group); ?></p></div>
					<?php
				}
				if(isset($_POST['wpvarnish_clear_blog_cache'])) {
					?>
					<div class="updated"><p><?php echo __('Purging Everything!',$this->plugin_group); ?></p></div>
					<?php
						$this->WPVarnishPurgeAll();
					}
				if (isset($_POST['wpvarnish_test_blog_cache_1'])) {
					?>
						<div class="updated"><p><?php echo __('Testing Connection to Varnish Server',$this->plugin_group); ?> 1</p></div>
					<?php
					$this->WPVarnishTestConnect(1);
				}
				if (isset($_POST['wpvarnish_test_blog_cache_2'])) {
					?>
						<div class="updated"><p><?php echo __('Testing Connection to Varnish Server',$this->plugin_group); ?> 2</p></div>
					<?php
					$this->WPVarnishTestConnect(2);
				}
				if (isset($_POST['wpvarnish_test_blog_cache_3'])) {
					?>
						<div class="updated"><p><?php echo __('Testing Connection to Varnish Server',$this->plugin_group); ?> 3</p></div>
					<?php
										$this->WPVarnishTestConnect(3);
				}
			}
			?>
				<div class="wrap">
					<h2><?php echo __("Varnish as a Service Administration",$this->plugin_group); ?></h2>
					<form method="post" action="<?php echo $_SERVER['REQUEST_URI']; ?>">
					<?php if((strpos($_SERVER['REQUEST_URI'], 'network') !== false) || ($this->is_multisite === false)){ ?>
					<table width="100%">
						<tr valign="top">
							<td>
								<dl>
									<dt><label for="varactive1"><?php echo __("Server Activated",$this->plugin_group); ?></label></dt>
									<dd><input id="varactive1" type="checkbox" name="wpvarnish_server_1" value="1"<?php if($this->get_this_plugin_option("wpvarnish_server_1") == 1) echo ' checked'; ?>></dd>
									<dt><label for="varipaddress1"><?php echo __("Server IP Address",$this->plugin_group); ?></label></dt>
									<dd><input id="varipaddress1" type="text" name="wpvarnish_addr_1" value="<?php echo $this->get_this_plugin_option("wpvarnish_addr_1"); ?>" style="width: 120px;"></dd>
									<dt><label for="varport1"><?php echo __("Server Port",$this->plugin_group); ?></label></dt>
									<dd><input id="varport1" type="text" name="wpvarnish_port_1" value="<?php echo $this->get_this_plugin_option("wpvarnish_port_1"); ?>" style="width: 50px;"></dd>
									<dt><label for="varuseadmin1"><?php echo __("Use Admin port",$this->plugin_group); ?></label></dt>
									<dd><input id="varuseadmin1" type="checkbox" name="wpvarnish_use_adminport_1" value="1"<?php if($this->get_this_plugin_option("wpvarnish_use_adminport_1") == 1) echo ' checked'; ?>></dd>
									<dt><label for="varsecret1"><?php echo __("Secret Key",$this->plugin_group); ?></label></dt>
									<dd><input id="varsecret1" type="text" name="wpvarnish_secret_1" value="<?php echo $this->get_this_plugin_option("wpvarnish_secret_1"); ?>" style="width: 260px;"></dd>
									<dt><label for="varversion1"><?php echo __("Version",$this->plugin_group); ?></label></dt>
									<dd><select id="varversion1" name="wpvarnish_use_version_1">
										<option value="2"<?php if($this->get_this_plugin_option("wpvarnish_use_version_1") == 2) echo " selected"; ?>>v2 - PURGE</option>
										<option value="3"<?php if($this->get_this_plugin_option("wpvarnish_use_version_1") == 3) echo " selected"; ?>>v3 - BAN</option>
									</select></dd>
									<dt><label for="vartimeout1"><?php echo __("Timeout",$this->plugin_group); ?></label></dt>
									<dd><input id="vartimeout1" class="small-text" type="text" name="wpvarnish_timeout_1" value="<?php echo $this->get_this_plugin_option("wpvarnish_timeout_1"); ?>"> <?php echo __("seconds",$this->plugin_group); ?></dd>
									<dt><?php echo __("Test Connection to Varnish",$this->plugin_group); ?></dt>
									<dd><input type="submit" class="button-secondary" name="wpvarnish_test_blog_cache_1" value="<?php echo __("Test Connection to Varnish",$this->plugin_group); ?>"></dd>
								</dl>
							</td>
							<td>
								<dl>
									<dt><label for="varactive2"><?php echo __("Server Activated",$this->plugin_group); ?></label></dt>
									<dd><input id="varactive2" type="checkbox" name="wpvarnish_server_2" value="1"<?php if($this->get_this_plugin_option("wpvarnish_server_2") == 1) echo ' checked'; ?>></dd>
									<dt><label for="varipaddress2"><?php echo __("Server IP Address",$this->plugin_group); ?></label></dt>
									<dd><input id="varipaddress2" type="text" name="wpvarnish_addr_2" value="<?php echo $this->get_this_plugin_option("wpvarnish_addr_2"); ?>" style="width: 120px;"></dd>
									<dt><label for="varport2"><?php echo __("Server Port",$this->plugin_group); ?></label></dt>
									<dd><input id="varport2" type="text" name="wpvarnish_port_2" value="<?php echo $this->get_this_plugin_option("wpvarnish_port_2"); ?>" style="width: 50px;"></dd>
									<dt><label for="varuseadmin2"><?php echo __("Use Admin port",$this->plugin_group); ?></label></dt>
									<dd><input id="varuseadmin2" type="checkbox" name="wpvarnish_use_adminport_2" value="1"<?php if($this->get_this_plugin_option("wpvarnish_use_adminport_2") == 1) echo ' checked'; ?>></dd>
									<dt><label for="varsecret2"><?php echo __("Secret Key",$this->plugin_group); ?></label></dt>
									<dd><input id="varsecret2" type="text" name="wpvarnish_secret_2" value="<?php echo $this->get_this_plugin_option("wpvarnish_secret_2"); ?>" style="width: 260px;"></dd>
									<dt><label for="varversion2"><?php echo __("Version",$this->plugin_group); ?></label></dt>
									<dd><select id="varversion2" name="wpvarnish_use_version_2">
										<option value="2"<?php if($this->get_this_plugin_option("wpvarnish_use_version_2") == 2) echo " selected"; ?>>v2 - PURGE</option>
										<option value="3"<?php if($this->get_this_plugin_option("wpvarnish_use_version_2") == 3) echo " selected"; ?>>v3 - BAN</option>
									</select></dd>
									<dt><label for="vartimeout2"><?php echo __("Timeout",$this->plugin_group); ?></label></dt>
									<dd><input id="vartimeout2" class="small-text" type="text" name="wpvarnish_timeout_2" value="<?php echo $this->get_this_plugin_option("wpvarnish_timeout_2"); ?>"> <?php echo __("seconds",$this->plugin_group); ?></dd>
									<dt><?php echo __("Test Connection to Varnish",$this->plugin_group); ?></dt>
									<dd><input type="submit" class="button-secondary" name="wpvarnish_test_blog_cache_2" value="<?php echo __("Test Connection to Varnish",$this->plugin_group); ?>"></dd>
								</dl>
							</td>
							<td>
								<dl>
									<dt><label for="varactive3"><?php echo __("Server Activated",$this->plugin_group); ?></label></dt>
									<dd><input id="varactive3" type="checkbox" name="wpvarnish_server_3" value="1"<?php if($this->get_this_plugin_option("wpvarnish_server_3") == 1) echo ' checked'; ?>></dd>
									<dt><label for="varipaddress3"><?php echo __("Server IP Address",$this->plugin_group); ?></label></dt>
									<dd><input id="varipaddress3" type="text" name="wpvarnish_addr_3" value="<?php echo $this->get_this_plugin_option("wpvarnish_addr_3"); ?>" style="width: 120px;"></dd>
									<dt><label for="varport3"><?php echo __("Server Port",$this->plugin_group); ?></label></dt>
									<dd><input id="varport3" type="text" name="wpvarnish_port_3" value="<?php echo $this->get_this_plugin_option("wpvarnish_port_3"); ?>" style="width: 50px;"></dd>
									<dt><label for="varuseadmin3"><?php echo __("Use Admin port",$this->plugin_group); ?></label></dt>
									<dd><input id="varuseadmin3" type="checkbox" name="wpvarnish_use_adminport_3" value="1"<?php if($this->get_this_plugin_option("wpvarnish_use_adminport_3") == 1) echo ' checked'; ?>></dd>
									<dt><label for="varsecret3"><?php echo __("Secret Key",$this->plugin_group); ?></label></dt>
									<dd><input id="varsecret3" type="text" name="wpvarnish_secret_3" value="<?php echo $this->get_this_plugin_option("wpvarnish_secret_3"); ?>" style="width: 260px;"></dd>
									<dt><label for="varversion3"><?php echo __("Version",$this->plugin_group); ?></label></dt>
									<dd><select id="varversion3" name="wpvarnish_use_version_3">
										<option value="2"<?php if($this->get_this_plugin_option("wpvarnish_use_version_3") == 2) echo " selected"; ?>>v2 - PURGE</option>
										<option value="3"<?php if($this->get_this_plugin_option("wpvarnish_use_version_3") == 3) echo " selected"; ?>>v3 - BAN</option>
									</select></dd>
									<dt><label for="vartimeout3"><?php echo __("Timeout",$this->plugin_group); ?></label></dt>
									<dd><input id="vartimeout3" class="small-text" type="text" name="wpvarnish_timeout_3" value="<?php echo $this->get_this_plugin_option("wpvarnish_timeout_3"); ?>"> <?php echo __("seconds",$this->plugin_group); ?></dd>
									<dt><?php echo __("Test Connection to Varnish",$this->plugin_group); ?></dt>
									<dd><input type="submit" class="button-secondary" name="wpvarnish_test_blog_cache_3" value="<?php echo __("Test Connection to Varnish",$this->plugin_group); ?>"></dd>
								</dl>
							</td>
						</tr>
					</table>

					<p class="submit">
						<input type="submit" class="button-primary" name="wpvarnish_admin" value="<?php echo __("Save Changes",$this->plugin_group); ?>">
					<?php } ?>
						<input type="submit" class="button-secondary" name="wpvarnish_clear_blog_cache" value="<?php echo __("Purge All Blog Cache",$this->plugin_group); ?>"></p>
					</form>
				</div>
			<?php
		}
		function WPAuth($challenge, $secret) {
			$ctx = hash_init('sha256');
			hash_update($ctx, $challenge);
			hash_update($ctx, "\n");
			hash_update($ctx, $secret."\n");
			hash_update($ctx, $challenge);
			hash_update($ctx, "\n");
			$sha256 = hash_final($ctx);
			return $sha256;
		}
		function WPVarnishPurgeObject($wpv_url_obj) {
			global $varnish_servers;
			$j=0;
			if($this->get_this_plugin_option("wpvarnish_server_1")) {
				$array_wpv_purgeaddr[$j] = $this->get_this_plugin_option("wpvarnish_addr_1");
				$array_wpv_purgeport[$j] = $this->get_this_plugin_option("wpvarnish_port_1");
				$array_wpv_secret[$j] = $this->get_this_plugin_option("wpvarnish_secret_1");
				$array_wpv_timeout[$j] = $this->get_this_plugin_option("wpvarnish_timeout_1");
				$array_wpv_use_adminport[$j] = $this->get_this_plugin_option("wpvarnish_use_adminport_1");
				$array_wpv_use_version[$j] = $this->get_this_plugin_option("wpvarnish_use_version_1");
				$j++;
			}
			if($this->get_this_plugin_option("wpvarnish_server_2")) {
				$array_wpv_purgeaddr[$j] = $this->get_this_plugin_option("wpvarnish_addr_2");
				$array_wpv_purgeport[$j] = $this->get_this_plugin_option("wpvarnish_port_2");
				$array_wpv_secret[$j] = $this->get_this_plugin_option("wpvarnish_secret_2");
				$array_wpv_timeout[$j] = $this->get_this_plugin_option("wpvarnish_timeout_2");
				$array_wpv_use_adminport[$j] = $this->get_this_plugin_option("wpvarnish_use_adminport_2");
				$array_wpv_use_version[$j] = $this->get_this_plugin_option("wpvarnish_use_version_2");
				$j++;
			}
			if($this->get_this_plugin_option("wpvarnish_server_3")) {
				$array_wpv_purgeaddr[$j] = $this->get_this_plugin_option("wpvarnish_addr_3");
				$array_wpv_purgeport[$j] = $this->get_this_plugin_option("wpvarnish_port_3");
				$array_wpv_secret[$j] = $this->get_this_plugin_option("wpvarnish_secret_3");
				$array_wpv_timeout[$j] = $this->get_this_plugin_option("wpvarnish_timeout_3");
				$array_wpv_use_adminport[$j] = $this->get_this_plugin_option("wpvarnish_use_adminport_3");
				$array_wpv_use_version[$j] = $this->get_this_plugin_option("wpvarnish_use_version_3");
				$j++;
			}
			for($i=0; $i<$j; $i++) {
				$wpv_purgeaddr = $array_wpv_purgeaddr[$i];
				$wpv_purgeport = $array_wpv_purgeport[$i];
				$wpv_secret = $array_wpv_secret[$i];
				$wpv_timeout = $array_wpv_timeout[$i];
				$wpv_use_adminport = $array_wpv_use_adminport[$i];
				$wpv_use_version = $array_wpv_use_version[$i];
				$wpv_wpurl = get_bloginfo('wpurl');
				$wpv_replace_wpurl = '/^https?:\/\/([^\/]+)(.*)/i';
				$wpv_host = preg_replace($wpv_replace_wpurl, "$1", $wpv_wpurl);
				$wpv_blogaddr = preg_replace($wpv_replace_wpurl, "$2", $wpv_wpurl);
				$wpv_url = $wpv_blogaddr.$wpv_url_obj;
				$varnish_sock = fsockopen($wpv_purgeaddr, $wpv_purgeport, $errno, $errstr, $wpv_timeout);
				if($varnish_sock) {
					if($wpv_use_adminport) {
						$buf = fread($varnish_sock, 1024);
						if(preg_match('/(\w+)\s+Authentication required./', $buf, $matches)) {
							$auth = $this->WPAuth($matches[1], $wpv_secret);
							fwrite($varnish_sock, "auth ".$auth."\n");
							$buf = fread($varnish_sock, 1024);
							if(preg_match('/^200/', $buf)) {
								if ($wpv_use_version == 2) {
									$out = "purge req.url ~ ^$wpv_url && req.http.host == $wpv_host\n";
								} elseif ($wpv_use_version == 3) {
									$out = "ban req.url ~ ^$wpv_url && req.http.host == $wpv_host\n";
								} else {
									$out = "ban req.url ~ ^$wpv_url && req.http.host == $wpv_host\n";
								}
								fwrite($varnish_sock, $out."\n");
							}
						} else {
							if ($wpv_use_version == 2) {
								$out = "purge req.url ~ ^$wpv_url && req.http.host == $wpv_host\n";
							} elseif ($wpv_use_version == 3) {
								$out = "ban req.url ~ ^$wpv_url && req.http.host == $wpv_host\n";
							} else {
								$out = "ban req.url ~ ^$wpv_url && req.http.host == $wpv_host\n";
							}
							fwrite($varnish_sock, $out."\n");
						}
					} else {
						if ($wpv_use_version == 3) {
							$out = "BAN HTTP/1.0\r\n";
							$out .= "X-Ban-Url: $wpv_url\r\n";
							$out .= "X-Ban-Host: $wpv_host\r\n";
							$out .= "Connection: Close\r\n\r\n";
						} else {
							$out = "PURGE $wpv_url HTTP/1.0\r\n";
							$out .= "Host: $wpv_host\r\n";
							$out .= "Connection: Close\r\n\r\n";
						}
						fwrite($varnish_sock, $out."\n");
					}
					fclose($varnish_sock);
				}
			}
		}
		function WPVarnishTestConnect($servernum) {
			global $varnish_servers;
			$varnish_test_conn = "";
			if($servernum == 1) {
				$wpv_purgeaddr = $this->get_this_plugin_option("wpvarnish_addr_1");
				$wpv_purgeport = $this->get_this_plugin_option("wpvarnish_port_1");
				$wpv_secret = $this->get_this_plugin_option("wpvarnish_secret_1");
				$wpv_timeout = $this->get_this_plugin_option("wpvarnish_timeout_1");
				$wpv_use_adminport = $this->get_this_plugin_option("wpvarnish_use_adminport_1");
				$wpv_use_version = $this->get_this_plugin_option("wpvarnish_use_version_1");
			} elseif($servernum == 2) {
				$wpv_purgeaddr = $this->get_this_plugin_option("wpvarnish_addr_2");
				$wpv_purgeport = $this->get_this_plugin_option("wpvarnish_port_2");
				$wpv_secret = $this->get_this_plugin_option("wpvarnish_secret_2");
				$wpv_timeout = $this->get_this_plugin_option("wpvarnish_timeout_2");
				$wpv_use_adminport = $this->get_this_plugin_option("wpvarnish_use_adminport_2");
				$wpv_use_version = $this->get_this_plugin_option("wpvarnish_use_version_2");
			} elseif($servernum == 3) {
				$wpv_purgeaddr = $this->get_this_plugin_option("wpvarnish_addr_3");
				$wpv_purgeport = $this->get_this_plugin_option("wpvarnish_port_3");
				$wpv_secret = $this->get_this_plugin_option("wpvarnish_secret_3");
				$wpv_timeout = $this->get_this_plugin_option("wpvarnish_timeout_3");
				$wpv_use_adminport = $this->get_this_plugin_option("wpvarnish_use_adminport_3");
				$wpv_use_version = $this->get_this_plugin_option("wpvarnish_use_version_3");
			}
			$wpv_wpurl = get_bloginfo("wpurl");
			$wpv_replace_wpurl = '/^https?:\/\/([^\/]+)(.*)/i';
			$wpv_host = preg_replace($wpv_replace_wpurl, "$1", $wpv_wpurl);
			$wpv_blogaddr = preg_replace($wpv_replace_wpurl, "$2", $wpv_wpurl);
			$wpv_url = $wpv_blogaddr."/";
			$varnish_test_conn .= "<ul>\n";
			$varnish_test_conn .= "<li><span style=\"color: blue;\">".__("INFO - Testing Server",$this->plugin_group)." ".$servernum."</span></li>\n";
			$varnish_sock = fsockopen($wpv_purgeaddr, $wpv_purgeport, $errno, $errstr, $wpv_timeout);
			if($varnish_sock) {
				$varnish_test_conn .= "<li><span style=\"color: green;\">".__("OK - Connection to Server",$this->plugin_group)."</span></li>\n";
				if ($wpv_use_adminport) {
					$varnish_test_conn .= "<li><span style=\"color: blue;\">".__("INFO - Using Admin Port",$this->plugin_group)."</span></li>\n";
					$buf = fread($varnish_sock, 1024);
					if(preg_match('/(\w+)\s+Authentication required./', $buf, $matches)) {
						$auth = $this->WPAuth($matches[1], $wpv_secret);
						fwrite($varnish_sock, "auth ".$auth."\n");
						$buf = fread($varnish_sock, 1024);
						if(preg_match('/^200/', $buf)) {
							$varnish_test_conn .= "<li><span style=\"color: green;\">".__("OK - Authentication",$this->plugin_group)."</span></li>\n";
							if ($wpv_use_version == 2) {
								$out = "purge req.url ~ ^$wpv_url && req.http.host == $wpv_host\n";
							} elseif ($wpv_use_version == 3) {
								$out = "ban req.url ~ ^$wpv_url && req.http.host == $wpv_host\n";
							} else {
								$out = "ban req.url ~ ^$wpv_url && req.http.host == $wpv_host\n";
							}
							fwrite($varnish_sock, $out."\n");
							$buf = fread($varnish_sock, 256);
							if(preg_match('/^200/', $buf)) {
								$varnish_test_conn .= "<li><span style=\"color: green;\">".__("OK - Cache flush",$this->plugin_group)."</span></li>\n";
							} else {
								$varnish_test_conn .= "<li><span style=\"color: red;\">".__("KO - Cache flush",$this->plugin_group)."</span><br><small>".__("Verify your Varnish version",$this->plugin_group)."</small></li>\n";
							}
						} else {
							$varnish_test_conn .= "<li><span style=\"color: red;\">".__("KO - Invalid Secret Key",$this->plugin_group)."</span></li>\n";
						}
					} else {
						$varnish_test_conn .= "<li><span style=\"color: blue;\">".__("INFO - Authentication not required",$this->plugin_group)."</span></li>\n";
						if ($wpv_use_version == 2) {
							$out = "purge req.url ~ ^$wpv_url && req.http.host == $wpv_host\n";
						} elseif ($wpv_use_version == 3) {
							$out = "ban req.url ~ ^$wpv_url && req.http.host == $wpv_host\n";
						} else {
							$out = "ban req.url ~ ^$wpv_url && req.http.host == $wpv_host\n";
						}
						fwrite($varnish_sock, $out."\n");
						$buf = fread($varnish_sock, 256);
						if(preg_match('/^200/', $buf)) {
							$varnish_test_conn .= "<li><span style=\"color: green;\">".__("OK - Cache flush",$this->plugin_group)."</span></li>\n";
						} else {
							$varnish_test_conn .= "<li><span style=\"color: red;\">".__("KO - Cache flush",$this->plugin_group)."</span><br><small>".__("Verify your Varnish version",$this->plugin_group)."</small></li>\n";
						}
					}
				} else {
					if ($wpv_use_version == 3) {
						$varnish_test_conn .= "<li><span style=\"color: blue;\">".__("INFO - HTTP BAN",$this->plugin_group)."</span></li>\n";
						$out = "BAN HTTP/1.0\r\n";
						$out .= "X-Ban-Url: $wpv_url\r\n";
						$out .= "X-Ban-Host: $wpv_host\r\n";
						$out .= "Connection: Close\r\n\r\n";
						fwrite($varnish_sock, $out."\n");
						$buf = fread($varnish_sock, 256);
						if(preg_match('/200/', $buf)) {
							$varnish_test_conn .= "<li><span style=\"color: green;\">".__("OK - Cache flush",$this->plugin_group)."</span></li>\n";
						} else {
							$varnish_test_conn .= "<li><span style=\"color: red;\">".__("KO - Cache flush",$this->plugin_group)."</span><br><small>".__("Verify your Varnish version",$this->plugin_group)."</small></li>\n";
						}

					} else {
						$varnish_test_conn .= "<li><span style=\"color: blue;\">".__("INFO - HTTP Purge",$this->plugin_group)."</span></li>\n";
						$out = "PURGE $wpv_url HTTP/1.0\r\n";
						$out .= "Host: $wpv_host\r\n";
						$out .= "Connection: Close\r\n\r\n";
						fwrite($varnish_sock, $out);
						$buf = fread($varnish_sock, 256);
						if(preg_match('/200 OK/', $buf)) {
							$varnish_test_conn .= "<li><span style=\"color: green;\">".__("OK - Request",$this->plugin_group)."</span></li>\n";
						} else {
							$varnish_test_conn .= "<li><span style=\"color: red;\">".__("KO - Request",$this->plugin_group)."</span></li>\n";
						}
					}
				}
				fclose($varnish_sock);
			} else {
				$varnish_test_conn .= "<li><span style=\"color: red;\">".__("KO - Connection to Server",$this->plugin_group)."</span><br><small>".__("IP address or port closed. Verify your firewall or iptables.",$this->plugin_group)."</small></li>\n";
			}
			$varnish_test_conn .= "</ul>\n";
			?>
			<div class="updated"><?php echo $varnish_test_conn; ?></div>
			<?php
		}
	}
	if ( is_admin() ) {
		new WPVarnish();
	}
}