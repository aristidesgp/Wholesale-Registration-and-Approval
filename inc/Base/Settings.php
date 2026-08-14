<?php

/*
*
* @package aristidesgp
*
*/

namespace CSHR\Inc\Base;

use CSHR\Inc\Util\Helper;
use CSHR\Inc\Base\Logs;

class Settings
{
    private $table_name;

    public function register()
    {
        global $wpdb;
        $this->table_name = $wpdb->prefix . 'cuba_shipping_rates';
        add_action('admin_menu', [$this, 'add_admin_page']);
        add_action('admin_init', [$this, 'create_table']);
        add_action('woocommerce_before_checkout_billing_form', [$this,'agregar_mensaje_arriba_billing_details']);
        add_action('woocommerce_after_checkout_billing_form', [$this, 'agregar_mensaje_arriba_shipping_form']);
        add_action('woocommerce_product_options_shipping', [$this,'add_custom_shipping_fields']);
        add_action('woocommerce_process_product_meta', [$this,'save_custom_shipping_fields']);
    }

    function add_custom_shipping_fields() {
        woocommerce_wp_text_input([
            'id' => 'cuba_shipping_rate',
            'label' => __('Rate de Envío a Cuba', 'textdomain'),
            'desc_tip' => 'true',
            'description' => __('Ingrese la tarifa de envío a Cuba para este producto.', 'textdomain'),
            'type' => 'number',
            'custom_attributes' => [
                'step' => 'any',
                'min' => '0'
            ]
        ]);
    
        woocommerce_wp_checkbox([
            'id' => 'cuba_shipping_by_weight',
            'label' => __('Precio por Peso', 'textdomain'),
            'description' => __('Marque esta casilla si el precio es por peso.', 'textdomain')
        ]);
    }

    function save_custom_shipping_fields($post_id) {
        $cuba_shipping_rate = isset($_POST['cuba_shipping_rate']) ? floatval($_POST['cuba_shipping_rate']) : '';
        update_post_meta($post_id, 'cuba_shipping_rate', $cuba_shipping_rate);
    
        $cuba_shipping_by_weight = isset($_POST['cuba_shipping_by_weight']) ? 'yes' : 'no';
        update_post_meta($post_id, 'cuba_shipping_by_weight', $cuba_shipping_by_weight);
    }

    public function add_admin_page() {
        add_menu_page(
            'Tarifas de Envío - Cuba',
            'Tarifas de Envío',
            'manage_options',
            'cuba-shipping-rates',
            [$this, 'render_admin_page'],
            'dashicons-admin-generic',
            56
        );
    }

    public function render_admin_page() {
        $active_tab = isset($_GET['tab']) ? $_GET['tab'] : 'general';
        ?>
        <div class="wrap">
            <h1>Tarifas de Envío para Cuba</h1>
            <h2 class="nav-tab-wrapper">
                <a href="?page=cuba-shipping-rates&tab=general" class="nav-tab <?php echo $active_tab == 'general' ? 'nav-tab-active' : ''; ?>">General</a>
                <a href="?page=cuba-shipping-rates&tab=rates_by_province" class="nav-tab <?php echo $active_tab == 'rates_by_province' ? 'nav-tab-active' : ''; ?>">Tarifas por Provincia</a>
                <a href="?page=cuba-shipping-rates&tab=fees_by_category" class="nav-tab <?php echo $active_tab == 'fees_by_category' ? 'nav-tab-active' : ''; ?>">Tarifas por Categoría</a>
            </h2>
            <?php
            if ($active_tab == 'general') {
                $this->render_general_settings();
            } elseif ($active_tab == 'rates_by_province') {
                $this->render_rates_by_province();
            } else {
                $this->render_fees_by_category();
            }
            ?>
        </div>
        <?php
    }
    public function render_general_settings() {
        if (isset($_POST['save_general_settings'])) {
            check_admin_referer('cshr_general_settings');
            $percentage = isset($_POST['cuba_shipping_percentage']) ? floatval($_POST['cuba_shipping_percentage']) : 0;
            $rate_maritimo_general = isset($_POST['rate_maritimo_general']) ? floatval($_POST['rate_maritimo_general']) : 0;
            $rate_aereo_general = isset($_POST['rate_aereo_general']) ? floatval($_POST['rate_aereo_general']) : 0;
            $min_lbs_maritimo = isset($_POST['cshr_min_lbs_maritimo']) ? max(0, floatval($_POST['cshr_min_lbs_maritimo'])) : 0;
            $min_lbs_aereo = isset($_POST['cshr_min_lbs_aereo']) ? max(0, floatval($_POST['cshr_min_lbs_aereo'])) : 0;
            $min_mode = (isset($_POST['cshr_min_lbs_mode']) && $_POST['cshr_min_lbs_mode'] === 'block') ? 'block' : 'bill';
            $recipient_enabled = isset($_POST['cshr_recipient_fields_enabled']) ? 'yes' : 'no';
            update_option('cuba_shipping_percentage', $percentage);
            update_option('rate_maritimo_general', $rate_maritimo_general);
            update_option('rate_aereo_general', $rate_aereo_general);
            update_option('cshr_min_lbs_maritimo', $min_lbs_maritimo);
            update_option('cshr_min_lbs_aereo', $min_lbs_aereo);
            update_option('cshr_min_lbs_mode', $min_mode);
            update_option('cshr_recipient_fields_enabled', $recipient_enabled);
            echo '<div class="updated"><p>Configuración guardada correctamente.</p></div>';
        }

        $percentage = get_option('cuba_shipping_percentage', 0);
        $rate_maritimo_general = get_option('rate_maritimo_general', 0);
        $rate_aereo_general = get_option('rate_aereo_general', 0);
        $min_lbs_maritimo = get_option('cshr_min_lbs_maritimo', 0);
        $min_lbs_aereo = get_option('cshr_min_lbs_aereo', 0);
        $min_mode = get_option('cshr_min_lbs_mode', 'bill');
        $recipient_enabled = get_option('cshr_recipient_fields_enabled', 'no');
        ?>
        <form method="post">
            <?php wp_nonce_field('cshr_general_settings'); ?>
            <table class="form-table">
                <tbody>
                    <tr>
                        <th scope="row"><label for="cuba_shipping_percentage">Porcentaje adicional al rate final</label></th>
                        <td><input type="number" step="any" min="0" name="cuba_shipping_percentage" id="cuba_shipping_percentage" value="<?php echo esc_attr($percentage); ?>" /></td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="rate_maritimo_general">Rate Marítimo General</label></th>
                        <td><input type="number" step="any" min="0" name="rate_maritimo_general" id="rate_maritimo_general" value="<?php echo esc_attr($rate_maritimo_general); ?>" /></td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="rate_aereo_general">Rate Aéreo General</label></th>
                        <td><input type="number" step="any" min="0" name="rate_aereo_general" id="rate_aereo_general" value="<?php echo esc_attr($rate_aereo_general); ?>" /></td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="cshr_min_lbs_maritimo">Libras mínimas — Marítimo</label></th>
                        <td>
                            <input type="number" step="any" min="0" name="cshr_min_lbs_maritimo" id="cshr_min_lbs_maritimo" value="<?php echo esc_attr($min_lbs_maritimo); ?>" />
                            <p class="description">0 = sin mínimo (comportamiento anterior).</p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="cshr_min_lbs_aereo">Libras mínimas — Aéreo</label></th>
                        <td>
                            <input type="number" step="any" min="0" name="cshr_min_lbs_aereo" id="cshr_min_lbs_aereo" value="<?php echo esc_attr($min_lbs_aereo); ?>" />
                            <p class="description">0 = sin mínimo (comportamiento anterior).</p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="cshr_min_lbs_mode">Modo del mínimo</label></th>
                        <td>
                            <select name="cshr_min_lbs_mode" id="cshr_min_lbs_mode">
                                <option value="bill" <?php selected($min_mode, 'bill'); ?>>Facturar el mínimo (cobra las libras mínimas aunque el carrito pese menos)</option>
                                <option value="block" <?php selected($min_mode, 'block'); ?>>Bloquear el checkout (no permite comprar por debajo del mínimo)</option>
                            </select>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="cshr_recipient_fields_enabled">Datos del destinatario (Cuba)</label></th>
                        <td>
                            <label>
                                <input type="checkbox" name="cshr_recipient_fields_enabled" id="cshr_recipient_fields_enabled" <?php checked($recipient_enabled, 'yes'); ?> />
                                Pedir Carnet de Identidad y teléfono de Cuba en el checkout
                            </label>
                            <p class="description">Desactivado por defecto. Actívalo solo en sitios que lo requieran.</p>
                        </td>
                    </tr>
                </tbody>
            </table>
            <input type="submit" name="save_general_settings" class="button button-primary" value="Guardar Configuración">
        </form>
        <?php
    }

    public function render_rates_by_province() {
        global $wpdb;
        if (isset($_POST['save_rates'])) {
            check_admin_referer('cshr_save_rates');
            $wpdb->query('START TRANSACTION');
            $success = true;

            // Borrar todas las filas de la tabla
            $wpdb->query("DELETE FROM {$this->table_name}");

            foreach ($_POST['rates'] as $province => $municipalities) {
                foreach ($municipalities as $municipality => $data) {
                    $rate_maritimo = isset($data['rate_maritimo']) ? floatval($data['rate_maritimo']) : null;
                    $rate_aereo = isset($data['rate_aereo']) ? floatval($data['rate_aereo']) : null;
                    $active = isset($data['active']) ? 1 : 0;
                    $result = $wpdb->insert($this->table_name, [
                        'province' => sanitize_text_field($province),
                        'municipality' => sanitize_text_field($municipality),
                        'rate_maritimo' => $rate_maritimo,
                        'rate_aereo' => $rate_aereo,
                        'active' => $active
                    ]);
                    if ($result === false) {
                        $success = false;
                        break 2; // Exit both foreach loops
                    }
                }
            }

            if ($success) {
                $wpdb->query('COMMIT');
                echo '<div class="updated"><p>Tarifas guardadas correctamente.</p></div>';
            } else {
                $wpdb->query('ROLLBACK');
                echo '<div class="error"><p>Error al guardar las tarifas.</p></div>';
            }
        }

        $provinces = $this->add_cuba_provinces([])['CU'];
        echo '<form method="post">';
        wp_nonce_field('cshr_save_rates');
        echo '<table class="form-table"><thead><tr><th>Municipio</th><th>Rate Marítimo</th><th>Rate Aéreo</th><th>Activo</th></tr></thead><tbody>';
        foreach ($provinces as $province_code => $province_data) {
            echo "<tr><th colspan='4'>{$province_data['name']}</th></tr>";
            foreach ($province_data['municipalities'] as $municipality) {
                $rate_maritimo = $wpdb->get_var($wpdb->prepare("SELECT rate_maritimo FROM {$this->table_name} WHERE province = %s AND municipality = %s", $province_code, $municipality));
                $rate_aereo = $wpdb->get_var($wpdb->prepare("SELECT rate_aereo FROM {$this->table_name} WHERE province = %s AND municipality = %s", $province_code, $municipality));
                $active = $wpdb->get_var($wpdb->prepare("SELECT active FROM {$this->table_name} WHERE province = %s AND municipality = %s", $province_code, $municipality));
                $checked = $active ? 'checked' : '';
                echo "<tr>
                        <td>{$municipality}</td>
                        <td><input type='text' name='rates[{$province_code}][{$municipality}][rate_maritimo]' value='{$rate_maritimo}' /></td>
                        <td><input type='text' name='rates[{$province_code}][{$municipality}][rate_aereo]' value='{$rate_aereo}' /></td>
                        <td><input type='checkbox' name='rates[{$province_code}][{$municipality}][active]' {$checked} /></td>
                      </tr>";
            }
        }
        echo '</tbody></table><input type="submit" name="save_rates" class="button button-primary" value="Guardar Tarifas"></form>';
    }

    public function get_product_categories() {
        $categories = get_terms([
            'taxonomy' => 'product_cat',
            'hide_empty' => false,
        ]);
        return $categories;
    }

    public function render_fees_by_category() {
        $categories = $this->get_product_categories();
    
        if (isset($_POST['save_fees'])) {
            check_admin_referer('cshr_save_fees');
            foreach ($_POST['fees'] as $category_id => $fee) {
                $category_id = absint($category_id);
                $flat_price = isset($fee['flat_price']) ? floatval($fee['flat_price']) : 0;
                if (isset($fee['price_by_weight'])) {
                    update_term_meta($category_id, 'price_by_weight', floatval($fee['price_by_weight']));
                }
                update_term_meta($category_id, 'flat_price', $flat_price);
            }
            echo '<div class="updated"><p>Tarifas por categoría guardadas correctamente.</p></div>';
        }

        echo '<form method="post">';
        wp_nonce_field('cshr_save_fees');
        echo '<table class="form-table"><thead><tr>
            <th>Categoría</th>            
            <th>Precio Fijo</th>
          </tr></thead><tbody>';
        foreach ($categories as $category) {
            $price_by_weight = get_term_meta($category->term_id, 'price_by_weight', true);
            $flat_price = get_term_meta($category->term_id, 'flat_price', true);
            echo "<tr>
                    <td>{$category->name}</td>
                    
                    <td><input type='text' name='fees[{$category->term_id}][flat_price]' value='{$flat_price}' placeholder='Flat Price' /></td>
                  </tr>";
        }
        echo '</tbody></table><input type="submit" name="save_fees" class="button button-primary" value="Guardar Tarifas"></form>';
    }

    public function create_table() {
        global $wpdb;
        $charset_collate = $wpdb->get_charset_collate();
        $sql = "CREATE TABLE IF NOT EXISTS {$this->table_name} (
            id mediumint(9) NOT NULL AUTO_INCREMENT,
            province varchar(255) NOT NULL,
            municipality varchar(255) NOT NULL,
            rate_maritimo float DEFAULT NULL,
            rate_aereo float DEFAULT NULL,
            active tinyint(1) NOT NULL DEFAULT 1,
            PRIMARY KEY  (id)
        ) $charset_collate;";
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);
    }

    function agregar_mensaje_arriba_billing_details() {
        echo '<p style="background-color: #ffefc2; padding: 10px; border-left: 4px solid #ffa500; font-weight: bold;">
            Por favor, ingresa la dirección de facturación asociada a la tarjeta que estás utilizando para el pago. Esto es necesario para evitar errores en el procesamiento del pago.
        </p>';
    }

    public function agregar_mensaje_arriba_shipping_form() {
        echo '<p style="background-color: #ffefc2; padding: 10px; border-left: 4px solid #ffa500; font-weight: bold;">
            Si deseas que tu pedido se envíe a una dirección diferente a la de facturación, marca la casilla "¿Enviar a una dirección diferente?", ubicada justo debajo, y completa los datos de envío.
        </p>';
    }
    
    public function add_cuba_provinces($states) {
        $states['CU'] = [
            'PRI' => [
                'name' => 'Pinar del Río',
                'municipalities' => [
                    'Pinar del Río',
                    'San Luis',
                    'San Juan y Martínez',
                    'Consolación del Sur',
                    'Viñales',
                    'La Palma',
                    'Los Palacios',
                    'Minas de Matahambre',
                    'Mantua',
                    'Guane'
                ]
            ],
            'ART' => [
                'name' => 'Artemisa',
                'municipalities' => [
                    'Artemisa',
                    'Mariel',
                    'Guanajay',
                    'Caimito',
                    'Bauta',
                    'San Antonio de los Baños',
                    'Güira de Melena',
                    'Alquízar',
                    'Candelaria',
                    'Bahía Honda'
                ]
            ],
            'HAB' => [
                'name' => 'La Habana',
                'municipalities' => [
                    'Playa',
                    'Plaza de la Revolución',
                    'Centro Habana',
                    'La Habana Vieja',
                    'Regla',
                    'La Habana del Este',
                    'Guanabacoa',
                    'San Miguel del Padrón',
                    'Diez de Octubre',
                    'Cerro',
                    'Marianao',
                    'La Lisa',
                    'Boyeros',
                    'Arroyo Naranjo',
                    'Cotorro'
                ]
            ],
            'MAY' => [
                'name' => 'Mayabeque',
                'municipalities' => [
                    'Bejucal',
                    'San José de las Lajas',
                    'Jaruco',
                    'Santa Cruz del Norte',
                    'Madruga',
                    'Nueva Paz',
                    'San Nicolás',
                    'Güines',
                    'Melena del Sur',
                    'Batabanó',
                    'Quivicán'
                ]
            ],
            'MTZ' => [
                'name' => 'Matanzas',
                'municipalities' => [
                    'Matanzas',
                    'Cárdenas',
                    'Martí',
                    'Colón',
                    'Perico',
                    'Jovellanos',
                    'Pedro Betancourt',
                    'Limonar',
                    'Unión de Reyes',
                    'Ciénaga de Zapata',
                    'Jagüey Grande',
                    'Calimete'
                ]
            ],
            'CFG' => [
                'name' => 'Cienfuegos',
                'municipalities' => [
                    'Cienfuegos',
                    'Palmira',
                    'Cruces',
                    'Cumanayagua',
                    'Rodas',
                    'Lajas',
                    'Aguada de Pasajeros',
                    'Abreus'
                ]
            ],
            'VCL' => [
                'name' => 'Villa Clara',
                'municipalities' => [
                    'Santa Clara',
                    'Placetas',
                    'Remedios',
                    'Caibarién',
                    'Camajuaní',
                    'Encrucijada',
                    'Sagua la Grande',
                    'Quemado de Güines',
                    'Corralillo',
                    'Santo Domingo',
                    'Ranchuelo',
                    'Manicaragua'
                ]
            ],
            'SSP' => [
                'name' => 'Sancti Spíritus',
                'municipalities' => [
                    'Sancti Spíritus',
                    'Trinidad',
                    'Jatibonico',
                    'Taguasco',
                    'Cabaiguán',
                    'Fomento',
                    'Yaguajay',
                    'La Sierpe'
                ]
            ],
            'CAV' => [
                'name' => 'Ciego de Ávila',
                'municipalities' => [
                    'Ciego de Ávila',
                    'Morón',
                    'Venezuela',
                    'Baraguá',
                    'Bolivia',
                    'Chambas',
                    'Ciro Redondo',
                    'Florencia',
                    'Majagua',
                    'Primero de Enero'
                ]
            ],
            'CMG' => [
                'name' => 'Camagüey',
                'municipalities' => [
                    'Camagüey',
                    'Nuevitas',
                    'Minas',
                    'Sibanicú',
                    'Guáimaro',
                    'Santa Cruz del Sur',
                    'Esmeralda',
                    'Florida',
                    'Vertientes',
                    'Jimaguayú',
                    'Sierra de Cubitas',
                    'Najasa'
                ]
            ],
            'LTU' => [
                'name' => 'Las Tunas',
                'municipalities' => [
                    'Las Tunas',
                    'Puerto Padre',
                    'Amancio',
                    'Colombia',
                    'Manatí',
                    'Jobabo',
                    'Jesús Menéndez',
                    'Majibacoa'
                ]
            ],
            'HOL' => [
                'name' => 'Holguín',
                'municipalities' => [
                    'Holguín',
                    'Gibara',
                    'Rafael Freyre',
                    'Banes',
                    'Antilla',
                    'Báguanos',
                    'Cacocum',
                    'Calixto García',
                    'Cauto Cristo',
                    'Cueto',
                    'Frank País',
                    'Mayarí',
                    'Moa',
                    'Sagua de Tánamo',
                    'Urbano Noris'
                ]
            ],
            'GRA' => [
                'name' => 'Granma',
                'municipalities' => [
                    'Bayamo',
                    'Manzanillo',
                    'Yara',
                    'Bartolomé Masó',
                    'Buey Arriba',
                    'Guisa',
                    'Jiguaní',
                    'Media Luna',
                    'Niquero',
                    'Pilón',
                    'Río Cauto',
                    'Campechuela'
                ]
            ],
            'SCU' => [
                'name' => 'Santiago de Cuba',
                'municipalities' => [
                    'Santiago de Cuba',
                    'Contramaestre',
                    'Guamá',
                    'Mella',
                    'Palma Soriano',
                    'San Luis',
                    'Segundo Frente',
                    'Songo-La Maya',
                    'Tercer Frente'
                ]
            ],
            'GTM' => [
                'name' => 'Guantánamo',
                'municipalities' => [
                    'Guantánamo',
                    'Baracoa',
                    'Caimanera',
                    'El Salvador',
                    'Imías',
                    'Maisí',
                    'Manuel Tames',
                    'Niceto Pérez',
                    'San Antonio del Sur',
                    'Yateras'
                ]
            ],
            'IJV' => [
                'name' => 'Isla de la Juventud',
                'municipalities' => [
                    'Isla de la Juventud'
                ]
            ]
        ];
        return $states;
    }
}