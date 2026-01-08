<?php
/**
 * Settings help text template
 *
 * @package Swiftcomplete
 */

if (!defined('ABSPATH')) {
    exit;
}
?>
<script type="text/javascript">
    !function (e, t, c) {
        e.swiftcomplete = e.swiftcomplete || {};
        var s = t.createElement("script");
        s.async = !0, s.src = c;
        var r = t.getElementsByTagName("script")[0];
        r.parentNode.insertBefore(s, r)
    }(window, document, "https://www.swiftcomplete.com/js/what3words.js");

    var SWIFTCOMPLETE_API_KEY = "";
    var SWIFTCOMPLETE_SEARCH_FIELD_ID = "swiftcomplete_bias_towards";

    function initialiseSwiftcomplete() {
        swiftcomplete.runWhenReady(function () {
            swiftcomplete.controls["Places search"] = new swiftcomplete.SwiftLookup({
                field: document.getElementById(SWIFTCOMPLETE_SEARCH_FIELD_ID),
                key: SWIFTCOMPLETE_API_KEY,
                searchFor: ""
            });

            document.getElementById(SWIFTCOMPLETE_SEARCH_FIELD_ID).addEventListener('swiftcomplete:swiftlookup:selected', function (e) {
                document.getElementById('swiftcomplete_bias_towards_lat_lon').value = e.detail.result.geometry.centre.lat + ',' + e.detail.result.geometry.centre.lon;
            }, false);
        });
    }

    window.addEventListener("load", initialiseSwiftcomplete, false);
</script>
<style>
    .swiftcomplete-card {
        background-color: #FFF;
        padding: 20px;
        margin-bottom: 20px;
    }

    .swiftcomplete-text {
        font-size: 1.5em;
    }

    .swiftcomplete-text-sm {
        font-size: 1.2em;
    }

    .swiftcomplete-card h1 {
        line-height: 1.3em;
    }

    .help-text {
        color: #555;
        margin-left: 10px;
    }
</style>
<script type="text/javascript">
    window.addEventListener('load', function () {
        if (document.getElementById('swiftcomplete_api_key').value.length == 0) {
            document.getElementById('swiftcomplete-setup-instructions').style.display = 'block';
        } else {
            document.getElementById('swiftcomplete-existing-integration-instructions').style.display = 'block';
        }

        document.getElementById('swiftcomplete-run-setup-again').onclick = function () {
            document.getElementById('swiftcomplete-setup-instructions').style.display = 'block';
            document.getElementById('swiftcomplete-existing-integration-instructions').style.display = 'none';
        };
    });
</script>
<div class="swiftcomplete-card">
    <div style="display: grid; column-gap: 50px; max-width: 1200px;">
        <div style="grid-column-start: 1; grid-column-end: 3;">
            <!--- Logo --->
            <img src="https://www.swiftcomplete.com/images/swiftcomplete-what3words-logo.svg" alt="Swiftcomplete"
                title="Swiftcomplete" width="175px" />
            <h1>SwiftLookup Plugin</h1>
            <p class="swiftcomplete-text-xm">Capture accurate billing and shipping addresses with SwiftLookup plugin for
                WooCommerce.</p>
            <p class="swiftcomplete-text-xm">Before you can use the Swiftcomplete plugin, you will need to get an API
                key.</p>
            <a href="https://www.swiftcomplete.com/account/register/" target="_blank" class="button button-primary">Get
                an API key</a>
            <div style="display: none;" id="swiftcomplete-existing-integration-instructions">
                <p class="swiftcomplete-text-xm">If you encounter any unexpected behaviour with the plugin, please
                    contact us at <a href="mailto:support@swiftcomplete.com">support@swiftcomplete.com</a> and we'll be
                    happy to help you.</p>
            </div>
        </div>
        <div style="grid-column-start: 3; grid-column-end: 4;">
            <img alt="Swiftcomplete"
                src="https://corpassets.what3words.com/wp-content/uploads/2022/08/Swiftcomplete-1100-x-825-px.jpg"
                width="400" />
        </div>
    </div>
</div>