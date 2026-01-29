=== SwiftLookup for WooCommerce ===
Contributors: Swiftcomplete
Tags: Swiftcomplete, address validator, what3words address, searchbox, search, address, validation, autosuggest, w3w, capture, address lookup, autocomplete, verification, address collection, address finder, cleansing, address standardising
Requires at least: 4.7
Tested up to: 6.4
Stable tag: 1.1.0
Requires PHP: 7.0
License: GPLv2 or later
License URI: http://www.gnu.org/licenses/gpl-2.0.html

Swiftcomplete is designed to be integrated into your address capture flow, for example on e-commerce checkout pages. 

## Key Features

* Fast: Operates with low latency
* Comprehensive: Combines high quality geospatial databases and multi*residence addresses for best possible address matching.
* Easy to use: Simple, effective user interface
* Cost-effective: Credit packs from £10 (3p per address) (Bespoke enterprise solutions available)
* what3words entry: included as standard

== Installation ==

= WordPress Installation (recommended) =

You can install SwiftLookup plugin through the WordPress plugins page in your WordPress admin panel.

1. Navigate to the _Plugins > Add New_ page
2. Search for "SwiftLookup"
3. Click the _Install Now_ button.
4. Once installed, you can activate the plugin on the _Plugins_ page.

= Manual Installation =

You can download the SwiftLookup plugin from the [WordPress Plugins site](https://wordpress.org/plugins/swiftlookup/#installation).

1. Download the SwiftLookup plugin from the WordPress Plugins site
2. Once you have downloaded the zipped plugin, you can upload it to your WordPress installation by navigating to _Plugins > Add New_, click the _Upload Plugin_ button
3. Select the zipped plugin file and click _Install Now_
4. Once installed, you can activate the plugin on the _Plugins_ page.

= More about what3words =

Find our full developer documentation here:
[https://swiftcomplete.notion.site/Swiftcomplete-WooCommerce-plugin-for-SwiftLookup](https://swiftcomplete.notion.site/Swiftcomplete-WooCommerce-plugin-for-SwiftLookup-1a466db17f3b8018bc4ce65f85f6c852)

You can learn more about our privacy policy here:
[https://www.swiftcomplete.com/privacy/](https://www.swiftcomplete.com/privacy/)

= Get in touch with us =

Have any questions? Want to learn more about how the SwiftLookup plugin works? Get in touch with us at [support@swiftcomplete.com](mailto:support@swiftcomplete.com).

== Screenshots ==

1. SwiftLookup
2. SwiftLookup Address flow
3. SwiftLookup - Input Flow - Postcode
4. SwiftLookup - Input Flow - Street Address
5. SwiftLookup - Input Flow - what3words address

== Changelog ==

= 1.1.0 =
* Added activation/deactivation hooks with comprehensive error handling
* Automatically deactivate when fatal error occurs
* Improved WooCommerce dependency checks with multiple safety layers
* Improved source code organization
* Enhanced safety checks throughout the plugin to prevent WordPress crashes
* Implemented template system for better HTML separation
* Blocks checkout fields

= 1.0.10 =
* Fixed issue where an address couldn't be selected if certain fields did not exist

= 1.0.9 =
* Fixed issue if what3words is disabled

= 1.0.8 =
* Update the latest swiftlookup.js

= 1.0.7 =
* Added check for address coverage when showing or hiding fields

= 1.0.6 =
* Remove invalid field validation on successful address population

