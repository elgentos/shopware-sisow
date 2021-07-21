<?php declare(strict_types=1);

namespace Sisow\Payment\PaymentHandlers;

use Exception;
use Shopware\Core\Checkout\Payment\Cart\PaymentHandler\SynchronousPaymentHandlerInterface;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\Plugin\PluginService;
use Sisow\Payment\Helpers\SisowHelper;
use Shopware\Core\Checkout\Order\Aggregate\OrderTransaction\OrderTransactionStateHandler;
use Shopware\Core\Checkout\Payment\Cart\SyncPaymentTransactionStruct;
use Shopware\Core\Checkout\Payment\Exception\SyncPaymentFinalizeException;
use Shopware\Core\Checkout\Payment\Exception\SyncPaymentProcessException;
use Shopware\Core\Checkout\Payment\Exception\CustomerCanceledSyncPaymentException;
use Shopware\Core\Framework\Validation\DataBag\RequestDataBag;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Core\Framework\Language\LanguageEntity;
use Symfony\Contracts\Translation\TranslatorInterface;
use Psr\Log\LoggerInterface;


class SyncSisowHandler extends BaseSisowHandler implements SynchronousPaymentHandlerInterface
{
    /**
     * SyncSisowHandler constructor.
     * @param OrderTransactionStateHandler $transactionStateHandler
     * @param SisowHelper $sisowHelper
     * @param PluginService $pluginService
     */
    public function __construct(
        OrderTransactionStateHandler $transactionStateHandler,
        SisowHelper $sisowHelper,
        PluginService $pluginService,
        EntityRepositoryInterface $orderRepository,
        TranslatorInterface $translator,
        LoggerInterface $logger
    )
    {
        parent::__construct($transactionStateHandler,
            $sisowHelper,
            $pluginService,
            $orderRepository,
            $translator,
            $logger
        );
    }

    /**
     * @param SyncPaymentTransactionStruct $transaction
     * @param RequestDataBag $dataBag
     * @param SalesChannelContext $salesChannelContext
     * @throws SyncPaymentProcessException
     */
    public function pay(
        SyncPaymentTransactionStruct $transaction,
        RequestDataBag $dataBag,
        SalesChannelContext $salesChannelContext
    ): void
    {
        $order = $transaction->getOrder();
        try {
            parent::payment($order, $dataBag, $salesChannelContext, $transaction->getOrderTransaction());
        } catch (Exception $e) {
            $this->logger->error('Sync Payment Exception, transaction: '.$transaction->getOrderTransaction()->getId().', '.$e->getMessage());
            throw new SyncPaymentProcessException($transaction->getOrderTransaction()->getId(),$e->getMessage());
        }
    }
}