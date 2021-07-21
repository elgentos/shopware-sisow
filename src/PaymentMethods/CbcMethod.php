<?php declare(strict_types=1);

namespace Sisow\Payment\PaymentMethods;

use Sisow\Payment\PaymentHandlers\CbcHandler;

class CbcMethod extends PaymentMethodBase implements PaymentMethodInterface
{
    public function getPaymentCode(): string
    {
        return 'cbc';
    }

    public function getName(): string
    {
        return 'CBC';
    }

    public function getPaymentHandler(): string
    {
        return CbcHandler::class;
    }
}