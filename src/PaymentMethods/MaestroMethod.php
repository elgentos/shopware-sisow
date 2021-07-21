<?php declare(strict_types=1);

namespace Sisow\Payment\PaymentMethods;

use Sisow\Payment\PaymentHandlers\MaestroHandler;

class MaestroMethod extends PaymentMethodBase implements PaymentMethodInterface
{
    public function getPaymentCode(): string
    {
        return 'maestro';
    }

    public function getName(): string
    {
        return 'Maestro';
    }

    public function getPaymentHandler(): string
    {
        return MaestroHandler::class;
    }
}