# Wholesale Registration and Approval Plugin

A custom WooCommerce plugin that adds wholesale-specific registration fields, handles approval processes, and restricts access to the site based on approval status.

## Features

- Custom fields in the WooCommerce registration form (Company Name, Tax ID).
- Automatic assignment of "Pending Approval" status for new wholesale users.
- Admin interface to approve or reject wholesale user applications.
- Restriction of site access to approved users only.
- Automatic email notifications for users when their status changes (Approved/Rejected).

## Requirements

- WordPress 5.0 or higher
- WooCommerce 3.0 or higher

## Installation

1. Download or clone the repository into your WordPress `/wp-content/plugins/` directory.
    ```bash
    git clone https://github.com/yourusername/Wholesale-Registration-and-Approval.git
    ```

2. Activate the plugin from the **Plugins** section of your WordPress admin dashboard.

3. Ensure that registration is enabled for WooCommerce customers:
    - Go to **WooCommerce > Settings > Accounts & Privacy**.
    - Check the box for **"Allow customers to create an account on the My Account page."**

4. Once activated, the custom fields will appear on the WooCommerce registration page, and the approval system will be in place.

## Usage

### Registration Process

- New users registering through the WooCommerce registration page will need to fill out additional fields for **Company Name** and **Tax ID**.
- After registration, users will automatically be marked as **Pending Approval**, and they will not be able to access the site until approved by an admin.

### Admin Approval Workflow

- In the WordPress admin area, go to **Users > All Users** to view a list of users.
- You will see an additional column showing the user’s approval status (Approved, Rejected, Pending).
- Approve or reject users by editing their profile or using the custom approval interface (to be developed).

### Restrict Access

- Unapproved users attempting to log in will be redirected to a **Pending Approval** page and will not have access to the site until approved.

### Notifications

- The plugin will automatically send email notifications to users once their approval status has been updated (Approved or Rejected).

## Future Features (Planned)

- Custom admin interface for bulk approving or rejecting users.
- Customizable email templates for notifications.
- Additional registration fields for wholesale users.
- Integration with third-party CRMs for automatic wholesale customer management.

## Development

### How to contribute

1. Fork the repository.
2. Create a new branch (`git checkout -b feature-branch`).
3. Make your changes.
4. Commit your changes (`git commit -m 'Add new feature'`).
5. Push to the branch (`git push origin feature-branch`).
6. Create a new Pull Request.

### Known issues

- [ ] Compatibility testing with WooCommerce versions prior to 3.0.
- [ ] Pending Approval page content needs to be customized.
  
## License

This plugin is open source and licensed under the [MIT License](https://opensource.org/licenses/MIT).
