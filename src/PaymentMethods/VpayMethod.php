<?php declare(strict_types=1);

namespace Sisow\Payment\PaymentMethods;

use Sisow\Payment\PaymentHandlers\VpayHandler;

class VpayMethod extends PaymentMethodBase implements PaymentMethodInterface
{
    public function getPaymentCode(): string
    {
        return 'vpay';
    }

    public function getName(): string
    {
        return 'V PAY';
    }

    public function getPaymentHandler(): string
    {
        return VpayHandler::class;
    }
}