<?php declare(strict_types=1);

namespace Sisow\Payment\Helpers;

use Shopware\Core\Checkout\Payment\PaymentMethodEntity;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Exception\InconsistentCriteriaIdsException;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\System\SystemConfig\SystemConfigService;
use Sisow\Payment\Components\SisowPayment\SisowService;
use Sisow\Payment\PaymentHandlers\AfterPayHandler;
use Sisow\Payment\PaymentHandlers\CapayableHandler;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

class SisowHelper
{
    /**
     * @var SystemConfigService
     */
    public $systemConfigService;

    /**
     * @var SisowService
     */
    private $sisowClient;

    /** @var UrlGeneratorInterface $router */
    private $router;

    /** @var EntityRepositoryInterface */
    private $paymentRepository;

    public function __construct(SystemConfigService $systemConfigService, SisowService $sisowClient, UrlGeneratorInterface $router,  EntityRepositoryInterface $paymentRepository)
    {
        $this->systemConfigService = $systemConfigService;
        $this->sisowClient = $sisowClient;
        $this->router = $router;
        $this->paymentRepository = $paymentRepository;
    }

    public function createSisowClient(?string $salesChannelId): SisowService {
        if ($this->sisowClient === null) {
            $this->initSisowClient($salesChannelId);
        }

        return $this->sisowClient;
    }

    public function initSisowClient(?string $salesChannelId): SisowService {
        $merchantid = $this->getSetting("merchantid",$salesChannelId);
        $merchantkey = $this->getSetting("merchantkey",$salesChannelId);
        $shopid = $this->getSetting("shopid",$salesChannelId);

        $this->sisowClient = new SisowService();
        $this->sisowClient->setMerchant($merchantid, $merchantkey, $shopid);

        return $this->sisowClient;
    }

    public function getSetting(string $setting, ?string $salesChannelId = null)
    {
        return $this->systemConfigService->get('SisowPayment.config.' . $setting, $salesChannelId);
    }

    public function getIssuers()
    {
        $banks = array();
        $this->sisowClient->DirectoryRequest($banks, false, $this->getSetting("idealTestmode"));

        $issuers = [];
        foreach($banks as $k => $v)
        {
            $issuers[] = [
                'bankid' => $k,
                'bankname' => $v
            ];
        }
        return $issuers;
    }

    public function getB2b($handlerIdentifier)
    {
        switch ($handlerIdentifier) {
            case AfterPayHandler::class:
                return $this->getSetting("afterpayB2b");
                break;
            case BillinkHandler::class:
                return $this->getSetting("billinkB2b");
                break;
            case CapayableHandler::class:
                return $this->getSetting("capayableB2b");
                break;
        }
        return false;
    }

    public function getYears()
    {
        $years = [];
        for($i = date("Y") - 17; $i > date("Y") - 125; $i--)
        {
            $years[] = [
                'value' => $i,
                'year' => $i
            ];
        }
        return $years;
    }

    /**
     * @return array
     */
    public function getNotifyUrl(): string
    {
        return $this->router->generate(
            'sisow_notify',
             [],
            UrlGeneratorInterface::ABSOLUTE_URL
        );
    }

    /**
     * Get payment method by ID.
     *
     * @param $id
     * @return PaymentMethodEntity
     * @throws InconsistentCriteriaIdsException
     */
    public function getPaymentMethodById($id) : ?PaymentMethodEntity
    {
        // Fetch ID for update
        $paymentCriteria = new Criteria();
        $paymentCriteria->addFilter(new EqualsFilter('id', $id));

        // Get payment methods
        $paymentMethods = $this->paymentRepository->search($paymentCriteria, Context::createDefaultContext());

        if ($paymentMethods->getTotal() === 0) {
            return null;
        }

        return $paymentMethods->first();
    }
}