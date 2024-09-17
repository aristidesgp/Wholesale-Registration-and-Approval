<?php
/*
*
* @package aristidesgp
*
*/

namespace WRA\Inc\Base;

class Registration
{

    public function register()
    {
        add_action('init', array($this, 'register_shortcodes'));
        add_action('woocommerce_created_customer', array($this, 'save_custom_registration_fields'));
        add_action('woocommerce_created_customer', array($this, 'set_user_pending_approval'));
        add_action('woocommerce_created_customer', array($this, 'save_customer_addresses'));
        add_action('init', array($this, 'handle_user_registration'));
    }

    public function register_shortcodes()
    {
        add_shortcode('wholesale_registration_form', array($this, 'render_registration_form'));
    }

    public function render_registration_form()
    {
        ob_start();
?>
        <form method="post" enctype="multipart/form-data" class="woocommerce-form woocommerce-form-register register form-container">
            <?php do_action('woocommerce_register_form_start'); ?>
            <div class="d-flex gap-2">
                <p class="woocommerce-form-row woocommerce-form-row--wide form-row form-row-wide">
                    <label for="reg_first_name"><?php _e('First Name', 'woocommerce'); ?> <span class="required">*</span></label>
                    <input type="text" class="input-text" required name="first_name" id="reg_first_name" value="<?php if (!empty($_POST['first_name'])) echo esc_attr($_POST['first_name']); ?>" />
                </p>
                <p class="woocommerce-form-row woocommerce-form-row--wide form-row form-row-wide">
                    <label for="reg_last_name"><?php _e('Last Name', 'woocommerce'); ?> <span class="required">*</span></label>
                    <input type="text" class="input-text" required name="last_name" id="reg_last_name" value="<?php if (!empty($_POST['last_name'])) echo esc_attr($_POST['last_name']); ?>" />
                </p>
            </div>

            <div class="d-flex gap-2">
                <p class="woocommerce-form-row woocommerce-form-row--wide form-row form-row-wide">
                    <label for="reg_email"><?php _e('Email address', 'woocommerce'); ?> <span class="required">*</span></label>
                    <input type="email" required class="woocommerce-Input woocommerce-Input--text input-text" name="email" id="reg_email" value="<?php if (!empty($_POST['email'])) echo esc_attr($_POST['email']); ?>" />
                </p>

                <p class="woocommerce-form-row woocommerce-form-row--wide form-row form-row-wide">
                    <label for="reg_business_name"><?php _e('Business Name', 'woocommerce'); ?> <span class="required">*</span></label>
                    <input type="text" required class="input-text" name="business_name" id="reg_business_name" value="<?php if (!empty($_POST['business_name'])) echo esc_attr($_POST['business_name']); ?>" />
                </p>
            </div>
            
            <p class="form-row form-row-wide">
                <label for="reg_phone_number"><?php _e('Phone Number', 'woocommerce'); ?> <span class="required">*</span></label>
                <input type="tel" required class="input-text phone-input" name="phone_number" id="reg_phone_number" value="<?php if (!empty($_POST['phone_number'])) echo esc_attr($_POST['phone_number']); ?>" />
            </p>

            <p class="woocommerce-form-row woocommerce-form-row--wide form-row form-row-wide">
                <label for="reg_business_type"><?php _e('What Type of Business Do You Have? Check all that apply.', 'woocommerce'); ?> <span class="required">*</span></label>
                <input type="checkbox" name="business_type[]" value="Brick & Mortar"> Brick & Mortar<br>
                <input type="checkbox" name="business_type[]" value="E-Commerce"> E-Commerce<br>
                <input type="checkbox" name="business_type[]" value="Facebook Group"> Facebook Group<br>
                <input type="checkbox" name="business_type[]" value="Live Seller/Comment Sold"> Live Seller/Comment Sold<br>
                <input type="checkbox" name="business_type[]" value="3rd Party Reseller Platforms (ie Amazon, Ebay, Poshmark etc)"> 3rd Party Reseller Platforms (ie Amazon, Ebay, Poshmark etc)<br>
            </p>

            <p class="form-row form-row-wide">
                <label for="reg_sales_tax_license"><?php _e('Upload a Copy of Your Current Sales Tax License', 'woocommerce'); ?> <span class="required">*</span></label>
                <input type="file" class="input-text" name="sales_tax_license" id="reg_sales_tax_license" />
            </p>

            <div class="d-flex gap-2">
                <p class="form-row form-row-wide">
                    <label for="reg_website_url"><?php _e('Website URL', 'woocommerce'); ?> <span class="required">*</span></label>
                    <input type="url" class="input-text" required name="website_url" id="reg_website_url" value="<?php if (!empty($_POST['website_url'])) echo esc_attr($_POST['website_url']); ?>" />
                </p>

                <p class="form-row form-row-wide">
                    <label for="reg_shipping_account"><?php _e('Shipping Account #', 'woocommerce'); ?></label>
                    <input type="text" class="input-text" name="shipping_account" id="reg_shipping_account" value="<?php if (!empty($_POST['shipping_account'])) echo esc_attr($_POST['shipping_account']); ?>" />
                </p>
            </div>

            <p class="form-row form-row-wide">
                <label for="reg_shipping_address"><?php _e('Shipping Address', 'woocommerce'); ?> <span class="required">*</span></label>
            <div class="d-flex gap-2 mb-2">
                <input type="text" class="input-text" required name="shipping_address_line1" id="reg_shipping_address_line1" placeholder="Street Address Line 1" value="<?php if (!empty($_POST['shipping_address_line1'])) echo esc_attr($_POST['shipping_address_line1']); ?>" /><br>
                <input type="text" class="input-text" name="shipping_address_line2" id="reg_shipping_address_line2" placeholder="Street Address Line 2" value="<?php if (!empty($_POST['shipping_address_line2'])) echo esc_attr($_POST['shipping_address_line2']); ?>" /><br>
            </div>
            <div class="d-flex gap-2 mb-2">
                <input type="text" class="input-text" required name="shipping_city" id="reg_shipping_city" placeholder="City" value="<?php if (!empty($_POST['shipping_city'])) echo esc_attr($_POST['shipping_city']); ?>" /><br>
                <input type="text" class="input-text" required name="shipping_state" id="reg_shipping_state" placeholder="State/Province" value="<?php if (!empty($_POST['shipping_state'])) echo esc_attr($_POST['shipping_state']); ?>" /><br>
            </div>
            <div class="d-flex gap-2 ">
                <input type="text" class="input-text" required name="shipping_postal_code" id="reg_shipping_postal_code" placeholder="Postal/Zip code" value="<?php if (!empty($_POST['shipping_postal_code'])) echo esc_attr($_POST['shipping_postal_code']); ?>" /><br>
                <select name="shipping_country" id="reg_shipping_country">
                    <?php foreach (WC()->countries->get_countries() as $country_code => $country_name) : ?>
                        <option value="<?php echo esc_attr($country_code); ?>"><?php echo esc_html($country_name); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            </p>
            <p class="form-row form-row-wide">
                <label for="reg_billing_address"><?php _e('Billing Address (If different than shipping address)', 'woocommerce'); ?></label>
                <div class="d-flex gap-2 mb-2">
                <input type="text" class="input-text" name="billing_address_line1" id="reg_billing_address_line1" placeholder="Street Address Line 1" value="<?php if (!empty($_POST['billing_address_line1'])) echo esc_attr($_POST['billing_address_line1']); ?>" /><br>
                <input type="text" class="input-text" name="billing_address_line2" id="reg_billing_address_line2" placeholder="Street Address Line 2" value="<?php if (!empty($_POST['billing_address_line2'])) echo esc_attr($_POST['billing_address_line2']); ?>" /><br>
                </div>
                <div class="d-flex gap-2 mb-2">
                <input type="text" class="input-text" name="billing_city" id="reg_billing_city" placeholder="City" value="<?php if (!empty($_POST['billing_city'])) echo esc_attr($_POST['billing_city']); ?>" /><br>
                <input type="text" class="input-text" name="billing_state" id="reg_billing_state" placeholder="State/Province" value="<?php if (!empty($_POST['billing_state'])) echo esc_attr($_POST['billing_state']); ?>" /><br>
                </div>
                <div class="d-flex gap-2">
                <input type="text" class="input-text" name="billing_postal_code" id="reg_billing_postal_code" placeholder="Postal/Zip code" value="<?php if (!empty($_POST['billing_postal_code'])) echo esc_attr($_POST['billing_postal_code']); ?>" /><br>
                <select name="billing_country" id="reg_billing_country">
                    <?php foreach (WC()->countries->get_countries() as $country_code => $country_name) : ?>
                        <option value="<?php echo esc_attr($country_code); ?>"><?php echo esc_html($country_name); ?></option>
                    <?php endforeach; ?>
                </select>
                </div>
            </p>
            <p class="form-row form-row-wide">
                <label for="reg_physical_location"><?php _e('Physical Location Where You Sell Products (If different than shipping or billing)', 'woocommerce'); ?></label>
                <div class="d-flex gap-2 mb-2">
                <input type="text" class="input-text" name="physical_location_line1" id="reg_physical_location_line1" placeholder="Street Address Line 1" value="<?php if (!empty($_POST['physical_location_line1'])) echo esc_attr($_POST['physical_location_line1']); ?>" /><br>
                <input type="text" class="input-text" name="physical_location_line2" id="reg_physical_location_line2" placeholder="Street Address Line 2" value="<?php if (!empty($_POST['physical_location_line2'])) echo esc_attr($_POST['physical_location_line2']); ?>" /><br>
                </div>
                <div class="d-flex gap-2 mb-2">
                <input type="text" class="input-text" name="physical_city" id="reg_physical_city" placeholder="City" value="<?php if (!empty($_POST['physical_city'])) echo esc_attr($_POST['physical_city']); ?>" /><br>
                <input type="text" class="input-text" name="physical_state" id="reg_physical_state" placeholder="State/Province" value="<?php if (!empty($_POST['physical_state'])) echo esc_attr($_POST['physical_state']); ?>" /><br>
                </div>
                <div class="d-flex gap-2">
                <input type="text" class="input-text" name="physical_postal_code" id="reg_physical_postal_code" placeholder="Postal/Zip code" value="<?php if (!empty($_POST['physical_postal_code'])) echo esc_attr($_POST['physical_postal_code']); ?>" /><br>
                <select name="physical_country" id="reg_physical_country">
                    <?php foreach (WC()->countries->get_countries() as $country_code => $country_name) : ?>
                        <option value="<?php echo esc_attr($country_code); ?>"><?php echo esc_html($country_name); ?></option>
                    <?php endforeach; ?>
                </select>
                </div>
            </p>
            
            <p class="form-row form-row-wide">
                <label for="reg_fit_partner"><?php _e('What makes your business a good fit to partner with BEE-OCH Organics?', 'woocommerce'); ?> <span class="required">*</span></label>
                <textarea name="fit_partner" required id="reg_fit_partner" class="input-text"><?php if (!empty($_POST['fit_partner'])) echo esc_attr($_POST['fit_partner']); ?></textarea>
            </p>
            <p class="form-row form-row-wide">
                <label for="reg_customer_demographic"><?php _e('What is your main customer demographic/interests?', 'woocommerce'); ?> <span class="required">*</span></label>
                <textarea name="customer_demographic" required id="reg_customer_demographic" class="input-text"><?php if (!empty($_POST['customer_demographic'])) echo esc_attr($_POST['customer_demographic']); ?></textarea>
            </p>
            <p class="form-row form-row-wide">
                <label for="reg_align_goals"><?php _e('How do your business goals align with the BEE-OCH clean-product mission and organic values?', 'woocommerce'); ?> <span class="required">*</span></label>
                <textarea name="align_goals" required id="reg_align_goals" class="input-text"><?php if (!empty($_POST['align_goals'])) echo esc_attr($_POST['align_goals']); ?></textarea>
            </p>
            <p class="form-row form-row-wide">
                <label for="reg_promote_products"><?php _e('How do you plan to promote BEE-OCH Organic Products if approved?', 'woocommerce'); ?> <span class="required">*</span></label>
                <textarea name="promote_products" required id="reg_promote_products" class="input-text"><?php if (!empty($_POST['promote_products'])) echo esc_attr($_POST['promote_products']); ?></textarea>
            </p>
            <p class="form-row form-row-wide">
                <label for="reg_facebook_group"><?php _e('Would you like to bee an active member of our exclusive wholesale-only facebook group?', 'woocommerce'); ?> <span class="required">*</span></label>
                <select name="facebook_group" id="reg_facebook_group">
                    <option value="Yes">Yes</option>
                    <option value="No">No</option>
                </select>
            </p>
            <p class="form-row form-row-wide">
                <label for="reg_interested_products"><?php _e('Which products are you most interested in?', 'woocommerce'); ?> <span class="required">*</span></label>
                <textarea name="interested_products" required id="reg_interested_products" class="input-text"><?php if (!empty($_POST['interested_products'])) echo esc_attr($_POST['interested_products']); ?></textarea>
            </p>
            <p class="form-row form-row-wide">
                <label for="reg_need_displays"><?php _e('Will you need displays to merchandise products?', 'woocommerce'); ?> <span class="required">*</span></label>
                <textarea name="need_displays" required id="reg_need_displays" class="input-text"><?php if (!empty($_POST['need_displays'])) echo esc_attr($_POST['need_displays']); ?></textarea>
            </p>
            <p class="form-row form-row-wide">
                <label for="reg_store_photo"><?php _e('Please Upload a Photo of Your Brick & Mortar and Shelf Location Where You Intend to Display BEE-OCH Products. (Used for promotional purposes once approved as a wholesale partner)', 'woocommerce'); ?> <span class="required">*</span></label>
                <input type="file" required class="input-text" name="store_photo" id="reg_store_photo" />
            </p>
            <p class="form-row form-row-wide">
                <label for="reg_sales_volume"><?php _e('What is your anticipated monthly sales volume & order frequency?', 'woocommerce'); ?> <span class="required">*</span></label>
                <textarea name="sales_volume" required id="reg_sales_volume" class="input-text"><?php if (!empty($_POST['sales_volume'])) echo esc_attr($_POST['sales_volume']); ?></textarea>
            </p>
            <p class="form-row form-row-wide">
                <label for="reg_first_order"><?php _e('When do you hope to place your first wholesale order?', 'woocommerce'); ?> <span class="required">*</span></label>
                <textarea name="first_order" required id="reg_first_order" class="input-text"><?php if (!empty($_POST['first_order'])) echo esc_attr($_POST['first_order']); ?></textarea>
            </p>
            <p class="form-row form-row-wide">
                <label for="reg_intro_session"><?php _e('Book a FREE 30-minute wholesale introduction session to learn more about BEE-OCH Organics and our products and get to know each other! (Required to be accepted as a wholesale partner): https://calendly.com/bee-och/30min', 'woocommerce'); ?> <span class="required">*</span></label>
                <textarea name="intro_session" required id="reg_intro_session" class="input-text"><?php if (!empty($_POST['intro_session'])) echo esc_attr($_POST['intro_session']); ?></textarea>
            </p>
            <p class="form-row form-row-wide">
                <label for="reg_initial_here"><?php _e('Initial Here that You Understand that we do not allow our products to be listed on personal or business E-commerce websites and that doing so will terminate your wholesale status with us. (Comment Sold Apps OK)', 'woocommerce'); ?> <span class="required">*</span></label>
                <textarea name="initial_here" required id="reg_initial_here" class="input-text"><?php if (!empty($_POST['initial_here'])) echo esc_attr($_POST['initial_here']); ?></textarea>
            </p>
            <?php do_action('woocommerce_register_form'); ?>
            <p class="woocommerce-form-row form-row">
                <?php wp_nonce_field('woocommerce-register', 'woocommerce-register-nonce'); ?>
                <button type="submit" class="woocommerce-Button woocommerce-button button woocommerce-form-register__submit" name="register" value="<?php esc_attr_e('Register', 'woocommerce'); ?>"><?php esc_html_e('Register', 'woocommerce'); ?></button>
            </p>
            <?php do_action('woocommerce_register_form_end'); ?>
        </form>
<?php
        return ob_get_clean();
    }

    public function save_custom_registration_fields($customer_id)
    {
        // Guardar el correo electrónico en el campo estándar de WordPress
        if (isset($_POST['email'])) {
            wp_update_user(array(
                'ID' => $customer_id,
                'user_email' => sanitize_email($_POST['email'])
            ));
        }

        // Guardar nombre y apellido en los campos estándar de WordPress
        if (isset($_POST['first_name'])) {
            update_user_meta($customer_id, 'first_name', sanitize_text_field($_POST['first_name']));
        }
        if (isset($_POST['last_name'])) {
            update_user_meta($customer_id, 'last_name', sanitize_text_field($_POST['last_name']));
        }

        // Guardar los demás campos personalizados como meta de usuario
        $fields = [
            'business_name',
            'website_url',
            'shipping_account',
            'shipping_address_line1',
            'shipping_address_line2',
            'shipping_city',
            'shipping_state',
            'shipping_postal_code',
            'shipping_country',
            'billing_address_line1',
            'billing_address_line2',
            'billing_city',
            'billing_state',
            'billing_postal_code',
            'billing_country',
            'physical_location_line1',
            'physical_location_line2',
            'physical_city',
            'physical_state',
            'physical_postal_code',
            'physical_country',
            'phone_number',
            'fit_partner',
            'customer_demographic',
            'align_goals',
            'promote_products',
            'facebook_group',
            'interested_products',
            'need_displays',
            'sales_volume',
            'first_order',
            'intro_session',
            'initial_here'
        ];

        foreach ($fields as $field) {
            if (isset($_POST[$field])) {
                update_user_meta($customer_id, $field, sanitize_text_field($_POST[$field]));
            }
        }

        // Campos de casillas de verificación (checkbox)
        if (isset($_POST['business_type'])) {
            update_user_meta($customer_id, 'business_type', array_map('sanitize_text_field', $_POST['business_type']));
        }

        // Manejo de archivos subidos
        if (!empty($_FILES['sales_tax_license']['name'])) {
            $uploaded_file = wp_handle_upload($_FILES['sales_tax_license'], array('test_form' => false));
            if (isset($uploaded_file['url'])) {
                update_user_meta($customer_id, 'sales_tax_license', esc_url($uploaded_file['url']));
            }
        }

        if (!empty($_FILES['store_photo']['name'])) {
            $uploaded_file = wp_handle_upload($_FILES['store_photo'], array('test_form' => false));
            if (isset($uploaded_file['url'])) {
                update_user_meta($customer_id, 'store_photo', esc_url($uploaded_file['url']));
            }
        }
    }

    public function set_user_pending_approval($customer_id)
    {
        update_user_meta($customer_id, 'approval_status', 'pending');
    }

    public function save_customer_addresses($user_id)
    {
        // Dirección de envío
        $shipping_address = array(
            'first_name' => sanitize_text_field($_POST['first_name']),
            'last_name' => sanitize_text_field($_POST['last_name']),
            'company' => sanitize_text_field($_POST['business_name']),
            'address_1' => sanitize_text_field($_POST['shipping_address_line1']),
            'address_2' => sanitize_text_field($_POST['shipping_address_line2']),
            'city' => sanitize_text_field($_POST['shipping_city']),
            'state' => sanitize_text_field($_POST['shipping_state']),
            'postcode' => sanitize_text_field($_POST['shipping_postal_code']),
            'country' => sanitize_text_field($_POST['shipping_country']),
            'phone' => sanitize_text_field($_POST['phone_number']),
        );

        foreach ($shipping_address as $key => $value) {
            update_user_meta($user_id, 'shipping_' . $key, $value);
        }

        // Dirección de facturación
        $billing_address = array(
            'first_name' => sanitize_text_field($_POST['first_name']),
            'last_name' => sanitize_text_field($_POST['last_name']),
            'company' => sanitize_text_field($_POST['business_name']),
            'address_1' => !empty($_POST['billing_address_line1']) ? sanitize_text_field($_POST['billing_address_line1']) : sanitize_text_field($_POST['shipping_address_line1']),
            'address_2' => !empty($_POST['billing_address_line2']) ? sanitize_text_field($_POST['billing_address_line2']) : sanitize_text_field($_POST['shipping_address_line2']),
            'city' => !empty($_POST['billing_city']) ? sanitize_text_field($_POST['billing_city']) : sanitize_text_field($_POST['shipping_city']),
            'state' => !empty($_POST['billing_state']) ? sanitize_text_field($_POST['billing_state']) : sanitize_text_field($_POST['shipping_state']),
            'postcode' => !empty($_POST['billing_postal_code']) ? sanitize_text_field($_POST['billing_postal_code']) : sanitize_text_field($_POST['shipping_postal_code']),
            'country' => !empty($_POST['billing_country']) ? sanitize_text_field($_POST['billing_country']) : sanitize_text_field($_POST['shipping_country']),
            'phone' => sanitize_text_field($_POST['phone_number']),
        );

        foreach ($billing_address as $key => $value) {
            update_user_meta($user_id, 'billing_' . $key, $value);
        }
    }

    public function handle_user_registration()
    {
        if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['register'])) {
            $email = sanitize_email($_POST['email']);
            $first_name = sanitize_text_field($_POST['first_name']);
            $last_name = sanitize_text_field($_POST['last_name']);
            $password = wp_generate_password();

            $userdata = array(
                'user_login' => $email,
                'user_email' => $email,
                'first_name' => $first_name,
                'last_name' => $last_name,
                'user_pass' => $password,
                'role' => 'customer'
            );

            $user_id = wp_insert_user($userdata);

            if (!is_wp_error($user_id)) {
                // Guardar los campos personalizados
                $this->save_custom_registration_fields($user_id);

                $this->save_customer_addresses($user_id);

                $this->set_user_pending_approval($user_id);

                // Enviar correo de bienvenida
                //wp_new_user_notification($user_id, null, 'both');
                //$this->send_pending_approval_email($user_id);
                wra_send_email($email, [
                    'data' => '<p style="margin:0 0 16px">Thanks for creating
                                                                            an account on Bee-Och Organics. </p>
                                                                        <p style="margin:0 0 16px">Your account is currently pending approval. You will receive another email once your account has been approved.
                                                                        </p>
                                                                        <p style="margin:0 0 16px">We look forward to
                                                                            seeing you soon.</p>'
                ], 'Your Account is Pending Approval');

                // Redirigir al usuario a la página de agradecimiento o inicio de sesión
                wp_redirect(home_url('/thank-you'));
                exit;
            } else {
                // Manejar errores
                $error_message = $user_id->get_error_message();
                echo '<div class="woocommerce-error">' . $error_message . '</div>';
            }
        }
    }
}
