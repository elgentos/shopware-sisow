<?php declare(strict_types=1);

namespace Sisow\Payment\PaymentMethods;

use Sisow\Payment\PaymentHandlers\VisaHandler;

class VisaMethod extends PaymentMethodBase implements PaymentMethodInterface
{
    public function getPaymentCode(): string
    {
        return 'visa';
    }

    public function getName(): string
    {
        return 'Visa';
    }

    public function getPaymentHandler(): string
    {
        return VisaHandler::class;
    }
}