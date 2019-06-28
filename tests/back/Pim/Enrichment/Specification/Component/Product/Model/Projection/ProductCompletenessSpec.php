<?php

namespace Specification\Akeneo\Pim\Enrichment\Component\Product\Model\Projection;

use Akeneo\Pim\Enrichment\Component\Product\Model\Projection\ProductCompleteness;
use PhpSpec\ObjectBehavior;

class ProductCompletenessSpec extends ObjectBehavior
{
    function let()
    {
        $this->beConstructedWith(
            'ecommerce',
            'fr_FR',
            30,
            ['name', 'brand', 'description', 'picture']
        );
    }

    function it_is_a_product_completeness()
    {
        $this->shouldHaveType(ProductCompleteness::class);
    }

    function it_throws_an_exception_if_required_count_is_zero()
    {
        $this->beConstructedWith(
            'ecommerce',
            'fr_FR',
            0,
            ['name', 'brand', 'description', 'picture']
        );
        $this->shouldThrow(\InvalidArgumentException::class)->duringInstantiation();
    }

    function it_throws_an_exception_if_required_count_is_negative()
    {
        $this->beConstructedWith(
            'ecommerce',
            'fr_FR',
            -5,
            ['name', 'brand', 'description', 'picture']
        );
        $this->shouldThrow(\InvalidArgumentException::class)->duringInstantiation();
    }

    function it_provides_a_completeness_ratio()
    {
        $this->ratio()->shouldReturn(86);
    }
}
