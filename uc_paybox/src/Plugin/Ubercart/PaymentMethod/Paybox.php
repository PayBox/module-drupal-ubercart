<?php
namespace Drupal\uc_paybox\Plugin\Ubercart\PaymentMethod;

use Drupal\Component\Utility\Unicode;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;
use Drupal\uc_order\Entity\Order;
use Drupal\uc_paybox\Paybox\Paybox as PayBoxFacade;
use Drupal\uc_order\OrderInterface;
use Drupal\uc_payment\ExpressPaymentMethodPluginInterface;
use Drupal\uc_payment\OffsitePaymentMethodPluginInterface;
use Drupal\uc_payment\PaymentMethodPluginBase;
use Symfony\Component\HttpFoundation\Response;
use Drupal\Core\Routing\TrustedRedirectResponse;

/**
* Defines the Paybox Checkout payment method.
*
* @UbercartPaymentMethod(
*   id = "uc_paybox",
*   name = @Translation("Paybox Checkout")
* )
*/

class Paybox extends PaymentMethodPluginBase implements ExpressPaymentMethodPluginInterface, OffsitePaymentMethodPluginInterface {
	
  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state) {
    $form['sid'] = array(
      '#type' => 'textfield',
      '#title' => $this->t('Merchant account number'),
      '#description' => $this->t('Your Paybox merchant account number.'),
      '#default_value' => $this->configuration['sid'],
      '#size' => 16,
    );
    $form['secret_word'] = array(
      '#type' => 'textfield',
      '#title' => $this->t('Secret word for order verification'),
      '#description' => $this->t('The secret word entered in your Paybox account Look and Feel settings.'),
      '#default_value' => $this->configuration['secret_word'],
      '#size' => 16,
    );
    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitConfigurationForm(array &$form, FormStateInterface $form_state) {
    $this->configuration['secret_word'] = $form_state->getValue('secret_word');
    $this->configuration['sid'] = $form_state->getValue('sid');
  }

  /**
   * {@inheritdoc}
   */
  public function buildRedirectForm(array $form, FormStateInterface $form_state, OrderInterface $order = NULL) {
  
  }
	
  public function getExpressButton($method_id) {
    $this->methodId = $method_id;
    return [
      '#type' => 'image_button',
      '#name' => 'uc_paybox',
      '#src' => 'https://paybox.money/images/site/logo.svg',
      '#title' => $this->t('Checkout with PayBox.'),
      '#submit' => ['::submitForm', [$this, 'submitExpressForm']],
    ];
  }
	
	public function submitExpressForm(array &$form, FormStateInterface $form_state) {
		global $base_url;
		
	    $items = \Drupal::service('uc_cart.manager')->get()->getContents();

        if (empty($items)) {
            drupal_set_message($this->t('You do not have any items in your shopping cart.'));
            return;
        }

        $order = Order::create([
          'uid' => \Drupal::currentUser()->id(),
          'payment_method' => $this->methodId,
        ]);
		
        $order->products = array();
        foreach ($items as $item) {
          $order->products[] = $item->toOrderProduct();
        }
		$order->setStatusId('paybox_pending');
        $order->save();
        $config = \Drupal::service('plugin.manager.uc_payment.method')->createFromOrder($order)->getConfiguration();

		$paybox = new PayBoxFacade();
		$paybox->merchant->id = $config['sid'];
        $paybox->merchant->secretKey = $config['secret_word'];
		$paybox->payment->secretKey = $config['secret_word'];
		
	    $paybox->config->currency = $order->getCurrency();
        $paybox->config->failureUrl = $base_url.'/';
    	$paybox->config->successUrl = $base_url.'/';
    	$paybox->config->checkUrl = $base_url.'/uc_paybox/check';
    	$paybox->config->resultUrl = $base_url.'/uc_paybox/result';
    	$paybox->config->refundUrl = $base_url.'/uc_paybox/refund';
    	$paybox->config->captureUrl = $base_url.'/uc_paybox/clearing';
    	$paybox->config->requestMethod = 'XML';
    	$paybox->config->successUrlMethod = 'GET';
    	$paybox->config->failureUrlMethod = 'GET';
	    $paybox->config->lifetime = 86400;
    	$paybox->config->encoding = 'UTF-8';
    	$paybox->config->language = 'RU';
		if($config->test_mode == 1) {
    	    $paybox->config->isTestingMode = true;
		}
		
		$paybox->order->id = $order->id();
    	$paybox->order->description = 'Order #'.$order->id();
    	$paybox->order->amount = $order->getTotal();
		$paybox->initPayment();
		$form_state->setResponse(new TrustedRedirectResponse($paybox->url));

	}


}