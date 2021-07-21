<?php declare(strict_types=1);

namespace Sisow\Payment\Subscribers;

use Shopware\Storefront\Page\Account\Order\AccountEditOrderPageLoadedEvent;
use Sisow\Payment\Helpers\SisowHelper;
use Sisow\Payment\PaymentHandlers\AfterPayHandler;
use Sisow\Payment\PaymentHandlers\BillinkHandler;
use Sisow\Payment\PaymentHandlers\CapayableHandler;
use Sisow\Payment\PaymentHandlers\IdealHandler;
use Sisow\Payment\PaymentHandlers\KlarnaHandler;
use Sisow\Payment\Storefront\Struct\CheckoutExtensionData;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Shopware\Core\Checkout\Payment\PaymentMethodEntity;
use Sisow\Payment\Installers\PaymentMethodInstaller;
use Sisow\Payment\PaymentMethods\PaymentMethodInterface;
use Sisow\Payment\SisowPayment;
use Exception;

class AccountOrderEditTemplateSubscriber implements EventSubscriberInterface
{
    /** @var SisowHelper */
    private $sisowHelper;

    /**
     * @return array
     */
    public static function getSubscribedEvents(): array
    {
        return [
            AccountEditOrderPageLoadedEvent::class => 'addSisowExtension'
        ];
    }

    /**
     * Creates a new instance of the checkout confirm page subscriber.
     *
     * @param SisowHelper $sisowHelper
     */
    public function __construct(
        SisowHelper $sisowHelper
    ) {
        $this->sisowHelper = $sisowHelper;
    }

    /**
     * @param AccountEditOrderPageLoadedEvent $event
     * @throws Exception
     */
    public function addSisowExtension(AccountEditOrderPageLoadedEvent $event): void
    {
        $page    = $event->getPage();
        $salesChannelContext = $event->getSalesChannelContext();
        //$customer = $salesChannelContext->getCustomer();

        $paymentMethod = $salesChannelContext->getPaymentMethod();



        if (!$paymentMethod || !$paymentMethod->getHandlerIdentifier() )
            return;

        $customFields = $paymentMethod->getCustomFields();

        if (empty($customFields[PaymentMethodInstaller::IS_SISOW]) || !$customFields[PaymentMethodInstaller::IS_SISOW])
            return;

        if ($page->hasExtension(CheckoutExtensionData::EXTENSION_NAME)) {
            $extensionData = $page->getExtension(CheckoutExtensionData::EXTENSION_NAME);
        } else {
            $extensionData = new CheckoutExtensionData();
        }

        $template = null;
        $customFields = $paymentMethod->getCustomFields();
        if (!empty($customFields[PaymentMethodInstaller::TEMPLATE])) {
            $template = $customFields[PaymentMethodInstaller::TEMPLATE];
        }

        if (null !== $extensionData) {
            $extensionData->assign([
                'template' => $template,
                'handler' => $paymentMethod->getFormattedHandlerIdentifier(),
             ]);
        }

        $preferredIssuer = "";

        if ($paymentMethod->getHandlerIdentifier() === IdealHandler::class) {
            $issuers = $this->sisowHelper->getIssuers();
            $extensionData->assign([
                'issuers' => $issuers,
            ]);
            $page->assign([
                'preferred_issuer' => $preferredIssuer
            ]);
        }

        if (in_array ($paymentMethod->getHandlerIdentifier(), [ AfterPayHandler::class,BillinkHandler::class,CapayableHandler::class,KlarnaHandler::class ] ) ) {
            $extensionData->assign([
                'years' => $this->sisowHelper->getYears()
            ]);
        }

        $billingAddress = $salesChannelContext->getCustomer()->getActiveBillingAddress();

        if (in_array ($paymentMethod->getHandlerIdentifier(), [ AfterPayHandler::class,BillinkHandler::class, CapayableHandler::class ] ) ) {
            $extensionData->assign([
                'offerB2b' => $this->sisowHelper->getB2b($paymentMethod->getHandlerIdentifier()),
                'requireB2b' => ( $billingAddress != null && !empty($billingAddress->getCompany()) )
            ]);
        }

        $page->assign(['paymentMethodName' => $paymentMethod->getName()]);

        $page->addExtension(CheckoutExtensionData::EXTENSION_NAME, $extensionData);
    }
}