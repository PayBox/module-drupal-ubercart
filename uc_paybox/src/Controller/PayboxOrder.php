<?php
namespace Drupal\uc_paybox\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\uc_order\Entity\Order;
use Drupal\uc_paybox\Paybox\Paybox;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Drupal\uc_cart\CartManagerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

class PayboxOrder extends ControllerBase {

	protected $cartManager;

	public function __construct(CartManagerInterface $cart_manager) {
		$this->cartManager = $cart_manager;
		$this->paybox = new Paybox();
		$this->paybox->merchant->id = '9970';
        $this->paybox->merchant->secretKey = 'lanoqakyvityneve';
		$this->paybox->payment->secretKey = 'lanoqakyvityneve';
	}

	public static function create(ContainerInterface $container) {
        return new static(
          $container->get('uc_cart.manager')
        );
    }

    public function checkOrder(Request $request) {
		$request = $this->parseRequest($request);
		$order = Order::load($request['pg_order_id']);
	    $answer = (is_null($order))
			? $this->paybox->payment->reject('Order ' . $request['pg_order_id'] . ' not found.')
			: (($order->getStatusId() == 'canceled')
			      ? $this->paybox->payment->reject('Order ' . $request['pg_order_id'] . ' is canceled.')
			      : (($order->getStatusId() == 'payment_received')
					 ? $this->paybox->payment->reject('Order ' . $request['pg_order_id'] . ' already paid.')
					 : (($order->getStatusId() == 'completed')
					   ? $this->paybox->payment->reject('Order ' . $request['pg_order_id'] . ' already is completed')
					   : (($order->getStatusId() == 'paybox_pending')
						 ? $this->paybox->payment->waiting(86400)
						 : $this->paybox->payment->error(1, 'unexpected status of order')
						 )
					   )
					)
			  );
		return new Response($answer);
	}

    public function resultOrder(Request $request) {
		$request = $this->parseRequest($request);
		$order = Order::load($request['pg_order_id']);
		if($request['pg_result'] == 1) {
			if(is_null($order)) {
			    return new Response($this->paybox->payment->reject('Order ' . $request['pg_order_id'] . ' not found.'));
			} else {
			    if($order->getStatusId() == 'payment_received') {
			        return new Response($this->paybox->payment->reject('Order ' . $request['pg_order_id'] . ' already paid.'));
			    } elseif($order->getStatusId() == 'canceled') {
				    return new Response($this->paybox->payment->reject('Order ' . $request['pg_order_id'] . ' is canceled.'));
				} elseif($order->getStatusId() == 'paybox_pending') {
					$order->setStatusId('completed')->save();
					return new Response($this->paybox->payment->accept('Order ' . $request['pg_order_id'] . ' successfully paid.'));
				}
			}
		} elseif($request['pg_result'] == 0) {
			$order->setStatusId('canceled')->save();
			return new Response($this->paybox->payment->accept('OK. Order will be cancelled'));
		} else {
		    return new Response($request['pg_result']);
		}
    }

    public function refundOrder(Request $request) {
        $request = $this->parseRequest($request);
		$order = Order::load($request['pg_order_id']);
		if(is_null($order)) {
		    return new Response($this->paybox->payment->reject('Order ' . $request['pg_order_id'] . ' not found.'));
		} else {
		    $order->setStatusId('canceled')->save();
			return new Response($this->paybox->payment->accept('OK. Order will be cancelled'));
		}
    }

    public function successOrder(Request $request) {
    }

    public function failureOrder(Request $request) {
    }

    public function clearOrder(Request $request) {
    }

	private function parseRequest($request) {
	    $query = (is_null($request->request->get('pg_xml')))
			? $request->query->get('pg_xml')
			: $request->request->get('pg_xml');
		return $this->paybox->parseRequest($query);
	}
}
