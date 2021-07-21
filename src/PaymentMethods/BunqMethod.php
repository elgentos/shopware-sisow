<?php declare(strict_types=1);

namespace Sisow\Payment\PaymentMethods;

use Sisow\Payment\PaymentHandlers\BunqHandler;

class BunqMethod extends PaymentMethodBase implements PaymentMethodInterface
{
    public function getPaymentCode(): string
    {
        return 'bunq';
    }

    public function getName(): string
    {
        return 'Bunq';
    }

    public function getPaymentHandler(): string
    {
        return BunqHandler::class;
    }
}