<?php
/*
  $Id$

  osCommerce, Open Source E-Commerce Solutions
  http://www.oscommerce.com

  Copyright (c) 2014 osCommerce

  Released under the GNU General Public License
*/

  class OSCOM_PayPal_EC_Cfg_sort_order {
    var $default = '0';
    var $title = 'Sort Order';
    var $description = 'The sort order location of the module shown in the available payment methods listing (lowest is displayed first).';

    function getSetField() {
      $input = tep_draw_input_field('sort_order', OSCOM_APP_PAYPAL_EC_SORT_ORDER, 'id="inputEcSortOrder"');

      $result = <<<EOT
<div>
  <p>
    <label for="inputEcSortOrder">{$this->title}</label>

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