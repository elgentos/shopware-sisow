<?php declare(strict_types=1);

namespace Sisow\Payment\PaymentMethods;

use Sisow\Payment\PaymentHandlers\KbcHandler;

class KbcMethod extends PaymentMethodBase implements PaymentMethodInterface
{
    public function getPaymentCode(): string
    {
        return 'kbc';
    }

    public function getName(): string
    {
        return 'KBC';
    }

    public function getPaymentHandler(): string
    {
        return KbcHandler::class;
    }
}