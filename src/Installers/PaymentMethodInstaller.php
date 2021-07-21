<?php declare(strict_types=1);

namespace Sisow\Payment\Installers;

use Shopware\Core\Content\Media\File\FileSaver;
use Shopware\Core\Content\Media\File\MediaFile;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Plugin;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\Plugin\Context\ActivateContext;
use Shopware\Core\Framework\Plugin\Context\DeactivateContext;
use Shopware\Core\Framework\Plugin\Context\InstallContext;
use Shopware\Core\Framework\Plugin\Context\UninstallContext;
use Shopware\Core\Framework\Plugin\Context\UpdateContext;

use Shopware\Core\Framework\Uuid\Uuid;

use Sisow\Payment\PaymentMethods\BunqMethod;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\DependencyInjection\Loader\XmlFileLoader;

use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\Plugin\Util\PluginIdProvider;

use Sisow\Payment\SisowPayment;
use Sisow\Payment\PaymentMethods\PaymentMethodInterface;
use Sisow\Payment\PaymentMethods\AfterPayMethod;
use Sisow\Payment\PaymentMethods\BancontactMethod;
use Sisow\Payment\PaymentMethods\BelfiusMethod;
use Sisow\Payment\PaymentMethods\BillinkMethod;
use Sisow\Payment\PaymentMethods\CapayableMethod;
use Sisow\Payment\PaymentMethods\CbcMethod;
use Sisow\Payment\PaymentMethods\EpsMethod;
use Sisow\Payment\PaymentMethods\GiropayMethod;
use Sisow\Payment\PaymentMethods\HomepayMethod;
use Sisow\Payment\PaymentMethods\IdealMethod;
use Sisow\Payment\PaymentMethods\IdealQrMethod;
use Sisow\Payment\PaymentMethods\KbcMethod;
use Sisow\Payment\PaymentMethods\KlarnaMethod;
use Sisow\Payment\PaymentMethods\MaestroMethod;
use Sisow\Payment\PaymentMethods\MastercardMethod;
use Sisow\Payment\PaymentMethods\OverboekingMethod;
use Sisow\Payment\PaymentMethods\PayPalMethod;
use Sisow\Payment\PaymentMethods\SofortMethod;
use Sisow\Payment\PaymentMethods\SpraypayMethod;
use Sisow\Payment\PaymentMethods\VisaMethod;
use Sisow\Payment\PaymentMethods\VpayMethod;

class PaymentMethodInstaller implements InstallerInterface
{
    public const IS_SISOW = 'is_sisow';
    public const TEMPLATE = 'template';
    public const PAYMENT_CODE = 'sisow_payment_code';

    /** @var PluginIdProvider */
    public $pluginIdProvider;
    /** @var EntityRepositoryInterface */
    public $paymentMethodRepository;

    /** @var EntityRepositoryInterface */
    private $mediaRepository;

    /** @var FileSaver */
    private $fileSaver;

    /**
     * PaymentMethodsInstaller constructor.
     * @param ContainerInterface $container
     */
    public function __construct(ContainerInterface $container)
    {
        $this->pluginIdProvider = $container->get(PluginIdProvider::class);
        $this->paymentMethodRepository = $container->get('payment_method.repository');
        $this->mediaRepository = $container->get('media.repository');
        $this->fileSaver = $container->get(FileSaver::class);
    }

    /**
     * @param InstallContext $context
     */
    public function install(InstallContext $context): void
    {
        foreach ($this->getPaymentMethods() as $paymentMethod) {
            $this->installPaymentMethod($context->getContext(), new $paymentMethod(), false);
            $this->addMedia($context->getContext(), new $paymentMethod());
        }
    }

    /**
     * @param UpdateContext $context
     */
    public function update(UpdateContext $context): void
    {
        foreach ($this->getPaymentMethods() as $paymentMethod) {
            $this->installPaymentMethod($context->getContext(), new $paymentMethod(), $context->getPlugin()->isActive());
            $this->addMedia($context->getContext(), new $paymentMethod());
        }
    }

    /**
     * @param UninstallContext $context
     */
    public function uninstall(UninstallContext $context): void
    {
        foreach ($this->getPaymentMethods() as $paymentMethod) {
            $this->setPaymentMethodActive($context->getContext(), new $paymentMethod(), false);
        }
    }

    /**
     * @param ActivateContext $context
     */
    public function activate(ActivateContext $context): void
    {
        foreach ($this->getPaymentMethods() as $paymentMethod) {
            $this->setPaymentMethodActive($context->getContext(), new $paymentMethod(), true);
        }
    }

    /**
     * @param DeactivateContext $context
     */
    public function deactivate(DeactivateContext $context): void
    {
        foreach ($this->getPaymentMethods() as $paymentMethod) {
            $this->setPaymentMethodActive($context->getContext(), new $paymentMethod(), false);
        }
    }

    /**
     * @param Context $context
     * @param PaymentMethodInterface $paymentMethod
     * @param bool $active
     */
    private function setPaymentMethodActive(Context $context, PaymentMethodInterface $paymentMethod, bool $active): void
    {
        $paymentMethodId = $this->getPaymentMethodId($paymentMethod, $context);

        if (!$paymentMethodId)
            return;

        $data = [
            'id'     => $paymentMethodId,
            'active' => $active,
        ];

        $this->paymentMethodRepository->upsert([$data], $context);
    }

    /**
     * @return string[]
     */
    private function getPaymentMethods(): array
    {
        $paymentMethods = [
            AfterPayMethod::class,
            BancontactMethod::class,
            BelfiusMethod::class,
            BillinkMethod::class,
            BunqMethod::class,
            CapayableMethod::class,
            CbcMethod::class,
            EpsMethod::class,
            GiropayMethod::class,
            HomepayMethod::class,
            IdealMethod::class,
            IdealQrMethod::class,
            KbcMethod::class,
            KlarnaMethod::class,
            MaestroMethod::class,
            MastercardMethod::class,
            OverboekingMethod::class,
            PayPalMethod::class,
            SofortMethod::class,
            SpraypayMethod::class,
            VisaMethod::class,
            VpayMethod::class,
        ];

        return $paymentMethods;
    }

    /**
     * @param Context $context
     * @param PaymentMethodInterface $paymentMethod
     * @param bool $active
     */
    private function installPaymentMethod(Context $context, PaymentMethodInterface $paymentMethod, bool $active): void {
        $paymentMethodId = $this->getPaymentMethodId($paymentMethod, $context);

        $pluginId = $this->pluginIdProvider->getPluginIdByBaseClass(SisowPayment::class, $context);

        $customFields = [
            self::TEMPLATE      => $paymentMethod->getTemplate(),
            self::IS_SISOW      => true,
            self::PAYMENT_CODE  => $paymentMethod->getPaymentCode(),
        ];

        $mediaId = $this->getMediaId($paymentMethod, $context);

        $options = [
            'id'                => $paymentMethodId,
            'name'              => $paymentMethod->getName(),
            'description'       => $paymentMethod->getDescription(),
            'handlerIdentifier' => $paymentMethod->getPaymentHandler(),
            'position'          => $paymentMethod->getPosition(),
            'pluginId'          => $pluginId,
            'mediaId'           => $mediaId,
            'customFields'      => $customFields,
            'translations'      => $paymentMethod->getTranslations(),
        ];

        if ($paymentMethodId === null && $active) {
            $options['active'] = true;
        }

        $this->paymentMethodRepository->upsert([$options], $context);
    }


    /**
     * @param PaymentMethodInterface $paymentMethod
     * @param Context $context
     * @throws \Shopware\Core\Content\Media\Exception\DuplicatedMediaFileNameException
     * @throws \Shopware\Core\Content\Media\Exception\EmptyMediaFilenameException
     * @throws \Shopware\Core\Content\Media\Exception\IllegalFileNameException
     * @throws \Shopware\Core\Content\Media\Exception\MediaNotFoundException
     */
    private function addMedia(Context $context, PaymentMethodInterface $paymentMethod): void
    {
        $mediaFile = __DIR__  . '/../Resources/views/storefront/sisow/logo/'.$paymentMethod->getPaymentCode().'.png';
        if (!file_exists($mediaFile)) {
            return;
        }

        if ($this->hasMediaAlreadyInstalled($paymentMethod, $context)) {
            return;
        }

        $mediaFile = $this->createMediaFile($mediaFile);
        $mediaId = Uuid::randomHex();

        $this->mediaRepository->create(
            [
                [
                    'id' => $mediaId
                ]
            ],
            $context
        );

        $this->fileSaver->persistFileToMedia(
            $mediaFile,
            $this->getMediaName($paymentMethod),
            $mediaId,
            $context
        );
    }


    /**
     * @param string $filePath
     * @return MediaFile
     */
    private function createMediaFile(string $filePath): MediaFile
    {
        return new MediaFile(
            $filePath,
            mime_content_type($filePath),
            pathinfo($filePath, PATHINFO_EXTENSION),
            filesize($filePath)
        );
    }

    /**
     * @param PaymentMethodInterface $paymentMethod
     * @param Context $context
     * @return bool
     * @throws \Shopware\Core\Framework\DataAbstractionLayer\Exception\InconsistentCriteriaIdsException
     */
    private function hasMediaAlreadyInstalled(PaymentMethodInterface $paymentMethod, Context $context) : bool
    {
        $criteria = (new Criteria())->addFilter(
            new EqualsFilter(
                'fileName',
                $this->getMediaName($paymentMethod)
            )
        );

        /** @var MediaEntity $media */
        $media = $this->mediaRepository->search($criteria, $context)->first();

        return $media ? true : false;
    }

    /**
     * @param PaymentMethodInterface $paymentMethod
     * @param Context $context
     * @return string|null
     * @throws \Shopware\Core\Framework\DataAbstractionLayer\Exception\InconsistentCriteriaIdsException
     */
    private function getMediaId(PaymentMethodInterface $paymentMethod, Context $context): ?string
    {
        $criteria = (new Criteria())->addFilter(
            new EqualsFilter(
                'fileName',
                $this->getMediaName($paymentMethod)
            )
        );

        /** @var MediaEntity $media */
        $media = $this->mediaRepository->search($criteria, $context)->first();

        if (!$media) {
            return null;
        }

        return $media->getId();
    }

    /**
     * @param PaymentMethodInterface $paymentMethod
     * @return string
     */
    private function getMediaName(PaymentMethodInterface $paymentMethod): string
    {
        return 'sisow_' . $paymentMethod->getPaymentCode();
    }

    /**
     * @param PaymentMethodInterface $paymentMethod
     * @param Context $context
     * @return string|null
     */
    private function getPaymentMethodId(PaymentMethodInterface $paymentMethod, Context $context): ?string
    {
        // Fetch ID for update
        $paymentCriteria = (new Criteria())->addFilter(new EqualsFilter('handlerIdentifier', $paymentMethod->getPaymentHandler()));
        $paymentIds = $this->paymentMethodRepository->searchIds(
            $paymentCriteria,
            $context
        );

        if ($paymentIds->getTotal() === 0) {
            return null;
        }

        return $paymentIds->getIds()[0];
    }
}