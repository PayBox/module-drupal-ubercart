<?php
function uc_platron_success() {
  $order_id = (int)$_REQUEST['pg_order_id'];

  if (intval($_SESSION['cart_order']) != $order_id) {
    drupal_set_message(t('Thank you for your order! Platron will notify us once your payment has been processed.'));
    drupal_goto('cart');
  }

  $order = uc_order_load($order_id);
  if ($order->payment_method != 'platron') {
    drupal_goto('cart');
  }

  $_SESSION['uc_checkout'][$_SESSION['cart_order']]['do_complete'] = TRUE;
  drupal_goto('cart/checkout/complete');
}

function uc_platron_failure() {
  unset($_SESSION['cart_order']);

  drupal_set_message(t('Your Platron payment was cancelled. Please feel free to continue shopping or contact us for assistance.'));

  drupal_goto(variable_get('uc_platron_cancel_return_url', 'cart'));
}

function uc_platron_done_payment() {
  module_load_include('class', 'uc_platron', 'PG_Signature');

  $params = [];
  foreach($_REQUEST as $key=>$value) {
    if( strpos($key, 'pg_')===0 ) $params[$key] = $value;
  }
  
  $uri = request_uri();
  $uri = substr($uri, 0, strpos($uri, '?'));
  $uri = substr($uri, strrpos($uri,'/')+1);
  $thisScriptName = (string)$uri;
  
  $state = uc_platron_check($params, $thisScriptName);
  
  $xml = new SimpleXMLElement('<?xml version="1.0" encoding="utf-8"?><response/>');
  $xml->addChild('pg_salt', $params['pg_salt']);
  $xml->addChild('pg_status', $state['status']);
  if(isset($state['description'])) $xml->addChild('pg_description', $state['description']);
  if(!empty($state['error'])) {
    $xml->addChild('pg_timeout', 60*60*24*3);
    $xml->addChild('pg_error_description', $state['error']);
  }
  $xml->addChild('pg_sig', PG_Signature::makeXML($thisScriptName, $xml, variable_get('uc_platron_msec', '')));
  
  header('Content-type: text/xml');
  echo $xml->asXML();
  exit;
}
 
function uc_platron_check($params, $thisScriptName) {
  module_load_include('class', 'uc_platron', 'PG_Signature');

  $state = array(
    'error' => '',
    'status' => '',
  );
  
  if (!PG_Signature::check($params['pg_sig'], $thisScriptName, $params, variable_get('uc_platron_msec', ''))) {
    $state['error'] = t('Security check failed');
    $state['status'] = 'error';
    return $state;
  }

  $order_id = (int)$params['pg_order_id'];
  if ($order_id==0) {
    $state['error'] = t('Order id cannot be 0');
    $state['status'] = 'rejected';
    return $state;   
  }
  $order = uc_order_load($order_id);
  
  $amount = $params['pg_amount'];
  if ($order->order_total < $amount) {
    $state['error'] = t('This number @out_summa does not match the original price @in_summa.', array('@out_summa' => $amount, '@in_summa' => $order->order_total));
    $state['status'] = 'rejected';
    return $state;   
  }
  
  if( isset($params['pg_result']) ) {
    if($params['pg_result']==1) {
      uc_order_update_status($order_id, variable_get('uc_platron_payment_received_status', 'payment_received'));
      
      $state['description'] = t('A payment was accepted.');
      $state['status'] = 'ok';
      return $state; 
    } else {
      uc_order_update_status($order_id, variable_get('uc_platron_canceled_status', 'canceled'));
      
      $state['description'] = t('Your Platron payment was cancelled. Please feel free to continue shopping or contact us for assistance.');
      $state['status'] = 'ok';
      return $state; 
    }
  } else {
    if(uc_order_status_data($order->order_status, 'id')==variable_get('uc_platron_ready_for_pay_status','') || variable_get('uc_platron_ready_for_pay_status', '')=='') {
      $state['status'] = 'ok';
    } else {
      uc_order_update_status($order_id, variable_get('uc_platron_platron_inited_status', 'platron_inited'));
      $state['status'] = 'waiting_for_check';
    }
    return $state;
  }
}