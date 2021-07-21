<?php declare(strict_types=1);

namespace Sisow\Payment\PaymentMethods;

use Sisow\Payment\PaymentHandlers\EpsHandler;

class EpsMethod extends PaymentMethodBase implements PaymentMethodInterface
{
    public function getPaymentCode(): string
    {
        return 'eps';
    }

    public function getName(): string
    {
        return 'EPS';
    }

    public function getPaymentHandler(): string
    {
        return EpsHandler::class;
    }

    public function getTemplate(): ?string
    {
        return '@SisowPayment/storefront/sisow/eps.html.twig';
    }
}