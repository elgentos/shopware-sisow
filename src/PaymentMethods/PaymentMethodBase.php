<?php declare(strict_types=1);

namespace Sisow\Payment\PaymentMethods;

abstract class PaymentMethodBase implements PaymentMethodInterface
{
    public function getDescription(): string
    {
        return sprintf('Pay save and secured with %s, provided by Sisow.',$this->getName());
    }

    public function getTranslations(): array
    {
        return [
            'de-DE' => [
                'name'        => $this->getName(),
                'description' => sprintf('Zahlen Sie sicher und gesichert mit %s, bereitgestellt von Sisow.',$this->getName()),
            ],
            'en-GB' => [
                'name'        => $this->getName(),
                'description' => $this->getDescription(),
            ],
            'nl-NL' => [
                'name'        => $this->getName(),
                'description' => sprintf('Betaal veilig en beveiligd met %s, geleverd door Sisow.',$this->getName()),
            ],
        ];
    }

    public function getTemplate(): ?string
    {
        return null;
    }

    public function getPosition(): int
    {
        return 1;
    }
}