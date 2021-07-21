<?php

declare(strict_types=1);

namespace Sisow\Payment\Storefront\Struct;

use Shopware\Core\Framework\Struct\Struct;

class CheckoutExtensionData extends Struct
{
    public const EXTENSION_NAME = 'sisow';

    /** @var null|string */
    protected $template;

    public function getTemplate(): ?string
    {
        return $this->template;
    }
}
