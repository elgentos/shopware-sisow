<?php declare(strict_types=1);

namespace Sisow\Payment\PaymentMethods;

use Sisow\Payment\PaymentHandlers\IdealHandler;

class IdealMethod extends PaymentMethodBase implements PaymentMethodInterface
{
    public function getPaymentCode(): string
    {
        return 'ideal';
    }

    public function getName(): string
    {
        return 'iDEAL';
    }

    public function getPaymentHandler(): string
    {
        return IdealHandler::class;
    }

    public function getTemplate(): ?string
    {
        return '@SisowPayment/storefront/sisow/ideal.html.twig';
    }
}