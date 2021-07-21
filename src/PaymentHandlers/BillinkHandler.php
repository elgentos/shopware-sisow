<?php declare(strict_types=1);

namespace Sisow\Payment\PaymentHandlers;

use Sisow\Payment\PaymentMethods\BillinkMethod;
use Shopware\Core\Checkout\Payment\Cart\SyncPaymentTransactionStruct;
use Shopware\Core\Framework\Validation\DataBag\RequestDataBag;
use Shopware\Core\System\SalesChannel\SalesChannelContext;

class BillinkHandler extends SyncSisowHandler
{
    /**
     * @param SyncPaymentTransactionStruct $transaction
     * @param RequestDataBag $dataBag
     * @param SalesChannelContext $salesChannelContext
     * @throws \Shopware\Core\Checkout\Payment\Exception\SyncPaymentProcessException
     */
    public function pay(
        SyncPaymentTransactionStruct $transaction,
        RequestDataBag $dataBag,
        SalesChannelContext $salesChannelContext
    ): void {
        $this->paymentMethod = new BillinkMethod();
        parent::pay(
            $transaction,
            $dataBag,
            $salesChannelContext
        );
    }
}