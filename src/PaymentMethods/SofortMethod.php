<?php declare(strict_types=1);

namespace Sisow\Payment\PaymentMethods;

use Sisow\Payment\PaymentHandlers\SofortHandler;

class SofortMethod extends PaymentMethodBase implements PaymentMethodInterface
{
    public function getPaymentCode(): string
    {
        return 'sofort';
    }

    public function getName(): string
    {
        return 'Sofort';
    }

    public function getPaymentHandler(): string
    {
        return SofortHandler::class;
    }
}