<?php declare(strict_types=1);

namespace Sisow\Payment\PaymentHandlers;

use Exception;
use Shopware\Core\Checkout\Order\Aggregate\OrderTransaction\OrderTransactionEntity;
use Shopware\Core\Checkout\Order\Aggregate\OrderTransaction\OrderTransactionStateHandler;
use Shopware\Core\Checkout\Order\Aggregate\OrderTransaction\OrderTransactionStates;
use Shopware\Core\Checkout\Order\OrderEntity;
use Shopware\Core\Checkout\Payment\Exception\UnknownPaymentMethodException;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\Plugin\PluginService;
use Shopware\Core\Framework\Validation\DataBag\RequestDataBag;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Core\System\StateMachine\Exception\IllegalTransitionException;
use Sisow\Payment\Components\SisowPayment\SisowService;
use Sisow\Payment\Helpers\SisowHelper;
use Sisow\Payment\PaymentMethods\PaymentMethodInterface;
use Sisow\Payment\SisowPayment;
use Symfony\Contracts\Translation\TranslatorInterface;
use Psr\Log\LoggerInterface;

class BaseSisowHandler {
    /**
     * @var PaymentMethodInterface
     */
    protected $paymentMethod;

    /**
     * @var OrderTransactionStateHandler
     */
    protected $transactionStateHandler;

    /**
     * @var SisowHelper
     */
    protected $sisowHelper;

    /**
     * @var SisowService;
     */
    protected $sisow;

    /**
     * @var PluginService
     */
    protected $pluginService;

    /** @var EntityRepositoryInterface */
    private $orderRepository;

    /** @var TranslatorInterface */
    private $translator;

    /** @var LoggerInterface */
    protected $logger;


    /**
     * AsyncSisowHandler constructor.
     * @param OrderTransactionStateHandler $transactionStateHandler
     * @param SisowHelper $sisowHelper
     * @param PluginService $pluginService
     * @param EntityRepositoryInterface $orderRepository
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
        $this->transactionStateHandler = $transactionStateHandler;
        $this->sisowHelper = $sisowHelper;
        $this->pluginService = $pluginService;
        $this->orderRepository = $orderRepository;
        $this->translator = $translator;
        $this->logger = $logger;
    }

    /**
     * @param OrderEntity $order
     * @param RequestDataBag $dataBag
     * @param SalesChannelContext $salesChannelContext
     * @param OrderTransactionEntity $transaction
     * @param string $returnUrl
     * @return string|null;
     * @throws Exception
     */
    public function payment(OrderEntity $order,
                             RequestDataBag $dataBag,
                             SalesChannelContext $salesChannelContext,
                            OrderTransactionEntity $transaction,
                             string $returnUrl = "") {

        $salesChannelId = $order->getSalesChannelId() ?: $salesChannelContext->getSalesChannel()->getId();
        $this->sisow = $this->sisowHelper->initSisowClient($salesChannelId);

        $customer = $salesChannelContext->getCustomer();

        $plugin = $this->pluginService->getPluginByName('SisowPayment', $salesChannelContext->getContext());

        if (!$plugin) {
            throw new UnknownPaymentMethodException($salesChannelContext->getPaymentMethod()->getId());
        }

        if ($plugin->getBaseClass() !== SisowPayment::class) {
            $this->logger->error('Not a Sisow payment method');
            throw new Exception(
                'Not a Sisow payment method' . PHP_EOL
            );
        }

        if ($customer == null) {
            $this->logger->error('No customer details');
            throw new Exception(
                'No customer details' . PHP_EOL
            );
        }

        $currency = $salesChannelContext->getSalesChannel()->getCurrency()->getIsoCode();
        $locale = $salesChannelContext->getSalesChannel()->getLanguage() !== null ?? $salesChannelContext->getSalesChannel()->getLanguage()->getLocale();

        $arg = [];

        /*
        if($this->get_option('makeinvoice') == 'yes')
            $arg['makeinvoice'] = 'true';
        */

        // get description
        $description = $this->sisowHelper->getSetting($this->sisow->payment.'Description', $salesChannelId);
        /*
        if(empty($description))
            $description = $this->sisowHelper->getSetting('description', $salesChannelId);
        */

        if(empty($description)) {
            $description = $order->getOrderNumber();
        } else {
            if (strpos($description, '{orderid}') !== false) {
                str_replace('{orderid}', $order->getOrderNumber(), $description);
            } else {
                $description .= ' '.$order->getOrderNumber();
            }
        }

        $this->sisow->payment = $this->paymentMethod->getPaymentCode();

        // get testmode
        $testmode = $this->sisowHelper->getSetting('testmode', $salesChannelId); // get default test mode
        if(!$testmode)
            $testmode = $this->sisowHelper->getSetting($this->sisow->payment.'Testmode', $salesChannelId); // Per betaalmethode

        if($testmode)
            $arg['testmode'] = 'true';

        switch ($this->sisow->payment){
            case 'ideal':
                $this->sisow->issuerId = $dataBag->get('sisow_issuer') ?: '';
                break;
            case 'giropay':
            case 'eps':
                $arg['bic'] = $dataBag->get('sisow_bic') ?: '';
                break;
            case 'afterpay':
            case 'capayable':
            case 'billink':
                $arg['gender'] = $dataBag->get('sisow_gender') ?: '';

                if ($dataBag->get('sisow_day') && $dataBag->get('sisow_month') && $dataBag->get('sisow_year')) {
                    $arg['birthdate'] = $dataBag->get('sisow_day').$dataBag->get('sisow_month').$dataBag->get('sisow_year');
                } else {
                    $arg['birthdate'] ='';
                }
                $arg['billing_coc'] = $dataBag->get('sisow_coc') ?: '';
                break;
            case 'overboeking':
                $days = $this->sisowHelper->getSetting($this->sisow->payment.'Days', $salesChannelId);
                $include = $this->sisowHelper->getSetting($this->sisow->payment.'Include', $salesChannelId);

                $arg['including'] = $include ? 'true' : 'false';
                if ($days > 0)
                    $arg['days'] = $days;
                break;
        }


        $arg['ipaddress'] = $_SERVER['REMOTE_ADDR'];

        //add Shipping Address
        $shippingAddress = $customer->getDefaultShippingAddress();
        $arg['shipping_firstname'] = $shippingAddress->getFirstName();
        $arg['shipping_lastname'] = $shippingAddress->getLastName();
        $arg['shipping_mail'] = $customer->getEmail();
        $arg['shipping_company'] = !empty($shippingAddress->getCompany()) ? $shippingAddress->getCompany() : '';
        $arg['shipping_address1'] = $shippingAddress->getStreet();
        $arg['shipping_address2'] = $shippingAddress->getAdditionalAddressLine1();
        $arg['shipping_zip'] = $shippingAddress->getZipCode();
        $arg['shipping_city'] = $shippingAddress->getCity();
        $arg['shipping_countrycode'] = $shippingAddress->getCountry()->getIso();
        $arg['shipping_phone'] = $shippingAddress->getPhoneNumber();

        //add Billing Address
        $billingAddress = $customer->getDefaultBillingAddress();
        $arg['billing_firstname'] = $billingAddress->getFirstName();
        $arg['billing_lastname'] = $billingAddress->getLastName();
        $arg['billing_mail'] = $customer->getEmail();
        $arg['billing_company'] = !empty($billingAddress->getCompany()) ? $billingAddress->getCompany() : '';
        $arg['billing_address1'] = $billingAddress->getStreet();
        $arg['billing_address2'] = $billingAddress->getAdditionalAddressLine1();
        $arg['billing_zip'] = $billingAddress->getZipCode();
        $arg['billing_city'] = $billingAddress->getCity();
        $arg['billing_countrycode'] = $billingAddress->getCountry()->getIso();
        $arg['billing_phone'] = $billingAddress->getPhoneNumber();

        $arg['shipping'] = round( ($order->getShippingCosts() ? $order->getShippingCosts()->getUnitPrice() : 0) *100 );
        $arg['tax'] = $order->getAmountTotal() - $order->getAmountNet(); //;
        $arg['currency'] = $currency;

        //producten
        $item_loop = 0;
        $lineItems = $order->getLineItems();

        if ($lineItems !== null && $lineItems !== 0) {
            foreach ($lineItems as $item) {

                $item_loop++;

                $unitPrice = $item->getUnitPrice();
                $taxRate = 0.0;
                if ($item->getPrice()->getCalculatedTaxes() !== null && $item->getPrice()->getCalculatedTaxes()->count() > 0) {
                    $taxRate = $item->getPrice()->getCalculatedTaxes()->first()->getTaxRate();
                }
                $unitPriceExcl = $unitPrice;
                if ($unitPrice && $taxRate) {
                    $unitPriceExcl /= (1 + ($taxRate / 100));
                }
                $unitTax = $unitPrice - $unitPriceExcl;

                $arg['product_id_' . $item_loop] = $item->getId();
                $arg['product_description_' . $item_loop] = $item->getLabel();
                $arg['product_quantity_' . $item_loop] = $item->getQuantity();
                $arg['product_netprice_' . $item_loop] = round($unitPriceExcl, 2) * 100;
                $arg['product_total_' . $item_loop] = round($unitPrice * $item->getQuantity(), 2) * 100;
                $arg['product_nettotal_' . $item_loop] = round($unitPriceExcl * $item->getQuantity(), 2) * 100;
                $arg['product_tax_' . $item_loop] = round($unitTax * $item->getQuantity(), 2) * 100;
                $arg['product_taxrate_' . $item_loop] = isset($taxRate) ? round($taxRate, 2) * 100 : 0;
                $arg['product_type_' . $item_loop] = 'physical';
            }
        }

        $shipping = $order->getShippingCosts();
        //verzendkosten
        if ($shipping !== null) {
            $item_loop++;

            $unitPrice = $shipping->getUnitPrice();
            $taxRate = 0.0;
            if  ($shipping->getCalculatedTaxes() !== null && $shipping->getCalculatedTaxes()->count() > 0) {
                $taxRate = $shipping->getCalculatedTaxes()->first()->getTaxRate();
            }
            $unitPriceExcl =  $unitPrice;
            if ($unitPrice && $taxRate) {
                $unitPriceExcl /= (1 + ($taxRate / 100));
            }
            $unitTax = $unitPrice - $unitPriceExcl;

            $arg['product_id_' . $item_loop] = 'shipping';
            $arg['product_description_' . $item_loop] = 'Verzendkosten';
            $arg['product_quantity_' . $item_loop] = $shipping->getQuantity();
            $arg['product_netprice_' . $item_loop] = round($unitPriceExcl, 2) * 100;
            $arg['product_total_' . $item_loop] = round($unitPrice * $shipping->getQuantity(), 2) * 100;
            $arg['product_nettotal_' . $item_loop] = round($unitPriceExcl * $item->getQuantity(), 2) * 100;
            $arg['product_tax_' . $item_loop] =  round($unitTax * $shipping->getQuantity(), 2) * 100;
            $arg['product_taxrate_' . $item_loop] = isset($taxRate) ? round($taxRate, 2) * 100 : 0;
            $arg['product_type_'. $item_loop] = 'shipping_fee';
        }


        $this->sisow->amount = $order->getAmountTotal();
        $this->sisow->purchaseId = $order->getOrderNumber();
        $this->sisow->entranceCode = $order->getOrderNumber();
        $this->sisow->description = $description; // description for consumer bank statement

        $returnUrl = "invalid.local";
        if (empty($returnUrl)) {
            $returnUrl = $this->sisowHelper->getNotifyUrl();
        }
        $this->sisow->returnUrl = $returnUrl;
        $this->sisow->cancelUrl = sprintf('%s&cancel=1', $returnUrl);
        $this->sisow->notifyUrl = $this->sisowHelper->getNotifyUrl();
        $this->sisow->callbackUrl = $this->sisowHelper->getNotifyUrl();

        if (($ex = $this->sisow->TransactionRequest($arg)) < 0) {

            $this->logger->error('Order '.$order->getOrderNumber().', Failed to start Transaction (' . $ex . ', ' . $this->sisow->errorCode . ', ' . $this->sisow->errorMessage . ')');
            $errorMessage = '';
            if ($this->sisow->payment == 'billink') {
                $errorMessage = $this->translator->trans('sisow.errorMessages.billinkError');;
            } else if ($this->sisow->payment == 'afterpay') {
                $errorMessage = $this->sisow->errorMessage;

                $defaultError = $this->translator->trans('sisow.errorMessages.afterpayDefaultError');

                if (!empty($errorMessage) && strpos($errorMessage, 'Reservation not possible (Failed;') !== false) {
                    $errorMessage = str_replace('Reservation not possible (Failed;', '', $errorMessage);
                    $errorMessage = substr($errorMessage, 0, strlen($errorMessage) - 1);

                    if ($errorMessage == 'Afterpay Technical Error' || $errorMessage == 'Aanvraag komt niet in aanmerking voor AfterPay')
                        $errorMessage = $defaultError;
                } else {
                    $errorMessage = $defaultError;
                }
            } else if ($this->sisow->payment == 'klarna') {
                $errorMessage = $this->translator->trans('sisow.errorMessages.klarnaError');
            }else {
                $errorMessage = sprintf($this->translator->trans('sisow.errorMessages.transactionError'),$ex, $this->sisow->errorCode) ;
                //$errorMessage = 'Error on starting the transaction' . ' (' . $ex . ', ' . $this->sisow->errorCode . ')';
            }

            throw new Exception(
                // 'Failed to start Transaction (' . $ex . ', ' . $this->sisow->errorCode . ', ' . $this->sisow->errorMessage . ')'
                $errorMessage
            );
        }

        // save trxid
        if (isset($this->sisow->trxId)) {
            $customFields = $transaction->getCustomFields() ?? [];
            $customFields['sisow_trxid'] = $this->sisow->trxId;

            $transaction->setCustomFields($customFields);

            $update = [
                'id' => $order->getId(),
                'customFields' => $customFields,
            ];

            $this->orderRepository->update([$update], $salesChannelContext->getContext());
        }
        $redirectUrl = null;

        try {

            if (in_array( $this->sisow->payment, ['overboeking', 'ebill', 'afterpay', 'billink' ]) ) {
                $context = $salesChannelContext->getContext();

                $currentState = $transaction->getStateMachineState()->getTechnicalName();

                try {
                    if (in_array($currentState, [OrderTransactionStates::STATE_OPEN])) {
                        switch ($this->sisow->status) {
                            case 'Pending':
                                // set order state to pending
                                $this->transactionStateHandler->process($transaction->getId(), $context);
                                break;
                            case 'Reservation':
                                // set order state to reservation
                                $this->transactionStateHandler->process($transaction->getId(), $context);


                                if ( $this->sisowHelper->getSetting($this->sisow->payment.'Makeinvoice', $salesChannelId) == true ) {

                                    $customFields = $transaction->getCustomFields() ?? [];
                                    if (!isset($customFields['sisow_invoice'])) {

                                        $this->sisow->InvoiceRequest($this->sisow->trxId);

                                        $customFields['sisow_invoice'] = true;
                                        $transaction->setCustomFields($customFields);
                                    }
                                }
                                break;
                            //default:
                            // status open, send consumer to the issuer URL to complete the payment
                            //$this->transactionStateHandler->reopen($transaction->getId(), $context);
                        }
                    }
                } catch (IllegalTransitionException $exception) {
                    // already set (eat exception)
                }

                return $redirectUrl;
            } else {
                return $redirectUrl = $this->sisow->issuerUrl;
            }

        } catch (Exception $e) {
            $this->logger->error('An error occurred during the communication with external payment gateway' . PHP_EOL . $e->getMessage());

            throw new Exception (
                'An error occurred during the communication with external payment gateway' . PHP_EOL . $e->getMessage()
            );
        }
    }
}