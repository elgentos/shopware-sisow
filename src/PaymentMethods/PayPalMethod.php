<?php declare(strict_types=1);

namespace Sisow\Payment\PaymentMethods;

use Sisow\Payment\PaymentHandlers\PayPalHandler;

class PayPalMethod extends PaymentMethodBase implements PaymentMethodInterface
{
    public function getPaymentCode(): string
    {
        return 'paypalec';
    }

    public function getName(): string
    {
        return 'PayPal';
    }

    public function getPaymentHandler(): string
    {
        return PayPalHandler::class;
    }
}