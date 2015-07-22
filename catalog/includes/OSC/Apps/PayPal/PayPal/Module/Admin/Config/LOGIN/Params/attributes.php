<?php
/**
  * osCommerce Online Merchant
  *
  * @copyright Copyright (c) 2015 osCommerce; http://www.oscommerce.com
  * @license GPL; http://www.oscommerce.com/gpllicense.txt
  */

namespace OSC\Apps\PayPal\PayPal\Module\Admin\Config\LOGIN\Params;

class attributes extends \OSC\Apps\PayPal\PayPal\Module\Admin\Config\ParamsAbstract
{
    public $sort_order = 700;

    protected $attributes = [
        'personal' => [
            'full_name' => 'profile',
            'date_of_birth' => 'profile',
            'age_range' => 'https://uri.paypal.com/services/paypalattributes',
            'gender' => 'profile'
        ],
        'address' => [
            'email_address' => 'email',
            'street_address' => 'address',
            'city' => 'address',
            'state' => 'address',
            'country' => 'address',
            'zip_code' => 'address',
            'phone' => 'phone'
        ],
        'account' => [
            'account_status' => 'https://uri.paypal.com/services/paypalattributes',
            'account_type' => 'https://uri.paypal.com/services/paypalattributes',
            'account_creation_date' => 'https://uri.paypal.com/services/paypalattributes',
            'time_zone' => 'profile',
            'locale' => 'profile',
            'language' => 'profile'
        ],
        'checkout' => [
            'seamless_checkout' => 'https://uri.paypal.com/services/expresscheckout'
        ]
    ];

    protected $required = [
        'full_name',
        'email_address',
        'street_address',
        'city',
        'state',
        'country',
        'zip_code'
    ];

    protected function init()
    {
        $this->default = implode(';', $this->getAttributes());

        $this->title = $this->app->getDef('cfg_login_attributes_title');
        $this->description = $this->app->getDef('cfg_login_attributes_desc');
    }

    public function getSetField()
    {
        $values_array = explode(';', OSCOM_APP_PAYPAL_LOGIN_ATTRIBUTES);

        $input = '';

        foreach ($this->attributes as $group => $attributes) {
            $input .= '<strong>' . $this->app->getDef('cfg_login_attributes_group_' . $group) . '</strong><br />';

            foreach ($attributes as $attribute => $scope) {
                if (in_array($attribute, $this->required)) {
                    $input .= '<input type="radio" id="ppLogInAttributesSelection' . ucfirst($attribute) . '" name="ppLogInAttributesTmp' . ucfirst($attribute) . '" value="' . $attribute . '" checked="checked" />';
                } else {
                    $input .= '<input type="checkbox" id="ppLogInAttributesSelection' . ucfirst($attribute) . '" name="ppLogInAttributes[]" value="' . $attribute . '"' . (in_array($attribute, $values_array) ? ' checked="checked"' : '') . ' />';
                }

                $input .= '&nbsp;<label for="ppLogInAttributesSelection' . ucfirst($attribute) . '">' . $this->app->getDef('cfg_login_attributes_attribute_' . $attribute) . '</label><br />';
            }
        }

        if (!empty($input)) {
            $input = '<br />' . substr($input, 0, -6);
        }

        $input .= '<input type="hidden" name="attributes" value="" />';

        $result = <<<EOT
<div>
  <p>
    <label>{$this->title}</label>

    {$this->description}
  </p>

  <div id="attributesSelection">
    {$input}
  </div>
</div>

<script>
function ppLogInAttributesUpdateCfgValue() {
  var pp_login_attributes_selected = '';

  if ( $('input[name^="ppLogInAttributesTmp"]').length > 0 ) {
    $('input[name^="ppLogInAttributesTmp"]').each(function() {
      pp_login_attributes_selected += $(this).attr('value') + ';';
    });
  }

  if ( $('input[name="ppLogInAttributes[]"]').length > 0 ) {
    $('input[name="ppLogInAttributes[]"]:checked').each(function() {
      pp_login_attributes_selected += $(this).attr('value') + ';';
    });
  }

  if ( pp_login_attributes_selected.length > 0 ) {
    pp_login_attributes_selected = pp_login_attributes_selected.substring(0, pp_login_attributes_selected.length - 1);
  }

  $('input[name="attributes"]').val(pp_login_attributes_selected);
}

$(function() {
  ppLogInAttributesUpdateCfgValue();

  if ( $('input[name="ppLogInAttributes[]"]').length > 0 ) {
    $('input[name="ppLogInAttributes[]"]').change(function() {
      ppLogInAttributesUpdateCfgValue();
    });
  }
});
</script>
EOT;

        return $result;
    }

    protected function getAttributes()
    {
        $data = [];

        foreach ($this->attributes as $group => $attributes) {
            foreach ($attributes as $attribute => $scope) {
                $data[] = $attribute;
            }
        }

        return $data;
    }
}
