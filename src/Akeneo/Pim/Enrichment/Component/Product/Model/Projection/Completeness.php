<?php

declare(strict_types=1);

namespace Akeneo\Pim\Enrichment\Component\Product\Model\Projection;

/**
 * @author    Mathias METAYER <mathias.metayer@akeneo.com>
 * @copyright 2019 Akeneo SAS (http://www.akeneo.com)
 * @license   http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */
interface Completeness
{
    public function channelCode(): string;

    public function localeCode(): string;

    public function requiredCount(): int;

    public function missingCount(): int;

    public function missingAttributeCodes(): array;

    public function ratio(): int;
}
