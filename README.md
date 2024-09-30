# WooCommerce DFX Payment Gateway

This repository contains a WooCommerce payment gateway plugin that allows customers to pay with cryptocurrencies using the DFX payment service.

## Description

The WooCommerce DFX Payment Gateway plugin integrates DFX's cryptocurrency payment service into your WooCommerce store. It allows customers to pay for their orders using various cryptocurrencies, including Bitcoin and Lightning Network payments.

## Features

- Seamless integration with WooCommerce
- Support for multiple cryptocurrencies
- Customizable payment gateway settings
- Responsive payment iframe

## Installation

1. Clone or download this repository to your local machine.
2. Ensure you have Node.js and npm installed on your system.
3. Navigate to the plugin directory in your terminal.
4. Install dependencies:
   ```
   npm install
   ```
5. Build the plugin:
   ```
   npm run build:plugin
   ```
   This will create a `dfx-plugin.zip` file in the plugin directory.
6. Upload the `dfx-plugin.zip` file to your WordPress site via the WordPress admin panel (Plugins > Add New > Upload Plugin).
7. Activate the plugin through the 'Plugins' menu in WordPress.
8. Go to WooCommerce > Settings > Payments and configure the DFX Gateway settings.

## Adding the DFX Payment Page

To add the DFX payment page to your WordPress site:

1. Create a new page in WordPress (e.g., "DFX Pay").
2. Add the following shortcode to the page content:
   ```
   [dfx_pay_shortcode]
   ```
3. Publish the page.
4. Note the URL of this page (e.g., `https://your-site.com/dfx-pay/`).

The shortcode `[dfx_pay_shortcode]` will render the DFX payment iframe on the page, allowing customers to complete their cryptocurrency payments.

## Configuration

After installation:

1. Go to WooCommerce > Settings > Payments.
2. Click on "DFX Gateway" to configure the payment method.
3. Set the title and description that customers will see during checkout.
4. Enter your DFX Route ID (default is '16760').
5. Save the settings.

## Building the Frontend for development

For development purposes, you can use the following npm scripts:

- To build the assets for production:
  ```
  npm run build
  ```
- For development with hot reloading:
  ```
  npm run start
  ```

These commands will compile and bundle the JavaScript and CSS files required for the plugin's frontend functionality.

## Support

For support or feature requests, please open an issue in this repository.
