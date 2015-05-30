<?php
/*
  $Id$

  osCommerce, Open Source E-Commerce Solutions
  http://www.oscommerce.com

  Copyright (c) 2014 osCommerce

  Released under the GNU General Public License
*/

  use OSC\OM\HTML;

  class OSCOM_PayPal_EC_Cfg_order_status_id {
    var $default = '0';
    var $title;
    var $description;
    var $sort_order = 800;

    function OSCOM_PayPal_EC_Cfg_order_status_id() {
      global $OSCOM_PayPal;

      $this->title = $OSCOM_PayPal->getDef('cfg_ec_order_status_id_title');
      $this->description = $OSCOM_PayPal->getDef('cfg_ec_order_status_id_desc');
    }

    function getSetField() {
      global $OSCOM_PayPal;

      $statuses_array = array(array('id' => '0', 'text' => $OSCOM_PayPal->getDef('cfg_ec_order_status_id_default')));

      $statuses_query = tep_db_query("select orders_status_id, orders_status_name from " . TABLE_ORDERS_STATUS . " where language_id = '" . (int)$_SESSION['languages_id'] . "' order by orders_status_name");
      while ($statuses = tep_db_fetch_array($statuses_query)) {
        $statuses_array[] = array('id' => $statuses['orders_status_id'],
                                  'text' => $statuses['orders_status_name']);
      }

      $input = HTML::selectField('order_status_id', $statuses_array, OSCOM_APP_PAYPAL_EC_ORDER_STATUS_ID, 'id="inputEcOrderStatusId"');

      $result = <<<EOT
<div>
  <p>
    <label for="inputEcOrderStatusId">{$this->title}</label>

    {$this->description}
  </p>

  <div>
    {$input}
  </div>
</div>
EOT;

      return $result;
    }
  }
?>
