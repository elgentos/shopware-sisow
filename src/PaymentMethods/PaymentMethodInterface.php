<?php declare(strict_types=1);

namespace Sisow\Payment\PaymentMethods;

interface PaymentMethodInterface
{
    public function getPaymentCode(): string;

    public function getName(): string;

    public function getDescription(): string;

    public function getPaymentHandler(): string;

    public function getTemplate(): ?string;

    public function getTranslations(): array;

    public function getPosition(): int;
}