<?php
/*
  $Id$

  osCommerce, Open Source E-Commerce Solutions
  http://www.oscommerce.com

  Copyright (c) 2014 osCommerce

  Released under the GNU General Public License
*/

  $ppUpdateDownloadResult = array('rpcStatus' => -1);

  if ( isset($_GET['v']) && is_numeric($_GET['v']) && ($_GET['v'] > $OSCOM_PayPal->getVersion()) ) {
    if ( $OSCOM_PayPal->isWritable(DIR_FS_CATALOG . 'includes/apps/PayPal/work') ) {
      if ( !file_exists(DIR_FS_CATALOG . 'includes/apps/PayPal/work') ) {
        mkdir(DIR_FS_CATALOG . 'includes/apps/PayPal/work', 0777, true);
      }

      $filepath = DIR_FS_CATALOG . 'includes/apps/PayPal/work/update.zip';

      if ( file_exists($filepath) && is_writable($filepath) ) {
        unlink($filepath);
      }

      $ppUpdateDownloadFile = $OSCOM_PayPal->makeApiCall('http://apps.oscommerce.com/index.php?Download&paypal&app&2_300&' . str_replace('.', '_', $_GET['v']) . '&update');

      $save_result = @file_put_contents($filepath, $ppUpdateDownloadFile);

      if ( ($save_result !== false) && ($save_result > 0) ) {
        $ppUpdateDownloadResult['rpcStatus'] = 1;
      } else {
        $ppUpdateDownloadResult['error'] = $OSCOM_PayPal->getDef('error_saving_download', array('filepath' => $OSCOM_PayPal->displayPath($filepath)));
      }
    } else {
      $ppUpdateDownloadResult['error'] = $OSCOM_PayPal->getDef('error_download_directory_permissions', array('filepath' => $OSCOM_PayPal->displayPath(DIR_FS_CATALOG . 'includes/apps/PayPal/work')));
    }
  }

  echo json_encode($ppUpdateDownloadResult);

  exit;
?>