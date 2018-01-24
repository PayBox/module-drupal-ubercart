<?php
namespace Drupal\uc_paybox\Paybox\Interfaces;

interface Payment {

    public function init($paybox);
    public function getPaymentStatus(int $paymentId);
    public function reject($description);
    public function waiting(int $timeout);
    public function error($code, $description);
    public function accept($description);

}
