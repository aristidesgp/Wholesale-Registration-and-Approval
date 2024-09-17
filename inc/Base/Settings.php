<?php

/*
*
* @package aristidesgp
*
*/

namespace WRA\Inc\Base;

use WRA\Inc\Util\Helper;
use WRA\Inc\Base\Logs;

class Settings
{
	public function register()
	{
		add_action('show_user_profile', array($this, 'show_custom_user_fields'));
		add_action('edit_user_profile', array($this, 'show_custom_user_fields'));
		add_action('personal_options_update', array($this, 'save_custom_user_fields'));
		add_action('edit_user_profile_update', array($this, 'save_custom_user_fields'));

		add_filter('manage_users_columns', array($this, 'add_approval_status_column'));
		add_action('manage_users_custom_column', array($this, 'show_approval_status_column'), 10, 3);
		add_filter('views_users', array($this, 'add_approval_status_views'));
    	add_filter('pre_get_users', array($this, 'filter_users_by_approval_status'));

		add_action('wp_login', array($this, 'restrict_site_access'), 10, 2);
		add_filter('login_message', array($this, 'show_login_message'));

		add_action('admin_menu', array($this,'register_delete_users_page'));
		add_action('admin_init', array($this,'handle_delete_non_admin_users'));

		add_filter('woocommerce_locate_template', array($this, 'wrp_locate_template'), 10, 3);
		add_action('admin_menu', array($this, 'register_settings_page'));
        add_action('admin_init', array($this, 'save_register_page_url'));
		add_filter('custom_register_button_url', array($this, 'custom_register_button_url'));
	}

	function custom_register_button_url() {
		return get_option('register_page_url', '/register'); 
	}

	function register_settings_page() {
        add_management_page(
            __('Register Page URL Settings', 'wra'),
            __('Register Page URL', 'wra'),
            'manage_options',
            'register-page-url',
            array($this, 'render_settings_page')
        );
    }

    function render_settings_page() {
        ?>
        <div class="wrap">
            <h1><?php _e('Register Page URL Settings', 'wra'); ?></h1>
            <form method="post" action="options.php">
                <?php
                settings_fields('register_page_url_settings');
                do_settings_sections('register-page-url');
                submit_button();
                ?>
            </form>
        </div>
        <?php
    }

    function save_register_page_url() {
        register_setting('register_page_url_settings', 'register_page_url');

        add_settings_section(
            'register_page_url_section',
            __('Register Page URL', 'wra'),
            null,
            'register-page-url'
        );

        add_settings_field(
            'register_page_url_field',
            __('Register Page URL', 'wra'),
            array($this, 'render_register_page_url_field'),
            'register-page-url',
            'register_page_url_section'
        );
    }

    function render_register_page_url_field() {
        $url = get_option('register_page_url', '');
        echo '<input type="text" name="register_page_url" value="' . esc_attr($url) . '" class="regular-text">';
    }

	function wrp_locate_template($template, $template_name, $template_path)
    {
        $basename = basename($template);

        switch ($basename) {
			case 'form-login.php':
				$template = WRA_PLUGIN_PATH . 'templates/form-login.php';
				break;                
		}        

        return $template;
    }

	function register_delete_users_page() {
		add_management_page(
			__('Delete Non-Admin Users', 'wra'),
			__('Delete Users', 'wra'), 
			'manage_options', 
			'delete-users', 
			array($this, 'render_delete_users_page' )
		);
	}	
	
	function render_delete_users_page() {
		?>
		<div class="wrap">
			<h1><?php _e('Delete Non-Admin Users', 'wra'); ?></h1>
			<form method="post" action="">
				<?php wp_nonce_field('delete_non_admin_users_action', 'delete_non_admin_users_nonce'); ?>
				<p><?php _e('Click the button below to delete all users who are not administrators.', 'wra'); ?></p>
				<p><input type="submit" name="delete_non_admin_users" class="button button-primary" value="<?php _e('Delete Users', 'wra'); ?>" /></p>
			</form>
		</div>
		<?php
	}

	function handle_delete_non_admin_users() {
		if (isset($_POST['delete_non_admin_users']) && check_admin_referer('delete_non_admin_users_action', 'delete_non_admin_users_nonce')) {
			
			$users = get_users();
	
			foreach ($users as $user) {
				
				if (!in_array('administrator', $user->roles)) {
					
					wp_delete_user($user->ID);
				}
			}	
			
			wp_redirect(add_query_arg('deleted', 'true', wp_get_referer()));
			exit;
		}	
		
		if (isset($_GET['deleted']) && $_GET['deleted'] == 'true') {
			add_action('admin_notices', function() {
				echo '<div class="notice notice-success is-dismissible"><p>' . __('Non-admin users have been deleted.', 'wra') . '</p></div>';
			});
		}
	}

	public function restrict_site_access($user_login, $user) {
		$approval_status = get_user_meta($user->ID, 'approval_status', true);
	
		if (in_array('administrator', $user->roles)) {
			return;
		}
	
		if (in_array('customer', $user->roles) && $approval_status === 'approved') {
			return;
		}
	
		$redirect_url = add_query_arg('approval_status', 'not_approved', wp_login_url());
		wp_redirect($redirect_url);
		exit;
	}

	public function show_login_message($message) {
		if (isset($_GET['approval_status']) && $_GET['approval_status'] == 'not_approved') {
			$message .= '<div class="error"><p>' . __('Your account is not approved yet. Please contact the administrator.', 'wra') . '</p></div>';
		}
		return $message;
	}

	public function show_custom_user_fields($user) {
		?>
		<h3><?php _e('Wholesale Information', 'wra'); ?></h3>
		<table class="form-table">
			<tr>
				<th><label for="approval_status"><?php _e('Approval Status', 'wra'); ?></label></th>
				<td>
					<select name="approval_status" id="approval_status">
						<option value=""><?php _e('Select', 'wra'); ?></option>
						<option value="pending" <?php selected(get_user_meta($user->ID, 'approval_status', true), 'pending'); ?>><?php _e('Pending', 'wra'); ?></option>
						<option value="approved" <?php selected(get_user_meta($user->ID, 'approval_status', true), 'approved'); ?>><?php _e('Approved', 'wra'); ?></option>
						<option value="rejected" <?php selected(get_user_meta($user->ID, 'approval_status', true), 'rejected'); ?>><?php _e('Rejected', 'wra'); ?></option>
					</select>
				</td>
			</tr>
			<tr>
				<th><label for="business_name"><?php _e('Business Name', 'wra'); ?></label></th>
				<td>
					<input type="text" name="business_name" id="business_name" value="<?php echo esc_attr(get_user_meta($user->ID, 'business_name', true)); ?>" class="regular-text" /><br />
					<span class="description"><?php _e('Please enter your business name.', 'wra'); ?></span>
				</td>
			</tr>
			<tr>
				<th><label for="business_type"><?php _e('Business Type', 'wra'); ?></label></th>
				<td>
					<?php
					$business_types = get_user_meta($user->ID, 'business_type', true);
					$options = [
						'Brick & Mortar' => 'Brick & Mortar',
						'E-Commerce' => 'E-Commerce',
						'Facebook Group' => 'Facebook Group',
						'Live Seller/Comment Sold' => 'Live Seller/Comment Sold',
						'3rd Party Reseller Platforms (ie Amazon, Ebay, Poshmark etc)' => '3rd Party Reseller Platforms (ie Amazon, Ebay, Poshmark etc)'
					];
					foreach ($options as $value => $label) {
						$checked = in_array($value, (array) $business_types) ? 'checked' : '';
						echo '<label><input type="checkbox" name="business_type[]" value="' . esc_attr($value) . '" ' . $checked . '> ' . esc_html($label) . '</label><br>';
					}
					?>
				</td>
			</tr>
			<tr>
				<th><label for="website_url"><?php _e('Website URL', 'wra'); ?></label></th>
				<td>
					<input type="url" name="website_url" id="website_url" value="<?php echo esc_attr(get_user_meta($user->ID, 'website_url', true)); ?>" class="regular-text" /><br />
					<span class="description"><?php _e('Please enter your website URL.', 'wra'); ?></span>
				</td>
			</tr>
			<tr>
				<th><label for="sales_tax_license"><?php _e('Sales Tax License', 'wra'); ?></label></th>
				<td>
					<?php $sales_tax_license = get_user_meta($user->ID, 'sales_tax_license', true); ?>
					<?php if ($sales_tax_license): ?>
						<a href="<?php echo esc_url($sales_tax_license); ?>" target="_blank"><?php _e('View Sales Tax License', 'wra'); ?></a><br />
					<?php endif; ?>
					<input type="file" name="sales_tax_license" id="sales_tax_license" /><br />
					<span class="description"><?php _e('Please upload your sales tax license.', 'wra'); ?></span>
				</td>
			</tr>
			<tr>
				<th><label for="shipping_account"><?php _e('Shipping Account #', 'wra'); ?></label></th>
				<td>
					<input type="text" name="shipping_account" id="shipping_account" value="<?php echo esc_attr(get_user_meta($user->ID, 'shipping_account', true)); ?>" class="regular-text" /><br />
					<span class="description"><?php _e('Please enter your shipping account number.', 'wra'); ?></span>
				</td>
			</tr>
			<tr>
				<th><label for="shipping_address"><?php _e('Shipping Address', 'wra'); ?></label></th>
				<td>
					<input type="text" name="shipping_address_line1" id="shipping_address_line1" placeholder="Street Address Line 1" value="<?php echo esc_attr(get_user_meta($user->ID, 'shipping_address_line1', true)); ?>" class="regular-text" /><br />
					<input type="text" name="shipping_address_line2" id="shipping_address_line2" placeholder="Street Address Line 2" value="<?php echo esc_attr(get_user_meta($user->ID, 'shipping_address_line2', true)); ?>" class="regular-text" /><br />
					<input type="text" name="shipping_city" id="shipping_city" placeholder="City" value="<?php echo esc_attr(get_user_meta($user->ID, 'shipping_city', true)); ?>" class="regular-text" /><br />
					<input type="text" name="shipping_state" id="shipping_state" placeholder="State/Province" value="<?php echo esc_attr(get_user_meta($user->ID, 'shipping_state', true)); ?>" class="regular-text" /><br />
					<input type="text" name="shipping_postal_code" id="shipping_postal_code" placeholder="Postal/Zip code" value="<?php echo esc_attr(get_user_meta($user->ID, 'shipping_postal_code', true)); ?>" class="regular-text" /><br />
					<select name="shipping_country" id="shipping_country" class="regular-text">
						<?php foreach (WC()->countries->get_countries() as $country_code => $country_name) : ?>
							<option value="<?php echo esc_attr($country_code); ?>" <?php selected(get_user_meta($user->ID, 'shipping_country', true), $country_code); ?>><?php echo esc_html($country_name); ?></option>
						<?php endforeach; ?>
					</select>
				</td>
			</tr>
			<tr>
				<th><label for="billing_address"><?php _e('Billing Address', 'wra'); ?></label></th>
				<td>
					<input type="text" name="billing_address_line1" id="billing_address_line1" placeholder="Street Address Line 1" value="<?php echo esc_attr(get_user_meta($user->ID, 'billing_address_line1', true)); ?>" class="regular-text" /><br />
					<input type="text" name="billing_address_line2" id="billing_address_line2" placeholder="Street Address Line 2" value="<?php echo esc_attr(get_user_meta($user->ID, 'billing_address_line2', true)); ?>" class="regular-text" /><br />
					<input type="text" name="billing_city" id="billing_city" placeholder="City" value="<?php echo esc_attr(get_user_meta($user->ID, 'billing_city', true)); ?>" class="regular-text" /><br />
					<input type="text" name="billing_state" id="billing_state" placeholder="State/Province" value="<?php echo esc_attr(get_user_meta($user->ID, 'billing_state', true)); ?>" class="regular-text" /><br />
					<input type="text" name="billing_postal_code" id="billing_postal_code" placeholder="Postal/Zip code" value="<?php echo esc_attr(get_user_meta($user->ID, 'billing_postal_code', true)); ?>" class="regular-text" /><br />
					<select name="billing_country" id="billing_country" class="regular-text">
						<?php foreach (WC()->countries->get_countries() as $country_code => $country_name) : ?>
							<option value="<?php echo esc_attr($country_code); ?>" <?php selected(get_user_meta($user->ID, 'billing_country', true), $country_code); ?>><?php echo esc_html($country_name); ?></option>
						<?php endforeach; ?>
					</select>
				</td>
			</tr>

			<tr>
				<th><label for="physical_location"><?php _e('Physical Location', 'wra'); ?></label></th>
				<td>
					<input type="text" name="physical_location_line1" id="physical_location_line1" placeholder="Street Address Line 1" value="<?php echo esc_attr(get_user_meta($user->ID, 'physical_location_line1', true)); ?>" class="regular-text" /><br />
					<input type="text" name="physical_location_line2" id="physical_location_line2" placeholder="Street Address Line 2" value="<?php echo esc_attr(get_user_meta($user->ID, 'physical_location_line2', true)); ?>" class="regular-text" /><br />
					<input type="text" name="physical_city" id="physical_city" placeholder="City" value="<?php echo esc_attr(get_user_meta($user->ID, 'physical_city', true)); ?>" class="regular-text" /><br />
					<input type="text" name="physical_state" id="physical_state" placeholder="State/Province" value="<?php echo esc_attr(get_user_meta($user->ID, 'physical_state', true)); ?>" class="regular-text" /><br />
					<input type="text" name="physical_postal_code" id="physical_postal_code" placeholder="Postal/Zip code" value="<?php echo esc_attr(get_user_meta($user->ID, 'physical_postal_code', true)); ?>" class="regular-text" /><br />
					<select name="physical_country" id="physical_country" class="regular-text">
						<?php foreach (WC()->countries->get_countries() as $country_code => $country_name) : ?>
							<option value="<?php echo esc_attr($country_code); ?>" <?php selected(get_user_meta($user->ID, 'physical_country', true), $country_code); ?>><?php echo esc_html($country_name); ?></option>
						<?php endforeach; ?>
					</select>
				</td>
			</tr>
			<tr>
				<th><label for="phone_number"><?php _e('Phone Number', 'wra'); ?></label></th>
				<td>
					<input type="text" name="phone_number" id="phone_number" value="<?php echo esc_attr(get_user_meta($user->ID, 'phone_number', true)); ?>" class="regular-text" /><br />
					<span class="description"><?php _e('Please enter your phone number.', 'wra'); ?></span>
				</td>
			</tr>
			<tr>
				<th><label for="fit_partner"><?php _e('Fit Partner', 'wra'); ?></label></th>
				<td>
					<textarea name="fit_partner" id="fit_partner" class="regular-text"><?php echo esc_textarea(get_user_meta($user->ID, 'fit_partner', true)); ?></textarea><br />
					<span class="description"><?php _e('Please describe why your business is a good fit to partner with us.', 'wra'); ?></span>
				</td>
			</tr>
			<tr>
				<th><label for="customer_demographic"><?php _e('Customer Demographic', 'wra'); ?></label></th>
				<td>
					<textarea name="customer_demographic" id="customer_demographic" class="regular-text"><?php echo esc_textarea(get_user_meta($user->ID, 'customer_demographic', true)); ?></textarea><br />
					<span class="description"><?php _e('Please describe your main customer demographic.', 'wra'); ?></span>
				</td>
			</tr>
			<tr>
				<th><label for="align_goals"><?php _e('Align Goals', 'wra'); ?></label></th>
				<td>
					<textarea name="align_goals" id="align_goals" class="regular-text"><?php echo esc_textarea(get_user_meta($user->ID, 'align_goals', true)); ?></textarea><br />
					<span class="description"><?php _e('Please describe how your business goals align with our mission.', 'wra'); ?></span>
				</td>
			</tr>
			<tr>
				<th><label for="promote_products"><?php _e('Promote Products', 'wra'); ?></label></th>
				<td>
					<textarea name="promote_products" id="promote_products" class="regular-text"><?php echo esc_textarea(get_user_meta($user->ID, 'promote_products', true)); ?></textarea><br />
					<span class="description"><?php _e('Please describe how you plan to promote our products.', 'wra'); ?></span>
				</td>
			</tr>
			<tr>
				<th><label for="facebook_group"><?php _e('Facebook Group', 'wra'); ?></label></th>
				<td>
					<select name="facebook_group" id="facebook_group" class="regular-text">
						<option value="Yes" <?php selected(get_user_meta($user->ID, 'facebook_group', true), 'Yes'); ?>><?php _e('Yes', 'wra'); ?></option>
						<option value="No" <?php selected(get_user_meta($user->ID, 'facebook_group', true), 'No'); ?>><?php _e('No', 'wra'); ?></option>
					</select><br />
					<span class="description"><?php _e('Would you like to be an active member of our exclusive wholesale-only Facebook group?', 'wra'); ?></span>
				</td>
			</tr>
			<tr>
				<th><label for="interested_products"><?php _e('Interested Products', 'wra'); ?></label></th>
				<td>
					<textarea name="interested_products" id="interested_products" class="regular-text"><?php echo esc_textarea(get_user_meta($user->ID, 'interested_products', true)); ?></textarea><br />
					<span class="description"><?php _e('Please describe which products you are most interested in.', 'wra'); ?></span>
				</td>
			</tr>
			<tr>
				<th><label for="need_displays"><?php _e('Need Displays', 'wra'); ?></label></th>
				<td>
					<textarea name="need_displays" id="need_displays" class="regular-text"><?php echo esc_textarea(get_user_meta($user->ID, 'need_displays', true)); ?></textarea><br />
					<span class="description"><?php _e('Will you need displays to merchandise products?', 'wra'); ?></span>
				</td>
			</tr>
			<tr>
				<th><label for="store_photo"><?php _e('Store Photo', 'wra'); ?></label></th>
				<td>
					<?php $store_photo = get_user_meta($user->ID, 'store_photo', true); ?>
					<?php if ($store_photo): ?>
						<a href="<?php echo esc_url($store_photo); ?>" target="_blank"><?php _e('View Store Photo', 'wra'); ?></a><br />
					<?php endif; ?>
					<input type="file" name="store_photo" id="store_photo" /><br />
					<span class="description"><?php _e('Please upload a photo of your store.', 'wra'); ?></span>
				</td>
			</tr>
			<tr>
				<th><label for="sales_volume"><?php _e('Sales Volume', 'wra'); ?></label></th>
				<td>
					<textarea name="sales_volume" id="sales_volume" class="regular-text"><?php echo esc_textarea(get_user_meta($user->ID, 'sales_volume', true)); ?></textarea><br />
					<span class="description"><?php _e('Please describe your anticipated monthly sales volume.', 'wra'); ?></span>
				</td>
			</tr>
			<tr>
				<th><label for="first_order"><?php _e('First Order', 'wra'); ?></label></th>
				<td>
					<textarea name="first_order" id="first_order" class="regular-text"><?php echo esc_textarea(get_user_meta($user->ID, 'first_order', true)); ?></textarea><br />
					<span class="description"><?php _e('When do you hope to place your first wholesale order?', 'wra'); ?></span>
				</td>
			</tr>
			<tr>
				<th><label for="intro_session"><?php _e('Intro Session', 'wra'); ?></label></th>
				<td>
					<textarea name="intro_session" id="intro_session" class="regular-text"><?php echo esc_textarea(get_user_meta($user->ID, 'intro_session', true)); ?></textarea><br />
					<span class="description"><?php _e('Book a FREE 30-minute wholesale introduction session.', 'wra'); ?></span>
				</td>
			</tr>
			<tr>
				<th><label for="initial_here"><?php _e('Initial Here', 'wra'); ?></label></th>
				<td>
					<textarea name="initial_here" id="initial_here" class="regular-text"><?php echo esc_textarea(get_user_meta($user->ID, 'initial_here', true)); ?></textarea><br />
					<span class="description"><?php _e('Initial here to confirm you understand our policies.', 'wra'); ?></span>
				</td>
			</tr>
		</table>
		<?php
	}

	public function save_custom_user_fields($user_id) {
		if (!current_user_can('edit_user', $user_id)) {
			return false;
		}
		
		if (isset($_POST['approval_status'])) {
			$valid_statuses = array('pending', 'approved', 'rejected');
			$approval_status = sanitize_text_field($_POST['approval_status']);
			
			if (in_array($approval_status, $valid_statuses)) {
				update_user_meta($user_id, 'approval_status', $approval_status);
	
				if ($approval_status === 'approved' || $approval_status === 'rejected') {
					$user_info = get_userdata($user_id);
					$to = $user_info->user_email;
				
					if ($approval_status === 'approved') {
						$reset_key = get_password_reset_key($user_info);
						if (!is_wp_error($reset_key)) {
							$reset_password_url = network_site_url("wp-login.php?action=rp&key=$reset_key&login=" . rawurlencode($user_info->user_login), 'login');
							
							wra_send_email($to, [
								'data' => '<p style="margin:0 0 16px">Congratulations! Your account has been approved. You can now enjoy the services offered by our site.</p>
										   <p style="margin:0 0 16px">To set your password, please visit the following link: <a href="' . $reset_password_url . '">Reset Password</a></p>
										   <p style="margin:0 0 16px">We look forward to seeing you soon.</p>'
							], 'Your Account Has Been Approved');
						}
					} elseif ($approval_status === 'rejected') {
						wra_send_email($to, [
							'data' => '<p style="margin:0 0 16px">We regret to inform you that your account has been rejected. If you have any questions, please contact our support team.</p>
									   <p style="margin:0 0 16px">Thank you for your understanding.</p>'
						], 'Your Account Has Been Rejected');
					}
				}
			}
		}
		
		if (isset($_POST['business_name'])) {
			update_user_meta($user_id, 'business_name', sanitize_text_field($_POST['business_name']));
		}	
		
		if (isset($_POST['business_type'])) {
			update_user_meta($user_id, 'business_type', array_map('sanitize_text_field', $_POST['business_type']));
		}	
		
		if (isset($_POST['website_url'])) {
			update_user_meta($user_id, 'website_url', esc_url_raw($_POST['website_url']));
		}	
		
		if (!empty($_FILES['sales_tax_license']['name'])) {
			$uploaded_file = wp_handle_upload($_FILES['sales_tax_license'], array('test_form' => false));
			if (isset($uploaded_file['url'])) {
				update_user_meta($user_id, 'sales_tax_license', esc_url($uploaded_file['url']));
			}
		}	
		
		if (isset($_POST['shipping_account'])) {
			update_user_meta($user_id, 'shipping_account', sanitize_text_field($_POST['shipping_account']));
		}	
		
		if (isset($_POST['shipping_address_line1'])) {
			update_user_meta($user_id, 'shipping_address_line1', sanitize_text_field($_POST['shipping_address_line1']));
		}
		if (isset($_POST['shipping_address_line2'])) {
			update_user_meta($user_id, 'shipping_address_line2', sanitize_text_field($_POST['shipping_address_line2']));
		}
		if (isset($_POST['shipping_city'])) {
			update_user_meta($user_id, 'shipping_city', sanitize_text_field($_POST['shipping_city']));
		}
		if (isset($_POST['shipping_state'])) {
			update_user_meta($user_id, 'shipping_state', sanitize_text_field($_POST['shipping_state']));
		}
		if (isset($_POST['shipping_postal_code'])) {
			update_user_meta($user_id, 'shipping_postal_code', sanitize_text_field($_POST['shipping_postal_code']));
		}
		if (isset($_POST['shipping_country'])) {
			update_user_meta($user_id, 'shipping_country', sanitize_text_field($_POST['shipping_country']));
		}
		if (isset($_POST['physical_location_line1'])) {
			update_user_meta($user_id, 'physical_location_line1', sanitize_text_field($_POST['physical_location_line1']));
		}
		if (isset($_POST['physical_location_line2'])) {
			update_user_meta($user_id, 'physical_location_line2', sanitize_text_field($_POST['physical_location_line2']));
		}
		if (isset($_POST['physical_city'])) {
			update_user_meta($user_id, 'physical_city', sanitize_text_field($_POST['physical_city']));
		}
		if (isset($_POST['physical_state'])) {
			update_user_meta($user_id, 'physical_state', sanitize_text_field($_POST['physical_state']));
		}
		if (isset($_POST['physical_postal_code'])) {
			update_user_meta($user_id, 'physical_postal_code', sanitize_text_field($_POST['physical_postal_code']));
		}
		if (isset($_POST['physical_country'])) {
			update_user_meta($user_id, 'physical_country', sanitize_text_field($_POST['physical_country']));
		}
		if (isset($_POST['phone_number'])) {
			update_user_meta($user_id, 'phone_number', sanitize_text_field($_POST['phone_number']));
		}
		if (isset($_POST['fit_partner'])) {
			update_user_meta($user_id, 'fit_partner', sanitize_textarea_field($_POST['fit_partner']));
		}
		if (isset($_POST['customer_demographic'])) {
			update_user_meta($user_id, 'customer_demographic', sanitize_textarea_field($_POST['customer_demographic']));
		}
		if (isset($_POST['align_goals'])) {
			update_user_meta($user_id, 'align_goals', sanitize_textarea_field($_POST['align_goals']));
		}
		if (isset($_POST['promote_products'])) {
			update_user_meta($user_id, 'promote_products', sanitize_textarea_field($_POST['promote_products']));
		}
		if (isset($_POST['facebook_group'])) {
			update_user_meta($user_id, 'facebook_group', sanitize_text_field($_POST['facebook_group']));
		}
		if (isset($_POST['interested_products'])) {
			update_user_meta($user_id, 'interested_products', sanitize_textarea_field($_POST['interested_products']));
		}
		if (isset($_POST['need_displays'])) {
			update_user_meta($user_id, 'need_displays', sanitize_textarea_field($_POST['need_displays']));
		}
		if (isset($_POST['sales_volume'])) {
			update_user_meta($user_id, 'sales_volume', sanitize_textarea_field($_POST['sales_volume']));
		}
		if (isset($_POST['first_order'])) {
			update_user_meta($user_id, 'first_order', sanitize_textarea_field($_POST['first_order']));
		}
		if (isset($_POST['intro_session'])) {
			update_user_meta($user_id, 'intro_session', sanitize_textarea_field($_POST['intro_session']));
		}
		if (isset($_POST['initial_here'])) {
			update_user_meta($user_id, 'initial_here', sanitize_textarea_field($_POST['initial_here']));
		}
	}

	public function add_approval_status_column($columns) {
		$columns['approval_status'] = __('Approval Status', 'wra');
		return $columns;
	}
	
	public function show_approval_status_column($value, $column_name, $user_id) {
		if ($column_name == 'approval_status') {
			$status = get_user_meta($user_id, 'approval_status', true);
			switch ($status) {
				case 'approved':
					return __('Approved', 'wra');
				case 'rejected':
					return __('Rejected', 'wra');
				case 'pending':
					return __('Pending', 'wra');
				default:
					return __('Not Set', 'wra');
			}
		}
		return $value;
	}

	public function add_approval_status_filter($which) {
		$status = isset($_GET['approval_status']) ? $_GET['approval_status'] : '';
		?>
		<select name="approval_status" id="approval_status">
			<option value=""><?php _e('All Approval Statuses', 'wra'); ?></option>
			<option value="pending" <?php selected($status, 'pending'); ?>><?php _e('Pending', 'wra'); ?></option>
			<option value="approved" <?php selected($status, 'approved'); ?>><?php _e('Approved', 'wra'); ?></option>
			<option value="rejected" <?php selected($status, 'rejected'); ?>><?php _e('Rejected', 'wra'); ?></option>
		</select>
		<input type="submit" name="filter_action" id="post-query-submit" class="button" value="<?php _e('Filter', 'wra'); ?>">
		<?php
	}	

	public function add_approval_status_views($views) {
		$current = isset($_GET['approval_status']) ? $_GET['approval_status'] : '';
	
		$statuses = array(
			'all' => __('All', 'wra'),
			'pending' => __('Pending', 'wra'),
			'approved' => __('Approved', 'wra'),
			'rejected' => __('Rejected', 'wra')
		);
	
		foreach ($statuses as $status => $label) {
			$class = ($current === $status) ? 'current' : '';
			$url = add_query_arg('approval_status', $status);
			$count = $this->get_users_count_by_approval_status($status);
			$views[$status] = sprintf(
				'<li class="%s"><a href="%s">%s <span class="count">(%d)</span></a></li>',
				esc_attr($status),
				esc_url($url),
				esc_html($label),
				intval($count)
			);
		}
	
		return $views;
	}
	
	private function get_users_count_by_approval_status($status) {
		global $wpdb;
	
		if ($status === 'all') {
			$query = "SELECT COUNT(*) FROM {$wpdb->usermeta} WHERE meta_key = 'approval_status'";
		} else {
			$query = $wpdb->prepare(
				"SELECT COUNT(*) FROM {$wpdb->usermeta} WHERE meta_key = 'approval_status' AND meta_value = %s",
				$status
			);
		}
	
		return $wpdb->get_var($query);
	}

	public function filter_users_by_approval_status($query) {
		global $pagenow;
	
		if (is_admin() && $pagenow == 'users.php' && isset($_GET['approval_status']) && $_GET['approval_status'] != 'all') {
			$meta_query = array(
				array(
					'key' => 'approval_status',
					'value' => sanitize_text_field($_GET['approval_status']),
					'compare' => '='
				)
			);
			$query->set('meta_query', $meta_query);
		}
	}
}
