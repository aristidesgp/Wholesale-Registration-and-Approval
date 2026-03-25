# Cuba Shipping Rates for WooCommerce

A custom WooCommerce plugin that adds all Cuban provinces and municipalities as shipping destinations and allows configuring per-province, per-municipality, and per-product shipping rates.

- **Version:** 1.0.0
- **Author:** Aristides Gutierrez — [devfl.us](https://devfl.us)

## Features

- Adds all 16 Cuban provinces and their municipalities as WooCommerce shipping states.
- Only provinces with at least one active municipality are shown at checkout.
- Replaces the default city field at checkout with a **Municipio** (municipality) dropdown.
- Configurable shipping rates per province and per municipality stored in a custom database table.
- Per-product shipping rate field (**Rate de Envío a Cuba**) and optional **price-by-weight** toggle.
- Global shipping percentage and minimum order weight configurable from the admin panel.
- Displays product weight on shop listings, single product pages, and the cart.
- Validates minimum cart weight on checkout and shows a weight summary before payment.
- Custom shipping label in cart totals for Cuban destinations.
- Shipping summary refreshes automatically via WooCommerce AJAX when the shipping address changes.
- Admin panel under **Tarifas de Envío** with three tabs: General, Tarifas por Provincia, Tarifas por Categoría.
- Internal logging system for debugging.

## Requirements

- WordPress 5.0 or higher
- WooCommerce 4.0 or higher
- PHP 7.4 or higher
- Composer (for autoloader)

## Installation

1. Clone or copy the plugin into your `/wp-content/plugins/` directory:
    ``bash
    git clone <repository-url> cuba-shipping-rates
    ``

2. Install Composer dependencies:
    ``bash
    cd cuba-shipping-rates
    composer install
    ``

3. Activate the plugin from **Plugins** in the WordPress admin dashboard.

4. Make sure Cuba (`CU`) is enabled as a selling/shipping region in **WooCommerce > Settings > General**.

## Configuration

### General Settings

Go to **Tarifas de Envío > General** to set:

- **Global shipping percentage** — applied on top of the base province rate.
- **Minimum order weight** — the cart will block checkout if the total weight falls below this value.

### Rates by Province

Go to **Tarifas de Envío > Tarifas por Provincia** to manage rates for each province and municipality. You can activate or deactivate specific municipalities; only active ones will appear in the checkout dropdown.

### Rates by Category

Go to **Tarifas de Envío > Tarifas por Categoría** to define rates based on product categories.

### Per-Product Rate

On each WooCommerce product's edit page, under the **Shipping** tab, you will find:

- **Rate de Envío a Cuba** — a fixed shipping rate for that product when shipped to Cuba.
- **Precio por Peso** — check this box if the rate should be applied per unit of weight.

## Database

On activation the plugin creates a custom table `{prefix}_cuba_shipping_rates` with the following structure:

| Column         | Description                               |
|----------------|-------------------------------------------|
| `id`         | Primary key                               |
| `province`   | Province code (e.g. `HAB`, `SCU`)     |
| `municipality` | Municipality name                       |
| `rate`       | Shipping rate for this municipality       |
| `active`     | Whether this municipality is available    |

## Cuban Provinces

| Code  | Province              |
|-------|-----------------------|
| PRI   | Pinar del Río         |
| ART   | Artemisa              |
| HAB   | La Habana             |
| MAY   | Mayabeque             |
| MTZ   | Matanzas              |
| CFG   | Cienfuegos            |
| VCL   | Villa Clara           |
| SSP   | Sancti Spíritus       |
| CAV   | Ciego de Ávila        |
| CMG   | Camagüey              |
| LTU   | Las Tunas             |
| HOL   | Holguín               |
| GRM   | Granma                |
| SCU   | Santiago de Cuba      |
| GTM   | Guantánamo            |
| IJV   | Isla de la Juventud   |

## Development

### Project Structure

``
cuba-shipping-rates/
├── assets/           # CSS/JS assets
├── inc/
│   ├── Base/
│   │   ├── Activate.php       # Plugin activation hook
│   │   ├── Ajax.php           # AJAX handlers
│   │   ├── Enqueue.php        # Script/style enqueuing
│   │   ├── Logs.php           # Internal logging
│   │   ├── Schedule.php       # Cron jobs
│   │   ├── Settings.php       # Admin settings & DB management
│   │   ├── ShippingRates.php  # Core shipping rate logic
│   │   └── ShortCodes.php     # Shortcodes
│   └── util/
│       └── Helper.php         # Utility functions
├── templates/
│   └── cart/
│       └── cart-shipping.php  # Custom cart shipping template
├── composer.json
├── index.php                  # Plugin entry point
└── uninstall.php
``

### Contributing

1. Fork the repository.
2. Create a new branch: `git checkout -b feature/my-feature`.
3. Commit your changes: `git commit -m 'Add my feature'`.
4. Push to the branch: `git push origin feature/my-feature`.
5. Open a Pull Request.

## License

This plugin is proprietary software developed by [Aristides Gutierrez](https://devfl.us). All rights reserved.