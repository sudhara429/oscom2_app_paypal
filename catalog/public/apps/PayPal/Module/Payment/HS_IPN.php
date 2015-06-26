<?php
/*
  $Id$

  osCommerce, Open Source E-Commerce Solutions
  http://www.oscommerce.com

  Copyright (c) 2014 osCommerce

  Released under the GNU General Public License
*/

  use OSC\OM\Apps\PayPal\Module\Payment\HS;

  chdir('../../../../../');
  require('includes/application_top.php');

  if ( !defined('OSCOM_APP_PAYPAL_HS_STATUS') || !in_array(OSCOM_APP_PAYPAL_HS_STATUS, array('1', '0')) ) {
    exit;
  }

  $result = false;

  if ( isset($_POST['txn_id']) && !empty($_POST['txn_id']) ) {
    $paypal_pro_hs = new HS();

    $result = $paypal_pro_hs->_app->getApiResult('APP', 'GetTransactionDetails', array('TRANSACTIONID' => $_POST['txn_id']), (OSCOM_APP_PAYPAL_HS_STATUS == '1') ? 'live' : 'sandbox', true);
  }

  if ( is_array($result) && isset($result['ACK']) && (($result['ACK'] == 'Success') || ($result['ACK'] == 'SuccessWithWarning')) ) {
    $_SESSION['pphs_result'] = $result;

    $paypal_pro_hs->verifyTransaction(true);
  }

  require('includes/application_bottom.php');
?>
