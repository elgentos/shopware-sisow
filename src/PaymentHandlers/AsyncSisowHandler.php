<?php declare(strict_types=1);

namespace Sisow\Payment\PaymentHandlers;

use Exception;
use Shopware\Core\Checkout\Order\Aggregate\OrderTransaction\OrderTransactionStates;
use Shopware\Core\Checkout\Payment\Exception\AsyncPaymentFinalizeException;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\Plugin\PluginService;
use Sisow\Payment\Helpers\SisowHelper;
use Sisow\Payment\Helpers\RedirectHelper;
use Shopware\Core\Checkout\Order\Aggregate\OrderTransaction\OrderTransactionStateHandler;
use Shopware\Core\Checkout\Payment\Cart\AsyncPaymentTransactionStruct;
use Shopware\Core\Checkout\Payment\Cart\PaymentHandler\AsynchronousPaymentHandlerInterface;
use Shopware\Core\Checkout\Payment\Exception\AsyncPaymentProcessException;
use Shopware\Core\Checkout\Payment\Exception\CustomerCanceledAsyncPaymentException;
use Shopware\Core\Framework\Validation\DataBag\RequestDataBag;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Shopware\Core\Framework\Language\LanguageEntity;
use Symfony\Contracts\Translation\TranslatorInterface;
use Psr\Log\LoggerInterface;


class AsyncSisowHandler extends BaseSisowHandler implements AsynchronousPaymentHandlerInterface
{

    /** @var RedirectHelper */
    protected $redirectHelper;

    /**
     * AsyncSisowHandler constructor.
     * @param OrderTransactionStateHandler $transactionStateHandler
     * @param SisowHelper $sisowHelper
     * @param PluginService $pluginService
     * @param RedirectHelper $redirectHelper
     */
    public function __construct(
        OrderTransactionStateHandler $transactionStateHandler,
        SisowHelper $sisowHelper,
        PluginService $pluginService,
        RedirectHelper $redirectHelper,
        EntityRepositoryInterface $orderRepository,
        TranslatorInterface $translator,
        LoggerInterface $logger
    )
    {
        $this->redirectHelper = $redirectHelper;
        parent::__construct($transactionStateHandler,
            $sisowHelper,
            $pluginService,
            $orderRepository,
            $translator,
            $logger
        );
    }

    /**
     * @param AsyncPaymentTransactionStruct $transaction
     * @param RequestDataBag $dataBag
     * @param SalesChannelContext $salesChannelContext
     * @return RedirectResponse
     * @throws AsyncPaymentProcessException
     */
    public function pay(
        AsyncPaymentTransactionStruct $transaction,
        RequestDataBag $dataBag,
        SalesChannelContext $salesChannelContext
    ): RedirectResponse {
        // Method that sends the return URL to the external gateway and gets a redirect URL back

        $order = $transaction->getOrder();
        $returnUrl = $this->redirectHelper->encode($transaction->getReturnUrl());
        try {
            $redirectUrl = parent::payment($order, $dataBag,$salesChannelContext,$transaction->getOrderTransaction(), $returnUrl);
        } catch (Exception $e) {
            $this->logger->error('Async Payment Exception, transaction: '.$transaction->getOrderTransaction()->getId().', '.$e->getMessage());
            throw new AsyncPaymentProcessException($transaction->getOrderTransaction()->getId(),$e->getMessage());
        }

        // Redirect to external gateway
        return new RedirectResponse($redirectUrl);
    }

    /**
     * @throws CustomerCanceledAsyncPaymentException
     * @throws Exception
     */
    public function finalize(
        AsyncPaymentTransactionStruct $transaction,
        Request $request,
        SalesChannelContext $salesChannelContext
    ): void {
        $transactionId = $transaction->getOrderTransaction()->getId();
        $salesChannelId = $salesChannelContext->getSalesChannel()->getId();

        // Cancelled payment?
        if ($request->query->getBoolean('cancel')) {
            $this->logger->error(sprintf('Transaction %s, Customer canceled the payment on the payment page',$transactionId));
            throw new CustomerCanceledAsyncPaymentException(
                $transactionId,
                'Customer canceled the payment on the payment page'
            );
        }

        $orderId = $request->query->getAlnum('ec');
        $status = $request->query->getAlpha('status');;
        $trxid = $request->query->getAlnum('trxid');
        $sha = $request->query->getAlnum('sha1');

        $merchantid = $this->sisowHelper->getSetting("merchantid",$salesChannelId);
        $merchantkey = $this->sisowHelper->getSetting("merchantkey",$salesChannelId);
       // $shopid = $this->sisowHelper->getSetting("shopid",$salesChannelId);

        // Validate Notify
        if(sha1($trxid . $orderId . $status . $merchantid . $merchantkey) != $sha) {
            $this->logger->error(sprintf('Transaction %s, Invalid Notify! Hash mismatch',$transactionId));
            throw new AsyncPaymentFinalizeException($transactionId, 'Invalid Notify!');
        }

        $context = $salesChannelContext->getContext();

        $currentState = $transaction->getOrderTransaction()->getStateMachineState()->getTechnicalName();

        if(!in_array($currentState , [OrderTransactionStates::STATE_OPEN, OrderTransactionStates::STATE_IN_PROGRESS, OrderTransactionStates::STATE_REMINDED ]) ) {
            // Order already processed!
            return;
        }

        switch ($status) {
            case "Cancel":
            case "Expired":
            case "Denied":
                if(OrderTransactionStates::STATE_OPEN != $currentState)
                    // Order already processed!
                    return;

                $this->transactionStateHandler->cancel($transaction->getOrderTransaction()->getId(), $context);
                break;
            case "Failure":
                if(OrderTransactionStates::STATE_OPEN != $currentState)
                    // Order already processed!
                    return;

                $this->transactionStateHandler->fail($transaction->getOrderTransaction()->getId(), $context);
                break;
            case "Reversed":
            case "Refund":
                $this->transactionStateHandler->cancel($transaction->getOrderTransaction()->getId(), $context);
                break;
            case "Paid":
            case "Success":
                if (OrderTransactionStates::STATE_PAID == $currentState)
                    // Order already processed!
                    return;

                $this->transactionStateHandler->paid($transaction->getOrderTransaction()->getId(), $context);
                break;
            case "Reservation":
            case "Pending":
                if(OrderTransactionStates::STATE_OPEN != $currentState)
                    // Order already processed!
                    return;

                $this->transactionStateHandler->process($transaction->getOrderTransaction()->getId(), $context);
                break;
            case "Open":
                if(OrderTransactionStates::STATE_OPEN != $currentState)
                    $this->transactionStateHandler->reopen($transaction->getOrderTransaction()->getId(), $context);

                break;

            default:
                $this->logger->error(sprintf('Transaction %s, Unknown status',$transactionId));
                throw new AsyncPaymentFinalizeException($transactionId, 'Unknown status');
        }
    }
}