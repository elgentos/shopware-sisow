<?php declare(strict_types=1);

namespace Sisow\Payment\PaymentMethods;

use Sisow\Payment\PaymentHandlers\MastercardHandler;

class MastercardMethod extends PaymentMethodBase implements PaymentMethodInterface
{
    public function getPaymentCode(): string
    {
        return 'mastercard';
    }

    public function getName(): string
    {
        return 'Mastercard';
    }

    public function getPaymentHandler(): string
    {
        return MastercardHandler::class;
    }
}