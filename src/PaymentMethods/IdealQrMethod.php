<?php declare(strict_types=1);

namespace Sisow\Payment\PaymentMethods;

use Sisow\Payment\PaymentHandlers\IdealQrHandler;

class IdealQrMethod extends PaymentMethodBase implements PaymentMethodInterface
{
    public function getPaymentCode(): string
    {
        return 'idealqr';
    }

    public function getName(): string
    {
        return 'iDEAL QR';
    }

    public function getPaymentHandler(): string
    {
        return IdealHandler::class;
    }
}