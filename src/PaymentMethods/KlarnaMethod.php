<?php declare(strict_types=1);

namespace Sisow\Payment\PaymentMethods;

use Sisow\Payment\PaymentHandlers\KlarnaHandler;

class KlarnaMethod extends PaymentMethodBase implements PaymentMethodInterface
{
    public function getPaymentCode(): string
    {
        return 'klarna';
    }

    public function getName(): string
    {
        return 'Klarna';
    }

    public function getPaymentHandler(): string
    {
        return KlarnaHandler::class;
    }

    public function getTemplate(): ?string
    {
        return '@SisowPayment/storefront/sisow/klarna.html.twig';
    }
}