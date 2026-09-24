=== BueDoc Facturação Electrónica AGT ===
Contributors: ravelinodecastro, buegood, buegoodcompany
Donate link: https://doc.buegood.com
Tags: invoicing, invoice, agt, angola, billing
Requires at least: 6.0
Tested up to: 7.1
Requires PHP: 7.4
Stable tag: 1.0.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Automatic issuance of invoices and receipts certified by the AGT tax authority in Angola via the BueDoc API for WooCommerce online stores.

== Description ==

**BueDoc Facturação Electrónica AGT** is the official integration plugin for automated issuance of fiscal invoices and receipts certified by **AGT (Administração Geral Tributária - General Tax Administration of Angola)** in WooCommerce stores.

With this plugin, your store issues certified Invoices (**FT**) and Invoice-Receipts (**FR**) in full compliance with Angolan tax legislation, featuring sequential numbering in dedicated API series, chained RSA-SHA1 digital signatures, RS256 JWS tokens, and official fiscal QR Code generation.

### Key Features

* **AGT Certified (Angola):** Fully compliant with Presidential Decree 71/25 and the AGT DS-120 technical electronic invoicing specification.
* **Exclusive API Series (`isApi: true`):** Document numbering generated from your online store is isolated from the BueDoc web interface, preventing chronological gaps or sequence collisions.
* **100% Automated Issuance:** Automatically generates and transmits the invoice to AGT as soon as the order is paid or reaches your chosen order statuses (*Processing* or *Completed*).
* **Tax ID (NIF) Field at Checkout:** Adds a dedicated NIF (Tax Number) field to WooCommerce billing with real-time validation against the official AGT taxpayer database.
* **Official VAT (IVA) Rates and Exemptions:** Native support for all Angolan VAT rates (14%, 7%, 5%, 0%) and official AGT exemption codes (**M00** through **M99**).
* **Itemized Shipping and Fees:** Shipping costs and additional fees are itemized on invoice lines with appropriate tax rates or legal exemption codes.
* **Credit Notes (NC):** Issue rectified Credit Notes referencing the original invoice when orders are refunded.
* **Customer Area and Email Attachments:** Customers receive the official certified PDF invoice automatically attached to their order completion emails and can download it at any time from their My Account area.
* **HPOS Ready:** Fully declared compatibility with WooCommerce High-Performance Order Storage (Custom Order Tables).

== Third-Party Service Disclosure ==

This plugin connects to and relies on the external cloud invoicing service **BueDoc API**, developed and operated by **BueGood Tecnologias**:

* **Service Provider:** BueGood Tecnologias (https://buegood.com)
* **Service Purpose:** Chained RSA-SHA1 digital signing of fiscal documents, official QR Code generation, chronological sequential number assignment, and electronic submission to the AGT tax authority servers in Angola.
* **Data Transmitted:** When an invoice is issued, the following order details are transmitted to the BueDoc API: customer NIF (tax number), customer name, billing address, email, telephone, and order line items (product descriptions, quantities, unit prices, and VAT rates). During live checkout validation, only the entered NIF is verified against the AGT database.
* **Terms of Service:** https://doc.buegood.com/termos
* **Privacy Policy:** https://doc.buegood.com/privacidade

== Installation ==

### Automatic Installation
1. Log in to your WordPress Admin dashboard and navigate to **Plugins > Add New**.
2. Search for `BueDoc Facturação Electrónica AGT`.
3. Click **Install Now**, and then click **Activate**.

### Manual Installation
1. Download the plugin ZIP file.
2. In your WordPress Admin dashboard, go to **Plugins > Add New > Upload Plugin**.
3. Select the `buedoc-facturacao-electronica-agt.zip` file and click **Install Now**.
4. Activate the plugin from the Plugins screen.

### Initial Configuration
1. Go to **WooCommerce > Settings > BueDoc Facturação** (or click the **BueDoc Facturação** submenu under WooCommerce).
2. Choose the environment (**Production** or **Sandbox / Homologação**).
3. Paste your **BueDoc API Key** (generated in the BueDoc dashboard under *Settings > API Keys*).
4. Click **Testar Ligação à API BueDoc** (Test Connection) to verify your credentials, active plan, remaining monthly quota, and reserved API series.
5. Set your preferred automatic issuance trigger statuses, default VAT rates, and checkout NIF settings.
6. Click **Save changes**.

== Frequently Asked Questions ==

= What is required to use this plugin? =
You need an active WordPress website running WooCommerce, and an account on the BueDoc platform (https://doc.buegood.com) with an API-enabled plan and your AGT private key configured.

= Where do I get my BueDoc API Key? =
In the BueDoc dashboard, go to **Company Settings > API Keys** and create a new key for your store.

= How does checkout NIF validation work? =
When "Real-Time Validation" is enabled, the plugin checks whether the NIF entered by the customer corresponds to an active taxpayer in the AGT database. If the customer leaves the field blank and it is optional, the document is issued to the statutory Final Consumer NIF `999999999`.

= Can I issue invoices manually? =
Yes. On any order details screen in WooCommerce, the "BueDoc Factura" Meta Box provides a button to issue the invoice manually (choosing between FR and FT), as well as a bulk issuance action on the orders list table.

= Can customers download the invoice PDF? =
Yes. The official certified PDF is automatically attached to order completion emails sent to the customer and is accessible in their "My Account > Orders" section.

= Is this plugin compatible with WooCommerce HPOS? =
Yes. The plugin declares full compatibility with WooCommerce High-Performance Order Storage (HPOS).

== Screenshots ==

1. BueDoc settings panel in WooCommerce.
2. API connection test with active plan and quota display.
3. Checkout NIF field with real-time AGT taxpayer validation.
4. Order screen Meta Box with fiscal details, QR Code, and PDF download.
5. Orders list column with document number and AGT status badge.

== Changelog ==

= 1.0.0 =
* Official initial release.
* Automated issuance of AGT-certified Invoices (FT) and Invoice-Receipts (FR).
* Support for dedicated API series (`isApi: true`).
* Checkout NIF field with real-time AGT taxpayer verification.
* Support for all official VAT rates and AGT exemption codes (M00 through M99).
* Automatic PDF attachment to WooCommerce emails and customer My Account download.
* Credit Note (NC) issuance for refunded orders.
* Full compatibility with High-Performance Order Storage (HPOS).

== Upgrade Notice ==

= 1.0.0 =
Initial release.
