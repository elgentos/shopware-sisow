<?php declare(strict_types=1);

namespace Sisow\Payment\PaymentMethods;

use Sisow\Payment\PaymentHandlers\CapayableHandler;

class CapayableMethod extends PaymentMethodBase implements PaymentMethodInterface
{
    public function getPaymentCode(): string
    {
        return 'capayable';
    }

    public function getName(): string
    {
        return 'In3 - betalen in 3 termijnen';
    }

    public function getPaymentHandler(): string
    {
        return CapayableHandler::class;
    }

    public function getTemplate(): ?string
    {
        return '@SisowPayment/storefront/sisow/capayable.html.twig';
    }
}