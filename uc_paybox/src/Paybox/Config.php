<?php
namespace Drupal\uc_paybox\Paybox;

class Config extends Abstractions\DataContainer {

    public $currency;
    public $checkUrl;
    public $resultUrl;
    public $refundUrl;
    public $captureUrl;
    public $successUrl;
    public $failureUrl;
    public $requestMethod;
    public $successUrlMethod;
    public $failureUrlMethod;
    public $paymentSystem;
    public $lifetime;
    public $encoding;
    public $language;
    public $isTestingMode;
}
