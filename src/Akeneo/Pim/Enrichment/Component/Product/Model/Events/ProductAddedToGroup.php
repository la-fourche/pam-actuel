<?php

declare(strict_types=1);

namespace Akeneo\Pim\Enrichment\Component\Product\Model\Events;

/**
 * @author    Mathias METAYER <mathias.metayer@akeneo.com>
 * @copyright 2019 Akeneo SAS (http://www.akeneo.com)
 * @license   http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */
class ProductAddedToGroup
{
    /** @var string */
    private $productIdentifier;

    /** @var string */
    private $groupCode;

    public function __construct(string $productIdentifier, string $groupCode)
    {
        $this->productIdentifier = $productIdentifier;
        $this->groupCode = $groupCode;
    }

    public function productIdentifier(): string
    {
        return $this->productIdentifier;
    }

    public function groupCode(): string
    {
        return $this->groupCode;
    }
}
