<?php declare(strict_types=1);

namespace Sisow\Payment\Storefront\Controller;

use Shopware\Core\Checkout\Order\Aggregate\OrderTransaction\OrderTransactionStateHandler;
use Shopware\Core\Checkout\Order\Aggregate\OrderTransaction\OrderTransactionStates;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Storefront\Controller\StorefrontController;
use Sisow\Payment\Components\SisowPayment\SisowService;
use Sisow\Payment\Helpers\SisowHelper;
use Shopware\Core\Framework\Context;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Routing\RouterInterface;
use Shopware\Core\Framework\Routing\Annotation\RouteScope;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Throwable;
use Exception;

class NotifyController extends StorefrontController
{
    /** @var Request $request */
    private $request;
    /** @var EntityRepositoryInterface $orderRepository */
    private $orderRepository;
    /** @var OrderTransactionStateHandler $orderTransactionStateHandler*/
    private $orderTransactionStateHandler;
    /** @var Context $context */
    private $context;
    /** @var SisowHelper $sisowHelper */
    private $sisowHelper;
    /** @var SisowService $sisow */
    protected $sisow;

    /**
     * NotifyController constructor.
     * @param EntityRepositoryInterface $orderRepository
     * @param SisowHelper $sisowHelper
     * @param OrderTransactionStateHandler $orderTransactionStateHandler
     */
    public function __construct( EntityRepositoryInterface $orderRepository,
                                 SisowHelper $sisowHelper,
                                 OrderTransactionStateHandler $orderTransactionStateHandler
                                 )
    {
        $this->orderRepository = $orderRepository;
        $this->sisowHelper = $sisowHelper;
        $this->request = new Request($_GET, $_POST, array(), $_COOKIE, $_FILES, $_SERVER);
        $this->context = \Shopware\Core\Framework\Context::createDefaultContext();
        $this->orderTransactionStateHandler = $orderTransactionStateHandler;
    }

    /**
     * @RouteScope(scopes={"storefront"})
     * @Route("/sisow/notify", name="sisow_notify", defaults={"csrf_protected": false}, options={"seo"="false"}, methods={"GET"})
     * @return Response
     * @throws Exception
     */
    public function notify(): Response
    {
        $response = new Response();
        $orderNumber = $this->request->query->get('ec');
        $trxid = $this->request->query->get('trxid');
        try {
            $orderRepo = $this->orderRepository;
            $criteria = new Criteria();
            $criteria->addFilter(new EqualsFilter('orderNumber', $orderNumber))
                ->addAssociation('transactions');
            $order = $orderRepo->search($criteria, $this->context)->first();

            $salesChannelId = $order->getSalesChannelId();
            $this->sisow = $this->sisowHelper->initSisowClient($salesChannelId);

            $transaction = $order->getTransactions()->first();
            $transactionId = $transaction->getId();
            $customFields  = $order->getCustomFields();
            //$paymentMethodId = $transaction->getPaymentMethodId();
            //$paymentMethod = $this->sisowHelper->getPaymentMethodById($paymentMethodId);

            $orderTrxid = $customFields['sisow_trxid'];
            if(empty($orderTrxid)) {
                throw new Exception('No trxid');
            }

            if($orderTrxid != $trxid) {
                throw new Exception('Invalid order');
            }

            // Execute StatusRequest
            if(($ex = $this->sisow->StatusRequest($trxid)) < 0)
                throw new Exception('StatusRequest failed');

            // Sisow status set?
            if(empty($this->sisow->status)) {
                throw new Exception('No sisow status');
            }

            $currentState = $transaction->getStateMachineState()->getTechnicalName();

            if(!in_array($currentState , [OrderTransactionStates::STATE_OPEN, OrderTransactionStates::STATE_IN_PROGRESS, OrderTransactionStates::STATE_REMINDED ]) ) {
                throw new Exception('Order already processed!');
            }

            switch($this->sisow->status)
            {
                case "Cancelled":
                case "Expired":
                case "Denied":
                    if(OrderTransactionStates::STATE_OPEN != $currentState)
                        throw new Exception('Order already processed!');

                    $this->orderTransactionStateHandler->cancel($transactionId, $this->context);
                    break;
                case "Failure":
                    if(OrderTransactionStates::STATE_OPEN != $currentState)
                        throw new Exception('Order already processed!');

                    $this->transactionStateHandler->fail($transaction->getOrderTransaction()->getId(), $this->context);
                    break;
                case "Reversed":
                case "Refund":
                    $this->orderTransactionStateHandler->cancel($transactionId, $this->context);
                    break;
                case "Paid":
                case "Success":
                    if (OrderTransactionStates::STATE_PAID == $currentState)
                        throw new Exception('Order already processed!');

                    $this->orderTransactionStateHandler->paid($transactionId, $this->context);
                    break;
                case "Reservation":
                    if(OrderTransactionStates::STATE_OPEN != $currentState)
                        throw new Exception('Order already processed!');


                    if ($this->sisowHelper->getSetting($this->sisow->payment.'Makeinvoice', $salesChannelId) == true
                    ) {

                        $customFields = $transaction->getCustomFields() ?? [];
                        if (!isset($customFields['sisow_invoice'])) {

                            $this->sisow->InvoiceRequest($orderTrxid);

                            $customFields['sisow_invoice'] = true;
                            $transaction->setCustomFields($customFields);
                        }
                    }
                case "Pending":
                    if(OrderTransactionStates::STATE_OPEN != $currentState)
                        throw new Exception('Order already processed!');

                    $this->orderTransactionStateHandler->process($transactionId, $this->context);
                    break;
                case "Open":
                    if(OrderTransactionStates::STATE_OPEN != $currentState)
                        $this->orderTransactionStateHandler->reopen($transactionId, $this->context);
                    break;
                default:
                    throw new Exception('Status unknown');
            }

        } catch (InconsistentCriteriaIdsException $exception) {
            return $response->setContent('NG');
        } catch (Exception $exception) {
            return $response->setContent('NG');
        }

        return $response->setContent('OK');
    }
}