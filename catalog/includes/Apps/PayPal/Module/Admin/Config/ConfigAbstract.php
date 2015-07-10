<?php
/**
  * osCommerce Online Merchant
  *
  * @copyright Copyright (c) 2015 osCommerce; http://www.oscommerce.com
  * @license GPL; http://www.oscommerce.com/gpllicense.txt
  */

namespace OSC\OM\Apps\PayPal\Module\Admin\Config;

use OSC\OM\OSCOM;
use OSC\OM\Registry;

abstract class ConfigAbstract
{
    protected $app;
    protected $db;

    public $code;
    public $title;
    public $short_title;
    public $introduction;
    public $req_notes = [];
    public $is_installed = false;
    public $is_uninstallable = false;
    public $is_migratable = false;
    public $sort_order = 0;

    abstract protected function init();

    final public function __construct()
    {
        $this->app = Registry::get('PayPal');
        $this->db = Registry::get('Db');

        $this->code = (new \ReflectionClass($this))->getShortName();

        $this->app->loadLanguageFile('modules/' . $this->code . '/' . $this->code . '.php');

        $this->init();
    }

    public function canMigrate()
    {
        return false;
    }

    public function install()
    {
        $cut_length = strlen('OSCOM_APP_PAYPAL_' . $this->code . '_');

        foreach ($this->getParameters() as $key) {
            $p = strtolower(substr($key, $cut_length));

            $class = 'OSC\OM\Apps\PayPal\Module\Admin\Config\\' . $this->code . '\Params\\' . $p;

            $cfg = new $class($this->code);

            $this->app->saveParameter($key, $cfg->default, isset($cfg->title) ? $cfg->title : null, isset($cfg->description) ? $cfg->description : null, isset($cfg->set_func) ? $cfg->set_func : null);
        }
    }

    public function uninstall()
    {
        $Qdelete = $this->db->prepare('delete from :table_configuration where configuration_key like :configuration_key');
        $Qdelete->bindValue(':configuration_key', 'OSCOM_APP_PAYPAL_' . $this->code . '_%');
        $Qdelete->execute();

        return $Qdelete->rowCount();
    }

    public function getParameters()
    {
        $result = [];

        $directory = OSCOM::BASE_DIR . 'apps/PayPal/Module/Admin/Config/' . $this->code . '/Params';

        if ($dir = new \DirectoryIterator($directory)) {
            foreach ($dir as $file) {
                if (!$file->isDot() && !$file->isDir() && ($file->getExtension() == 'php')) {
                    $class = 'OSC\OM\Apps\PayPal\Module\Admin\Config\\' . $this->code . '\\Params\\' . $file->getBasename('.php');

                    if (is_subclass_of($class, 'OSC\OM\Apps\PayPal\Module\Admin\Config\ParamsAbstract')) {
                        if ($this->code == 'G') {
                            $result[] = 'OSCOM_APP_PAYPAL_' . strtoupper($file->getBasename('.php'));
                        } else {
                            $result[] = 'OSCOM_APP_PAYPAL_' . $this->code . '_' . strtoupper($file->getBasename('.php'));
                        }
                    } else {
                        trigger_error('OSC\OM\Apps\PayPal\Module\Admin\Config\\ConfigAbstract::getParameters(): OSC\OM\Apps\PayPal\Module\Admin\Config\\' . $this->code . '\\Params\\' . $file->getBasename('.php') . ' is not a subclass of OSC\OM\Apps\PayPal\Module\Admin\Config\ParamsAbstract and cannot be loaded.');
                    }
                }
            }
        }

        return $result;
    }

    public function getInputParameters()
    {
        $result = [];

        if ($this->code == 'G') {
            $cut = 'OSCOM_APP_PAYPAL_';
        } else {
            $cut = 'OSCOM_APP_PAYPAL_' . $this->code . '_';
        }

        $cut_length = strlen($cut);

        foreach ($this->getParameters() as $key) {
            $p = strtolower(substr($key, $cut_length));

            $class = 'OSC\OM\Apps\PayPal\Module\Admin\Config\\' . $this->code . '\Params\\' . $p;

            $cfg = new $class($this->code);

            if (!defined($key)) {
              $this->app->saveParameter($key, $cfg->default, isset($cfg->title) ? $cfg->title : null, isset($cfg->description) ? $cfg->description : null, isset($cfg->set_func) ? $cfg->set_func : null);
            }

            if ($cfg->app_configured !== false) {
                if (is_numeric($cfg->sort_order)) {
                    $counter = (int)$cfg->sort_order;
                } else {
                    $counter = count($result);
                }

                while (true) {
                    if (isset($result[$counter])) {
                        $counter++;

                        continue;
                    }

                    $set_field = $cfg->getSetField();

                    if (!empty($set_field)) {
                        $result[$counter] = $set_field;
                    }

                    break;
                }
            }
        }

        ksort($result, SORT_NUMERIC);

        return $result;
    }
}