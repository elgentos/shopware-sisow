<?php declare(strict_types=1);

namespace Sisow\Payment\PaymentMethods;

use Sisow\Payment\PaymentHandlers\AfterPayHandler;

class AfterPayMethod extends PaymentMethodBase implements PaymentMethodInterface
{
    public function getPaymentCode(): string
    {
        return 'afterpay';
    }

    public function getName(): string
    {
        return 'AfterPay';
    }

    public function getPaymentHandler(): string
    {
        return AfterPayHandler::class;
    }

    public function getTemplate(): ?string
    {
        return '@SisowPayment/storefront/sisow/afterpay.html.twig';
    }
}