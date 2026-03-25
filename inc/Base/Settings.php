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

            $percentage = isset($_POST['cuba_shipping_percentage']) ? floatval($_POST['cuba_shipping_percentage']) : 0;

            update_option('cuba_shipping_percentage', $percentage);

            echo '<div class="updated"><p>Configuración guardada correctamente.</p></div>';

        }

    

        $percentage = get_option('cuba_shipping_percentage', 0);

        ?>

        <form method="post">

            <table class="form-table">

                <tbody>

                    <tr>

                        <th scope="row"><label for="cuba_shipping_percentage">Porcentaje adicional al rate final</label></th>

                        <td><input type="number" step="any" min="0" name="cuba_shipping_percentage" id="cuba_shipping_percentage" value="<?php echo esc_attr($percentage); ?>" /></td>

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

            $wpdb->query('START TRANSACTION');

            $success = true;

    

            // Borrar todas las filas de la tabla

            $wpdb->query("DELETE FROM {$this->table_name}");

    

            foreach ($_POST['rates'] as $province => $municipalities) {

                foreach ($municipalities as $municipality => $data) {

                    $rate = floatval($data['rate']);

                    $active = isset($data['active']) ? 1 : 0;

                    $result = $wpdb->insert($this->table_name, [

                        'province' => sanitize_text_field($province),

                        'municipality' => sanitize_text_field($municipality),

                        'rate' => $rate,

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

        echo '<form method="post"><table class="form-table"><tbody>';

        foreach ($provinces as $province_code => $province_data) {

            echo "<tr><th colspan='3'>{$province_data['name']}</th></tr>";

            foreach ($province_data['municipalities'] as $municipality) {

                $rate = $wpdb->get_var($wpdb->prepare("SELECT rate FROM {$this->table_name} WHERE province = %s AND municipality = %s", $province_code, $municipality));

                $active = $wpdb->get_var($wpdb->prepare("SELECT active FROM {$this->table_name} WHERE province = %s AND municipality = %s", $province_code, $municipality));

                $checked = $active ? 'checked' : '';

                echo "<tr>

                        <td>{$municipality}</td>

                        <td><input type='text' name='rates[{$province_code}][{$municipality}][rate]' value='{$rate}' /></td>

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

            foreach ($_POST['fees'] as $category_id => $fee) {

                $price_by_weight = floatval($fee['price_by_weight']);

                $flat_price = floatval($fee['flat_price']);

                update_term_meta($category_id, 'price_by_weight', $price_by_weight);

                update_term_meta($category_id, 'flat_price', $flat_price);

            }

            echo '<div class="updated"><p>Tarifas por categoría guardadas correctamente.</p></div>';

        }

    

        echo '<form method="post"><table class="form-table"><thead><tr>

            <th>Categoría</th>

            <th>Precio por Peso</th>

            <th>Precio Fijo</th>

          </tr></thead><tbody>';

        foreach ($categories as $category) {

            $price_by_weight = get_term_meta($category->term_id, 'price_by_weight', true);

            $flat_price = get_term_meta($category->term_id, 'flat_price', true);

            echo "<tr>

                    <td>{$category->name}</td>

                    <td><input type='text' name='fees[{$category->term_id}][price_by_weight]' value='{$price_by_weight}' placeholder='Price by Weight' /></td>

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

            rate float NOT NULL,

            active tinyint(1) NOT NULL DEFAULT 1,

            PRIMARY KEY  (id)

        ) $charset_collate;";

        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');

        dbDelta($sql);



        // Debugging message

        if ($wpdb->last_error) {

            error_log('Error creating table: ' . $wpdb->last_error);

        } else {

            error_log('Table created successfully or already exists.');

        }

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

