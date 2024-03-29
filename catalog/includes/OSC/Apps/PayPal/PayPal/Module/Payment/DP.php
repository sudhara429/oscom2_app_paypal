<?php
/**
  * osCommerce Online Merchant
  *
  * @copyright Copyright (c) 2015 osCommerce; http://www.oscommerce.com
  * @license GPL; http://www.oscommerce.com/gpllicense.txt
  */

  namespace OSC\Apps\PayPal\PayPal\Module\Payment;

  use OSC\OM\HTML;
  use OSC\OM\OSCOM;
  use OSC\OM\Registry;

  use OSC\Apps\PayPal\PayPal\PayPal as PayPalApp;

  class DP implements \OSC\OM\Modules\PaymentInterface {
    var $code, $title, $description, $enabled, $_app;

    function __construct() {
      global $order;

      if (!Registry::exists('PayPal')) {
        Registry::set('PayPal', new PayPalApp());
      }

      $this->_app = Registry::get('PayPal');
      $this->_app->loadLanguageFile('modules/DP/DP.php');

      $this->signature = 'paypal|paypal_pro_dp|' . $this->_app->getVersion() . '|2.4';
      $this->api_version = $this->_app->getApiVersion();

      $this->code = 'DP';
      $this->title = $this->_app->getDef('module_dp_title');
      $this->public_title = $this->_app->getDef('module_dp_public_title');
      $this->description = '<div align="center">' . $this->_app->drawButton($this->_app->getDef('module_dp_legacy_admin_app_button'), $this->_app->link('Configure&module=DP'), 'primary', null, true) . '</div>';
      $this->sort_order = defined('OSCOM_APP_PAYPAL_DP_SORT_ORDER') ? OSCOM_APP_PAYPAL_DP_SORT_ORDER : 0;
      $this->enabled = defined('OSCOM_APP_PAYPAL_DP_STATUS') && in_array(OSCOM_APP_PAYPAL_DP_STATUS, array('1', '0')) ? true : false;
      $this->order_status = defined('OSCOM_APP_PAYPAL_DP_ORDER_STATUS_ID') && ((int)OSCOM_APP_PAYPAL_DP_ORDER_STATUS_ID > 0) ? (int)OSCOM_APP_PAYPAL_DP_ORDER_STATUS_ID : 0;

      if ( !defined('MODULE_PAYMENT_INSTALLED') || !tep_not_null(MODULE_PAYMENT_INSTALLED) || !in_array('PayPal\EC', explode(';', MODULE_PAYMENT_INSTALLED)) || !defined('OSCOM_APP_PAYPAL_EC_STATUS') || !in_array(OSCOM_APP_PAYPAL_EC_STATUS, array('1', '0')) ) {
        $this->description .= '<div class="secWarning">' . $this->_app->getDef('module_dp_error_express_module') . '</div>';

        $this->enabled = false;
      }

      if ( defined('OSCOM_APP_PAYPAL_DP_STATUS') ) {
        if ( OSCOM_APP_PAYPAL_DP_STATUS == '0' ) {
          $this->title .= ' [Sandbox]';
          $this->public_title .= ' (' . $this->_app->vendor . '\\' . $this->_app->code . '\\' . $this->code . '; Sandbox)';
        }
      }

      if ( !function_exists('curl_init') ) {
        $this->description .= '<div class="secWarning">' . $this->_app->getDef('module_dp_error_curl') . '</div>';

        $this->enabled = false;
      }

      if ( $this->enabled === true ) {
        if ( OSCOM_APP_PAYPAL_GATEWAY == '1' ) { // PayPal
          if ( !$this->_app->hasCredentials('DP') ) {
            $this->description .= '<div class="secWarning">' . $this->_app->getDef('module_dp_error_credentials') . '</div>';

            $this->enabled = false;
          }
        } else { // Payflow
          if ( !$this->_app->hasCredentials('DP', 'payflow') ) {
            $this->description .= '<div class="secWarning">' . $this->_app->getDef('module_dp_error_credentials_payflow') . '</div>';

            $this->enabled = false;
          }
        }
      }

      if ( $this->enabled === true ) {
        if ( isset($order) && is_object($order) ) {
          $this->update_status();
        }
      }

      $this->cc_types = array('VISA' => 'Visa',
                              'MASTERCARD' => 'MasterCard',
                              'DISCOVER' => 'Discover Card',
                              'AMEX' => 'American Express',
                              'MAESTRO' => 'Maestro');
    }

    function update_status() {
      global $order;

      $OSCOM_Db = Registry::get('Db');

      if ( ($this->enabled == true) && ((int)OSCOM_APP_PAYPAL_DP_ZONE > 0) ) {
        $check_flag = false;

        $Qcheck = $OSCOM_Db->get('zones_to_geo_zones', 'zone_id', ['geo_zone_id' => OSCOM_APP_PAYPAL_DP_ZONE, 'zone_country_id' => $order->delivery['country']['id']], 'zone_id');

        while ($Qcheck->fetch()) {
          if (($Qcheck->valueInt('zone_id') < 1) || ($Qcheck->valueInt('zone_id') == $order->delivery['zone_id'])) {
            $check_flag = true;
            break;
          }
        }

        if ($check_flag == false) {
          $this->enabled = false;
        }
      }
    }

    function javascript_validation() {
      return false;
    }

    function selection() {
      return array('id' => $this->_app->vendor . '\\' . $this->_app->code . '\\' . $this->code,
                   'module' => $this->public_title);
    }

    function pre_confirmation_check() {
      if ( $this->templateClassExists() ) {
        $GLOBALS['oscTemplate']->addBlock($this->getSubmitCardDetailsJavascript(), 'header_tags');
      }
    }

    function confirmation() {
      global $order;

      $types_array = array();
      foreach ( $this->cc_types as $key => $value ) {
        if ($this->isCardAccepted($key)) {
          $types_array[] = array('id' => $key,
                                 'text' => $value);
        }
      }

      $today = getdate();

      $months_array = array();
      for ($i=1; $i<13; $i++) {
        $months_array[] = array('id' => sprintf('%02d', $i), 'text' => sprintf('%02d', $i));
      }

      $year_valid_from_array = array();
      for ($i=$today['year']-10; $i < $today['year']+1; $i++) {
        $year_valid_from_array[] = array('id' => strftime('%Y',mktime(0,0,0,1,1,$i)), 'text' => strftime('%Y',mktime(0,0,0,1,1,$i)));
      }

      $year_expires_array = array();
      for ($i=$today['year']; $i < $today['year']+10; $i++) {
        $year_expires_array[] = array('id' => strftime('%Y',mktime(0,0,0,1,1,$i)), 'text' => strftime('%Y',mktime(0,0,0,1,1,$i)));
      }

      $content = '<table id="paypal_table_new_card" border="0" width="100%" cellspacing="0" cellpadding="2">' .
                 '  <tr>' .
                 '    <td width="30%">' . $this->_app->getDef('module_dp_field_card_type') . '</td>' .
                 '    <td>' . HTML::selectField('cc_type', $types_array, null, 'id="paypal_card_type"') . '</td>' .
                 '  </tr>' .
                 '  <tr>' .
                 '    <td width="30%">' . $this->_app->getDef('module_dp_field_card_owner') . '</td>' .
                 '    <td>' . HTML::inputField('cc_owner', $order->billing['firstname'] . ' ' . $order->billing['lastname']) . '</td>' .
                 '  </tr>' .
                 '  <tr>' .
                 '    <td width="30%">' . $this->_app->getDef('module_dp_field_card_number') . '</td>' .
                 '    <td>' . HTML::inputField('cc_number_nh-dns', '', 'id="paypal_card_num"') . '</td>' .
                 '  </tr>' .
                 '  <tr>' .
                 '    <td width="30%">' . $this->_app->getDef('module_dp_field_card_expires') . '</td>' .
                 '    <td>' . HTML::selectField('cc_expires_month', $months_array) . '&nbsp;' . HTML::selectField('cc_expires_year', $year_expires_array) . '</td>' .
                 '  </tr>' .
                 '  <tr>' .
                 '    <td width="30%">' . $this->_app->getDef('module_dp_field_card_cvc') . '</td>' .
                 '    <td>' . HTML::inputField('cc_cvc_nh-dns', '', 'size="5" maxlength="4"') . ' <span id="cardSecurityCodeInfo" title="' . HTML::output($this->_app->getDef('module_dp_field_card_cvc_info')) . '" style="color: #084482; text-decoration: none; border-bottom: 1px dashed #084482; cursor: pointer;">' . $this->_app->getDef('module_dp_field_card_cvc_info_link') . '</span></td>' .
                 '  </tr>';

      if ( $this->isCardAccepted('MAESTRO') ) {
        $content .= '  <tr>' .
                    '    <td width="30%">' . $this->_app->getDef('module_dp_field_card_valid_from') . '</td>' .
                    '    <td>' . HTML::selectField('cc_starts_month', $months_array, null, 'id="paypal_card_date_start"') . '&nbsp;' . HTML::selectField('cc_starts_year', $year_valid_from_array) . '&nbsp;' . $this->_app->getDef('module_dp_field_card_valid_from_info') . '</td>' .
                    '  </tr>' .
                    '  <tr>' .
                    '    <td width="30%">' . $this->_app->getDef('module_dp_field_card_issue_number') . '</td>' .
                    '    <td>' . HTML::inputField('cc_issue_nh-dns', '', 'id="paypal_card_issue" size="3" maxlength="2"') . '&nbsp;' . $this->_app->getDef('module_dp_field_card_issue_number_info') . '</td>' .
                    '  </tr>';
      }

      $content .= '</table>';

      if ( !$this->templateClassExists() ) {
        $content .= $this->getSubmitCardDetailsJavascript();
      }

      $confirmation = array('title' => $content);

      return $confirmation;
    }

    function process_button() {
      return false;
    }

    function before_process() {
      if ( OSCOM_APP_PAYPAL_GATEWAY == '1' ) {
        $this->before_process_paypal();
      } else {
        $this->before_process_payflow();
      }
    }

    function before_process_paypal() {
      global $order, $order_totals, $response_array;

      if ( isset($_POST['cc_owner']) && !empty($_POST['cc_owner']) && isset($_POST['cc_type']) && $this->isCardAccepted($_POST['cc_type']) && isset($_POST['cc_number_nh-dns']) && !empty($_POST['cc_number_nh-dns']) ) {
        $params = array('AMT' => $this->_app->formatCurrencyRaw($order->info['total']),
                        'CREDITCARDTYPE' => $_POST['cc_type'],
                        'ACCT' => $_POST['cc_number_nh-dns'],
                        'EXPDATE' => $_POST['cc_expires_month'] . $_POST['cc_expires_year'],
                        'CVV2' => $_POST['cc_cvc_nh-dns'],
                        'FIRSTNAME' => substr($_POST['cc_owner'], 0, strpos($_POST['cc_owner'], ' ')),
                        'LASTNAME' => substr($_POST['cc_owner'], strpos($_POST['cc_owner'], ' ')+1),
                        'STREET' => $order->billing['street_address'],
                        'CITY' => $order->billing['city'],
                        'STATE' => tep_get_zone_code($order->billing['country']['id'], $order->billing['zone_id'], $order->billing['state']),
                        'COUNTRYCODE' => $order->billing['country']['iso_code_2'],
                        'ZIP' => $order->billing['postcode'],
                        'EMAIL' => $order->customer['email_address'],
                        'SHIPTOPHONENUM' => $order->customer['telephone'],
                        'CURRENCYCODE' => $order->info['currency']);

        if ( $_POST['cc_type'] == 'MAESTRO' ) {
          $params['STARTDATE'] = $_POST['cc_starts_month'] . $_POST['cc_starts_year'];
          $params['ISSUENUMBER'] = $_POST['cc_issue_nh-dns'];
        }

        if ( is_numeric($_SESSION['sendto']) && ($_SESSION['sendto'] > 0) ) {
          $params['SHIPTONAME'] = $order->delivery['firstname'] . ' ' . $order->delivery['lastname'];
          $params['SHIPTOSTREET'] = $order->delivery['street_address'];
          $params['SHIPTOCITY'] = $order->delivery['city'];
          $params['SHIPTOSTATE'] = tep_get_zone_code($order->delivery['country']['id'], $order->delivery['zone_id'], $order->delivery['state']);
          $params['SHIPTOCOUNTRYCODE'] = $order->delivery['country']['iso_code_2'];
          $params['SHIPTOZIP'] = $order->delivery['postcode'];
        }

        $item_params = array();

        $line_item_no = 0;

        foreach ( $order->products as $product ) {
          $item_params['L_NAME' . $line_item_no] = $product['name'];
          $item_params['L_AMT' . $line_item_no] = $this->_app->formatCurrencyRaw($product['final_price']);
          $item_params['L_NUMBER' . $line_item_no] = $product['id'];
          $item_params['L_QTY' . $line_item_no] = $product['qty'];

          $line_item_no++;
        }

        $items_total = $this->_app->formatCurrencyRaw($order->info['subtotal']);

        foreach ( $order_totals as $ot ) {
          if ( !in_array($ot['code'], array('ot_subtotal', 'ot_shipping', 'ot_tax', 'ot_total')) ) {
            $item_params['L_NAME' . $line_item_no] = $ot['title'];
            $item_params['L_AMT' . $line_item_no] = $this->_app->formatCurrencyRaw($ot['value']);

            $items_total += $this->_app->formatCurrencyRaw($ot['value']);

            $line_item_no++;
          }
        }

        $item_params['ITEMAMT'] = $items_total;
        $item_params['TAXAMT'] = $this->_app->formatCurrencyRaw($order->info['tax']);
        $item_params['SHIPPINGAMT'] = $this->_app->formatCurrencyRaw($order->info['shipping_cost']);

        if ( $this->_app->formatCurrencyRaw($item_params['ITEMAMT'] + $item_params['TAXAMT'] + $item_params['SHIPPINGAMT']) == $params['AMT'] ) {
          $params = array_merge($params, $item_params);
        }

        $response_array = $this->_app->getApiResult('DP', 'DoDirectPayment', $params);

        if ( !in_array($response_array['ACK'], array('Success', 'SuccessWithWarning')) ) {
          OSCOM::redirect('shopping_cart.php', 'error_message=' . stripslashes($response_array['L_LONGMESSAGE0']), 'SSL');
        }
      } else {
        OSCOM::redirect('checkout_confirmation.php', 'error_message=' . $this->_app->getDef('module_dp_error_all_fields_required'), 'SSL');
      }
    }

    function before_process_payflow() {
      global $order, $order_totals, $response_array;

      if ( isset($_POST['cc_owner']) && !empty($_POST['cc_owner']) && isset($_POST['cc_type']) && $this->isCardAccepted($_POST['cc_type']) && isset($_POST['cc_number_nh-dns']) && !empty($_POST['cc_number_nh-dns']) ) {
        $params = array('AMT' => $this->_app->formatCurrencyRaw($order->info['total']),
                        'CURRENCY' => $order->info['currency'],
                        'BILLTOFIRSTNAME' => substr($_POST['cc_owner'], 0, strpos($_POST['cc_owner'], ' ')),
                        'BILLTOLASTNAME' => substr($_POST['cc_owner'], strpos($_POST['cc_owner'], ' ')+1),
                        'BILLTOSTREET' => $order->billing['street_address'],
                        'BILLTOCITY' => $order->billing['city'],
                        'BILLTOSTATE' => tep_get_zone_code($order->billing['country']['id'], $order->billing['zone_id'], $order->billing['state']),
                        'BILLTOCOUNTRY' => $order->billing['country']['iso_code_2'],
                        'BILLTOZIP' => $order->billing['postcode'],
                        'EMAIL' => $order->customer['email_address'],
                        'ACCT' => $_POST['cc_number_nh-dns'],
                        'EXPDATE' => $_POST['cc_expires_month'] . $_POST['cc_expires_year'],
                        'CVV2' => $_POST['cc_cvc_nh-dns']);

        if ( is_numeric($_SESSION['sendto']) && ($_SESSION['sendto'] > 0) ) {
          $params['SHIPTOFIRSTNAME'] = $order->delivery['firstname'];
          $params['SHIPTOLASTNAME'] = $order->delivery['lastname'];
          $params['SHIPTOSTREET'] = $order->delivery['street_address'];
          $params['SHIPTOCITY'] = $order->delivery['city'];
          $params['SHIPTOSTATE'] = tep_get_zone_code($order->delivery['country']['id'], $order->delivery['zone_id'], $order->delivery['state']);
          $params['SHIPTOCOUNTRY'] = $order->delivery['country']['iso_code_2'];
          $params['SHIPTOZIP'] = $order->delivery['postcode'];
        }

        $item_params = array();

        $line_item_no = 0;

        foreach ($order->products as $product) {
          $item_params['L_NAME' . $line_item_no] = $product['name'];
          $item_params['L_COST' . $line_item_no] = $this->_app->formatCurrencyRaw($product['final_price']);
          $item_params['L_QTY' . $line_item_no] = $product['qty'];

          $line_item_no++;
        }

        $items_total = $this->_app->formatCurrencyRaw($order->info['subtotal']);

        foreach ($order_totals as $ot) {
          if ( !in_array($ot['code'], array('ot_subtotal', 'ot_shipping', 'ot_tax', 'ot_total')) ) {
            $item_params['L_NAME' . $line_item_no] = $ot['title'];
            $item_params['L_COST' . $line_item_no] = $this->_app->formatCurrencyRaw($ot['value']);
            $item_params['L_QTY' . $line_item_no] = 1;

            $items_total += $this->_app->formatCurrencyRaw($ot['value']);

            $line_item_no++;
          }
        }

        $item_params['ITEMAMT'] = $items_total;
        $item_params['TAXAMT'] = $this->_app->formatCurrencyRaw($order->info['tax']);
        $item_params['FREIGHTAMT'] = $this->_app->formatCurrencyRaw($order->info['shipping_cost']);

        if ( $this->_app->formatCurrencyRaw($item_params['ITEMAMT'] + $item_params['TAXAMT'] + $item_params['FREIGHTAMT']) == $params['AMT'] ) {
          $params = array_merge($params, $item_params);
        }

        $params['_headers'] = array('X-VPS-REQUEST-ID: ' . md5($_SESSION['cartID'] . session_id() . $this->_app->formatCurrencyRaw($order->info['total'])),
                                    'X-VPS-CLIENT-TIMEOUT: 45',
                                    'X-VPS-VIT-INTEGRATION-PRODUCT: OSCOM',
                                    'X-VPS-VIT-INTEGRATION-VERSION: 2.3');

        $response_array = $this->_app->getApiResult('DP', 'PayflowPayment', $params);

        if ( $response_array['RESULT'] != '0' ) {
          switch ( $response_array['RESULT'] ) {
            case '1':
            case '26':
              $error_message = $this->_app->getDef('module_dp_error_configuration');
              break;

            case '7':
              $error_message = $this->_app->getDef('module_dp_error_address');
              break;

            case '12':
              $error_message = $this->_app->getDef('module_dp_error_declined');
              break;

            case '23':
            case '24':
              $error_message = $this->_app->getDef('module_dp_error_invalid_card');
              break;

            default:
              $error_message = $this->_app->getDef('module_dp_error_general');
              break;
          }

          OSCOM::redirect('checkout_confirmation.php', 'error_message=' . $error_message, 'SSL');
        }
      } else {
        OSCOM::redirect('checkout_confirmation.php', 'error_message=' . $this->_app->getDef('module_dp_error_all_fields_required'), 'SSL');
      }
    }

    function after_process() {
      if ( OSCOM_APP_PAYPAL_GATEWAY == '1' ) {
        $this->after_process_paypal();
      } else {
        $this->after_process_payflow();
      }
    }

    function after_process_paypal() {
      global $response_array, $insert_id;

      $OSCOM_Db = Registry::get('Db');

      $details = $this->_app->getApiResult('APP', 'GetTransactionDetails', array('TRANSACTIONID' => $response_array['TRANSACTIONID']), (OSCOM_APP_PAYPAL_DP_STATUS == '1') ? 'live' : 'sandbox');

      $result = 'Transaction ID: ' . HTML::outputProtected($response_array['TRANSACTIONID']) . "\n";

      if ( in_array($details['ACK'], array('Success', 'SuccessWithWarning')) ) {
        $result .= 'Payer Status: ' . HTML::outputProtected($details['PAYERSTATUS']) . "\n" .
                   'Address Status: ' . HTML::outputProtected($details['ADDRESSSTATUS']) . "\n" .
                   'Payment Status: ' . HTML::outputProtected($details['PAYMENTSTATUS']) . "\n" .
                   'Payment Type: ' . HTML::outputProtected($details['PAYMENTTYPE']) . "\n" .
                   'Pending Reason: ' . HTML::outputProtected($details['PENDINGREASON']) . "\n";
      }

      $result .= 'AVS Code: ' . HTML::outputProtected($response_array['AVSCODE']) . "\n" .
                 'CVV2 Match: ' . HTML::outputProtected($response_array['CVV2MATCH']);

      $sql_data_array = array('orders_id' => $insert_id,
                              'orders_status_id' => OSCOM_APP_PAYPAL_TRANSACTIONS_ORDER_STATUS_ID,
                              'date_added' => 'now()',
                              'customer_notified' => '0',
                              'comments' => $result);

      $OSCOM_Db->save('orders_status_history', $sql_data_array);
    }

    function after_process_payflow() {
      global $insert_id, $response_array;

      $OSCOM_Db = Registry::get('Db');

      $details = $this->_app->getApiResult('APP', 'PayflowInquiry', array('ORIGID' => $response_array['PNREF']), (OSCOM_APP_PAYPAL_DP_STATUS == '1') ? 'live' : 'sandbox');

      $result = 'Transaction ID: ' . HTML::outputProtected($response_array['PNREF']) . "\n" .
                'Gateway: Payflow' . "\n" .
                'PayPal ID: ' . HTML::outputProtected($response_array['PPREF']) . "\n" .
                'Response: ' . HTML::outputProtected($response_array['RESPMSG']) . "\n";

      if ( isset($details['RESULT']) && ($details['RESULT'] == '0') ) {
        $pending_reason = $details['TRANSSTATE'];
        $payment_status = null;

        switch ( $details['TRANSSTATE'] ) {
          case '3':
            $pending_reason = 'authorization';
            $payment_status = 'Pending';
            break;

          case '4':
            $pending_reason = 'other';
            $payment_status = 'In-Progress';
            break;

          case '6':
            $pending_reason = 'scheduled';
            $payment_status = 'Pending';
            break;

          case '8':
          case '9':
            $pending_reason = 'None';
            $payment_status = 'Completed';
            break;
        }

        if ( isset($payment_status) ) {
          $result .= 'Payment Status: ' . HTML::outputProtected($payment_status) . "\n";
        }

        $result .= 'Pending Reason: ' . HTML::outputProtected($pending_reason) . "\n";
      }

      switch ( $response_array['AVSADDR'] ) {
        case 'Y':
          $result .= 'AVS Address: Match' . "\n";
          break;

        case 'N':
          $result .= 'AVS Address: No Match' . "\n";
          break;
      }

      switch ( $response_array['AVSZIP'] ) {
        case 'Y':
          $result .= 'AVS ZIP: Match' . "\n";
          break;

        case 'N':
          $result .= 'AVS ZIP: No Match' . "\n";
          break;
      }

      switch ( $response_array['IAVS'] ) {
        case 'Y':
          $result .= 'IAVS: International' . "\n";
          break;

        case 'N':
          $result .= 'IAVS: USA' . "\n";
          break;
      }

      switch ( $response_array['CVV2MATCH'] ) {
        case 'Y':
          $result .= 'CVV2: Match' . "\n";
          break;

        case 'N':
          $result .= 'CVV2: No Match' . "\n";
          break;
      }

      $sql_data_array = array('orders_id' => $insert_id,
                              'orders_status_id' => OSCOM_APP_PAYPAL_TRANSACTIONS_ORDER_STATUS_ID,
                              'date_added' => 'now()',
                              'customer_notified' => '0',
                              'comments' => $result);

      $OSCOM_Db->save('orders_status_history', $sql_data_array);
    }

    function get_error() {
      return false;
    }

    function check() {
      return defined('OSCOM_APP_PAYPAL_DP_STATUS') && (trim(OSCOM_APP_PAYPAL_DP_STATUS) != '');
    }

    function install() {
      $this->_app->redirect('Configure&Install&module=DP');
    }

    function remove() {
      $this->_app->redirect('Configure&Uninstall&module=DP');
    }

    function keys() {
      return array('OSCOM_APP_PAYPAL_DP_SORT_ORDER');
    }

    function isCardAccepted($card) {
      static $cards;

      if ( !isset($cards) ) {
        $cards = explode(';', OSCOM_APP_PAYPAL_DP_CARDS);
      }

      return isset($this->cc_types[$card]) && in_array(strtolower($card), $cards);
    }

    function templateClassExists() {
      return class_exists('oscTemplate') && isset($GLOBALS['oscTemplate']) && is_object($GLOBALS['oscTemplate']) && (get_class($GLOBALS['oscTemplate']) == 'oscTemplate');
    }

    function getSubmitCardDetailsJavascript() {
      $js = <<<EOD
<script>
if ( typeof jQuery == 'undefined' ) {
  document.write('<scr' + 'ipt src="https://ajax.googleapis.com/ajax/libs/jquery/1.11.1/jquery.min.js"></scr' + 'ipt>');
}
</script>

<script>
$(function() {
  if ( typeof($('#paypal_table_new_card').parent().closest('table').attr('width')) == 'undefined' ) {
    $('#paypal_table_new_card').parent().closest('table').attr('width', '100%');
  }

  paypalShowNewCardFields();

  $('#paypal_card_type').change(function() {
    var selected = $(this).val();

    if ( $('#paypal_card_date_start').length > 0 ) {
      if ( selected == 'MAESTRO' ) {
        $('#paypal_card_date_start').parent().parent().show();
      } else {
        $('#paypal_card_date_start').parent().parent().hide();
      }
    }

    if ( $('#paypal_card_issue').length > 0 ) {
      if ( selected == 'MAESTRO' ) {
        $('#paypal_card_issue').parent().parent().show();
      } else {
        $('#paypal_card_issue').parent().parent().hide();
      }
    }
  });

  $('#cardSecurityCodeInfo').tooltip();
});

function paypalShowNewCardFields() {
  var selected = $('#paypal_card_type').val();

  if ( $('#paypal_card_date_start').length > 0 ) {
    if ( selected != 'MAESTRO' ) {
      $('#paypal_card_date_start').parent().parent().hide();
    }
  }

  if ( $('#paypal_card_issue').length > 0 ) {
    if ( selected != 'MAESTRO' ) {
      $('#paypal_card_issue').parent().parent().hide();
    }
  }
}
</script>
EOD;

      return $js;
    }
  }
?>
