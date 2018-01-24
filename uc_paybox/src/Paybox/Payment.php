<?php
namespace Drupal\uc_paybox\Paybox;

final class Payment extends Abstractions\DataContainer implements Interfaces\Payment {

    public $isPostponePayment;
    public $isRecurringStart;
    public $recurringLifetime;

    public function init($paybox) {
        foreach(get_object_vars($paybox) as $key => $value) {
            if(is_object($value)) {
                $this->buildRequest($value);
            }
        }
        $this->signRequest($paybox->merchant->secretKey, 'payment.php');
        return $this->run('payment.php');
    }

    public function getPaymentStatus(int $paymentId) {

    }

    public function waiting(int $timeout) {
        return $this->buildResponse([
                'pg_status' => 'ok',
                'pg_timeout' => $timeout
            ],
            $this->secretKey);
    }

    public function reject($description) {
        return $this->buildResponse([
                'pg_status' => 'rejected',
                'pg_description' => $description
            ],
            $this->secretKey);
    }

    public function error($code, $descr) {
        return $this->buildResponse([
                'pg_status' => 'error',
                'pg_error_code' => $code,
                'pg_error_description' => $descr
            ],
            $this->secretKey);
    }

    public function accept($descr) {
        return $this->buildResponse([
                'pg_status' => 'ok',
                'pg_description' => $descr
            ],
            $this->secretKey);
    }

}
