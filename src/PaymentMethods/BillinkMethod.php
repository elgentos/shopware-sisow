<?php declare(strict_types=1);

namespace Sisow\Payment\PaymentMethods;

use Sisow\Payment\PaymentHandlers\BillinkHandler;

class BillinkMethod extends PaymentMethodBase implements PaymentMethodInterface
{
    public function getPaymentCode(): string
    {
        return 'billink';
    }

    public function getName(): string
    {
        return 'Billink';
    }

    public function getPaymentHandler(): string
    {
        return BillinkHandler::class;
    }

    public function getTemplate(): ?string
    {
        return '@SisowPayment/storefront/sisow/billink.html.twig';
    }
}