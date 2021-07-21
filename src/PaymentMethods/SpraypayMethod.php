<?php declare(strict_types=1);

namespace Sisow\Payment\PaymentMethods;

use Sisow\Payment\PaymentHandlers\SpraypayHandler;

class SpraypayMethod extends PaymentMethodBase implements PaymentMethodInterface
{
    public function getPaymentCode(): string
    {
        return 'spraypay';
    }

    public function getName(): string
    {
        return 'Spraypay';
    }

    public function getPaymentHandler(): string
    {
        return SpraypayHandler::class;
    }
}