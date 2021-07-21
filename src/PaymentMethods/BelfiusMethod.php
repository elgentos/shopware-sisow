<?php declare(strict_types=1);

namespace Sisow\Payment\PaymentMethods;

use Sisow\Payment\PaymentHandlers\BelfiusHandler;

class BelfiusMethod extends PaymentMethodBase implements PaymentMethodInterface
{
    public function getPaymentCode(): string
    {
        return 'belfius';
    }

    public function getName(): string
    {
        return 'Belfius';
    }

    public function getPaymentHandler(): string
    {
        return BelfiusHandler::class;
    }
}