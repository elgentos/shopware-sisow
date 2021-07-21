<?php declare(strict_types=1);

namespace Sisow\Payment\PaymentMethods;

use Sisow\Payment\PaymentHandlers\GiropayHandler;

class GiropayMethod extends PaymentMethodBase implements PaymentMethodInterface
{
    public function getPaymentCode(): string
    {
        return 'giropay';
    }

    public function getName(): string
    {
        return 'Giropay';
    }

    public function getPaymentHandler(): string
    {
        return GiropayHandler::class;
    }

    public function getTemplate(): ?string
    {
        return '@SisowPayment/storefront/sisow/giropay.html.twig';
    }
}