<?php declare(strict_types=1);

namespace Sisow\Payment\PaymentMethods;

use Sisow\Payment\PaymentHandlers\BancontactHandler;

class BancontactMethod extends PaymentMethodBase implements PaymentMethodInterface
{
    public function getPaymentCode(): string
    {
        return 'bancontact';
    }

    public function getName(): string
    {
        return 'Bancontact';
    }

    public function getPaymentHandler(): string
    {
        return BancontactHandler::class;
    }
}