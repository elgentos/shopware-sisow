<?php declare(strict_types=1);

namespace Sisow\Payment\PaymentMethods;

use Sisow\Payment\PaymentHandlers\HomepayHandler;

class HomepayMethod extends PaymentMethodBase implements PaymentMethodInterface
{
    public function getPaymentCode(): string
    {
        return 'homepay';
    }

    public function getName(): string
    {
        return 'ING Home\'Pay';
    }

    public function getPaymentHandler(): string
    {
        return HomepayHandler::class;
    }
}