<?php

declare(strict_types=1);

namespace Akeneo\Pim\Enrichment\Component\Product\Model\Projection;

use IteratorAggregate;

/**
 * @author    Mathias METAYER <mathias.metayer@akeneo.com>
 * @copyright 2019 Akeneo SAS (http://www.akeneo.com)
 * @license   http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */
class ProductCompletenessCollection implements IteratorAggregate
{
    /** @var array */
    private $completenesses;

    public function getIterator()
    {
        return new \ArrayIterator($this->completenesses);
    }

    public function add(Completeness $completeness): void
    {
        $this->completenesses[$this->getKey($completeness)] = $completeness;
    }

    private function getKey(Completeness $completeness): string
    {
        return sprintf('%s-%s', $completeness->channelCode(), $completeness->localeCode());
    }
}
