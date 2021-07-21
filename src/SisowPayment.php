<?php declare(strict_types=1);

namespace Sisow\Payment;

use Doctrine\DBAL\Connection;
use Sisow\Payment\Installers\PaymentMethodInstaller;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Plugin;
use Shopware\Core\Framework\Plugin\Context\ActivateContext;
use Shopware\Core\Framework\Plugin\Context\DeactivateContext;
use Shopware\Core\Framework\Plugin\Context\InstallContext;
use Shopware\Core\Framework\Plugin\Context\UninstallContext;
use Shopware\Core\Framework\Plugin\Context\UpdateContext;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Loader\XmlFileLoader;

class SisowPayment extends Plugin
{
    public function boot(): void
    {
        parent::boot();
    }

	public function build(ContainerBuilder $container): void
    {
        parent::build($container);

        //$loader = new XmlFileLoader($container, new FileLocator(__DIR__ . '/Resources/config'));
        //$loader->load('services.xml');

        $loader = new XmlFileLoader($container, new FileLocator(__DIR__ . '/DependencyInjection'));
        $loader->load('listeners.xml');
    }

    public function postInstall(InstallContext $context): void
    {
        parent::postInstall($context);

    }


    /**
     * @param InstallContext $context
     */
    public function install(InstallContext $context): void
    {
        (new PaymentMethodInstaller($this->container))->install($context);
    }

    public function update(UpdateContext $context): void {
        (new PaymentMethodInstaller($this->container))->update($context);
    }

    /**
     * @param UninstallContext $context
     */
    public function uninstall(UninstallContext $context): void
    {
        (new PaymentMethodInstaller($this->container))->uninstall($context);

        if ($context->keepUserData()) {
            return;
        }


        /** @var Connection $connection */
        if (false) {
            // Enable if re-run migrations works
            $connection = $this->container->get(Connection::class);
            $connection->exec('DROP TABLE sisow_payment_redirect');
        }

        //parent::uninstall($context);
    }

    /**
     * @param DeactivateContext $context
     */
    public function deactivate(DeactivateContext $context): void
    {
        (new PaymentMethodInstaller($this->container))->deactivate($context);
    }

    /**
     * @param ActivateContext $context
     */
    public function activate(ActivateContext $context): void
    {
        (new PaymentMethodInstaller($this->container))->activate($context);
    }
}
