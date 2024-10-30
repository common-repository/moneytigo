<?php
/*
Plugin Name: Ovri Payment
Plugin URI: https://my.ovri.app
Description: Accept credit cards in less than 5 minutes
Version: 1.7.0
Author: OVRI SAS
Author URI: https://www.ovri.com
License: MIT
WC tested up to: 9.3.3
Domain Path: /languages
Text Domain: ovri
*/

define('OvriVersion', "1.7.0");

define('pnfenabled', false);

/* Additional links on the plugin page */
add_filter('plugin_row_meta', 'ovri_register_plugin_links', 10, 2);
if (is_admin()) {
  add_action('admin_notices', 'sample_admin_notice__success');
}
require_once('updater.php');

function sample_admin_notice__success()
{
  if (get_plugin_updates()['moneytigo/woocommerce-ovri.php']) {
?>
    <div class="notice notice-error is-dismissible">
      <p><span style='font-weight: bold; color: red;'>OVRI Payment Service -> </span><span style='color: red;'>
          <?php echo __('A new version of the OVRI plugin is currently available. update quickly', 'ovri') ?>
        </span></p>
    </div>
<?php
  }
}

function trademark_ovri_dsp()
{


  echo '<div style="background-color: lightgray;
  margin-bottom: 0px;
  text-align: right;
  padding: 5px;"><span style="font-size: 0.8em">' . __('Secure payment by OVRI.COM', 'ovri') . ' (' . OvriVersion . ')</span><a href="https://www.ovri.com" target="_blank" title="' . __('OVRI payment solution for Wordpress Woocommerce', 'ovri') . '" alt="' . __('OVRI payment solution for Wordpress Woocommerce', 'ovri') . '"><img alt="' . __('Ovri Payment for WordPress', 'ovri') . '" title="' . __('Ovri Payment for WordPress', 'ovri') . '" style="height: 35px; width: 160px;" src="' . ovri_get_file("assets/img/footer_ovri_logo.png") . '" /></a></div>';
}

/* Auto update plugins */
function ovri_update_auto_plins($update, $item)
{
  // Array of plugin slugs to always auto-update
  $plugins = array(
    'moneytigo',
    'ovri'
  );
  if (in_array($item->slug, $plugins)) {
    return true;
  } else {
    return $update;
  }
}

add_filter('auto_update_plugin', 'ovri_update_auto_plins', 10, 2);

/* Securing file calls by taking into account specific installations */
function ovri_get_file($namefiles = "")
{
  $plugin_url = plugin_dir_url(__FILE__);
  return $plugin_url . $namefiles;
}

/* Add styles Css */
function ovri_load_plugin_css()
{
  $plugin_url = plugin_dir_url(__FILE__);
  wp_enqueue_style('ovri', $plugin_url . 'assets/css/styles.css');

  wp_enqueue_style('bootstrapcss', 'https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css', array(), false, 'all');

  wp_register_script('bootstrapjs', 'https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js', array('jquery'), false, true);
  wp_enqueue_script('bootstrapjs');

  wp_register_script('ovriPayment', $plugin_url . 'assets/js/ovrithreesecure.js', array('jquery'), false, true);
  wp_enqueue_script('ovriPayment');
}
add_action('wp_enqueue_scripts', 'ovri_load_plugin_css');

function addThreePanel()
{
  echo '<div class="modal fade" data-bs-backdrop="static" data-bs-keyboard="false" id="modalThreePanel" tabindex="-1" role="dialog" aria-labelledby="exampleModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered modal-fullscreen-sm-down" role="document">
    <div class="modal-content">
      <div class="modal-body">
        <iframe src=""  id="OvriIframeThree" style="width: 100%; min-height: 440px;"></iframe>
      </div>
    </div>
  </div>
</div>';
}
add_action('wp_footer', 'addThreePanel');

/* Function for universal calling in the payment sub-modules */
function ovri_universale_params()
{
  $baseUriOvriWEB = "https://checkout.ovri.app";
  $baseUriOvriAPI = "https://api.ovri.app/payment";
  $baseApiEndpoint = "https://api.ovri.app/api/";
  $config = array(
    'Version' => "1.7.0",
    'ApiInitPayment' => $baseUriOvriAPI . "/init_transactions/",
    'ApiGetTransaction' => $baseUriOvriAPI . "/transactions/",
    'ApiGetTransactionByOrderId' => $baseUriOvriAPI . "/transactions_by_merchantid/",
    'WebUriStandard' => $baseUriOvriWEB . "/pay/standard/token/",
    'WebUriInstallment' => $baseUriOvriWEB . "/pay/installment/token/",
    'WebUriSubscription' => $baseUriOvriWEB . "/pay/subscription/token/",
    'subscription' => $baseApiEndpoint . 'subscriptionTEST',
  );
  return $config;
}

function ovri_register_plugin_links($links, $file)
{
  $base = plugin_basename(__FILE__);
  if ($file == $base) {
    $links[] = '<a href="https://docs.ovri.app" target="_blank">' . __('Documentation', 'ovri') . '</a>';
  }
  return $links;
}

/* WooCommerce fallback notice. */
function ovri_ipg_fallback_notice()
{
  $htmlToReturn = '<div class="error">';
  $htmlToReturn .= '<p>' . sprintf(__('The Ovri module works from Woocommerce version %s minimum', 'ovri'), '<a href="http://wordpress.org/extend/plugins/woocommerce/">WooCommerce</a>') . '</p>';
  $htmlToReturn .= '</div>';
  echo $htmlToReturn;
}

/* Loading both payment methods */
function custom_Ovri_gateway_load()
{
  global $woocommerce;
  if (!class_exists('WC_Payment_Gateway')) {
    add_action('admin_notices', 'ovri_ipg_fallback_notice');
    return;
  }

  /* Payment classic */
  function wc_CustomOvri_add_gateway($methods)
  {


    array_unshift($methods, 'WC_Ovri');
    return $methods;
  }

  /* Payment by installments */
  function wc_CustomOvriPnfTwo_add_gateway($methods)
  {
    $methods[] = 'WC_OvriPnfTwo';
    return $methods;
  }

  function wc_CustomOvriPnfThree_add_gateway($methods)
  {
    $methods[] = 'WC_OvriPnfThree';
    return $methods;
  }

  function wc_CustomOvriPnfFour_add_gateway($methods)
  {
    $methods[] = 'WC_OvriPnfFour';
    return $methods;
  }

  add_filter('woocommerce_payment_gateways', 'wc_CustomOvri_add_gateway');
  if (pnfenabled) {
    add_filter('woocommerce_payment_gateways', 'wc_CustomOvriPnfTwo_add_gateway');
    add_filter('woocommerce_payment_gateways', 'wc_CustomOvriPnfThree_add_gateway');
    add_filter('woocommerce_payment_gateways', 'wc_CustomOvriPnfFour_add_gateway');
  }

  /* Load class for both payment methods */
  require_once plugin_dir_path(__FILE__) . 'class-wc-ovri.php';
  if (pnfenabled) {
    require_once plugin_dir_path(__FILE__) . 'class-wc-ovripnf-two.php';
    require_once plugin_dir_path(__FILE__) . 'class-wc-ovripnf-three.php';
    require_once plugin_dir_path(__FILE__) . 'class-wc-ovripnf-four.php';
  }
}
add_action('plugins_loaded', 'custom_Ovri_gateway_load');

add_action('before_woocommerce_init', function () {
  if (class_exists(\Automattic\WooCommerce\Utilities\FeaturesUtil::class)) {
    \Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility('custom_order_tables', __FILE__, true);
  }
});

/* Adds custom settings url in plugins page. */
function ovri_action_links($links)
{
  $settings = array(
    'settings' => sprintf(
      '<a href="%s">%s</a>',
      admin_url('admin.php?page=wc-settings&tab=checkout'),
      __('Payment Gateways', 'Ovri')
    )
  );
  return array_merge($settings, $links);
}
add_filter('plugin_action_links_' . plugin_basename(__FILE__), 'ovri_action_links');

/* Filtering of methods according to the amount of the basket */
add_filter('woocommerce_available_payment_gateways', 'ovri_payment_method_filters', 1);

function ovri_payment_method_filters($gateways)
{
  if (isset($gateways['ovri'])) {
    if ($gateways['ovri']->enabled == "yes") {
      if ((!$gateways['ovri']->ovri_gateway_api_key || $gateways['ovri']->ovri_gateway_api_key == ' ') || (!$gateways['ovri']->ovri_gateway_secret_key || $gateways['ovri']->ovri_gateway_secret_key == ' ')) {
        if (!is_admin()) {
          wc_add_notice('<b>Ovri</b> : ' . __('Module not configured, API key or ENCRYPTION key missing', 'ovri') . '', 'error');
        }
        unset($gateways['ovri']); //Not available because not settings
      }
    }
  }

  if (isset($gateways['ovripnftwo'])) {
    if ($gateways['ovripnftwo']->enabled == "yes") {
      if ((!$gateways['ovri']->ovri_gateway_api_key || $gateways['ovri']->ovri_gateway_api_key == ' ') || (!$gateways['ovri']->ovri_gateway_secret_key || $gateways['ovri']->ovri_gateway_secret_key == ' ')) {
        if (!is_admin()) {
          wc_add_notice('<b>Ovri (2X)</b> : ' . __('Module not configured, API key or ENCRYPTION key missing', 'ovri') . '', 'error');
        }
        unset($gateways['ovripnftwo']); //Not available because not settings
      }
    }
  }

  if (isset($gateways['ovripnfthree'])) {
    if ($gateways['ovripnfthree']->enabled == "yes") {
      if ((!$gateways['ovri']->ovri_gateway_api_key || $gateways['ovri']->ovri_gateway_api_key == ' ') || (!$gateways['ovri']->ovri_gateway_secret_key || $gateways['ovri']->ovri_gateway_secret_key == ' ')) {
        if (!is_admin()) {
          wc_add_notice('<b>Ovri (3X)</b> : ' . __('Module not configured, API key or ENCRYPTION key missing', 'ovri') . '', 'error');
        }
        unset($gateways['ovripnfthree']); //Not available because not settings
      }
    }
  }

  if (isset($gateways['ovripnffour'])) {
    if ($gateways['ovripnffour']->enabled == "yes") {
      if ((!$gateways['ovri']->ovri_gateway_api_key || $gateways['ovri']->ovri_gateway_api_key == ' ') || (!$gateways['ovri']->ovri_gateway_secret_key || $gateways['ovri']->ovri_gateway_secret_key == ' ')) {
        if (!is_admin()) {
          wc_add_notice('<b>Ovri (4X)</b> : ' . __('Module not configured, API key or ENCRYPTION key missing', 'ovri') . '', 'error');
        }
        unset($gateways['ovripnffour']); //Not available because not settings
      }
    }
  }

  //Check first if payment module is settings	
  if (isset($gateways['ovripnftwo'])) {
    if ($gateways['ovripnftwo']->enabled == "yes") {
      /* Check if the amount of the basket is sufficient to display the method in several installments*/
      global $woocommerce;

      $IPSPnf = $gateways['ovripnftwo']->settings;

      if (isset($woocommerce->cart->total) && $woocommerce->cart->total < $IPSPnf['seuil']) {
        unset($gateways['ovripnftwo']);
      }
    }
  }

  if (isset($gateways['ovripnfthree'])) {
    if ($gateways['ovripnfthree']->enabled == "yes") {
      /* Check if the amount of the basket is sufficient to display the method in several installments*/
      global $woocommerce;

      $IPSPnf = $gateways['ovripnfthree']->settings;

      if (isset($woocommerce->cart->total) && $woocommerce->cart->total < $IPSPnf['seuil']) {
        unset($gateways['ovripnfthree']);
      }
    }
  }

  if (isset($gateways['ovripnffour'])) {
    if ($gateways['ovripnffour']->enabled == "yes") {
      /* Check if the amount of the basket is sufficient to display the method in several installments*/
      global $woocommerce;

      $IPSPnf = $gateways['ovripnffour']->settings;

      if (isset($woocommerce->cart->total) && $woocommerce->cart->total < $IPSPnf['seuil']) {
        unset($gateways['ovripnffour']);
      }
    }
  }
  /* Return of available methods */
  return $gateways;
}


/* Load composer OVRI */
function ovri_wc_init()
{
  if (!class_exists('Ovri\Payment')) {
    require_once(plugin_dir_path(__FILE__) . 'vendor/autoload.php');
  }
}
add_action('plugins_loaded', 'ovri_wc_init');

add_action('woocommerce_blocks_loaded', 'ovri_wc_register_order_approval_payment_method_type');

/**
 * Custom function to register a payment method type

 */
function ovri_wc_register_order_approval_payment_method_type()
{
  // Check if the required class exists
  if (!class_exists('Automattic\WooCommerce\Blocks\Payments\Integrations\AbstractPaymentMethodType')) {
    return;
  }

  // Include the custom Blocks Checkout class
  require_once plugin_dir_path(__FILE__) . 'includes/WC_Ovri_Checkout_block.php';

  // Hook the registration function to the 'woocommerce_blocks_payment_method_type_registration' action
  add_action(
    'woocommerce_blocks_payment_method_type_registration',
    function (Automattic\WooCommerce\Blocks\Payments\PaymentMethodRegistry $payment_method_registry) {
      // Register an instance of WC_Phonepe_Blocks
      $payment_method_registry->register(new WC_Ovri_Blocks);
    }
  );
}



/* Adding translation files */
load_plugin_textdomain('ovri', false, dirname(plugin_basename(__FILE__)) . DIRECTORY_SEPARATOR . 'languages' . DIRECTORY_SEPARATOR);
