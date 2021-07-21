<?php declare(strict_types=1);

namespace Sisow\Payment\PaymentMethods;

use Sisow\Payment\PaymentHandlers\OverboekingHandler;

class OverboekingMethod extends PaymentMethodBase implements PaymentMethodInterface
{
    public function getPaymentCode(): string
    {
        return 'overboeking';
    }

    public function getName(): string
    {
        return 'Overboeking';
    }

    public function getPaymentHandler(): string
    {
        return OverboekingHandler::class;
    }
}