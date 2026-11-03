<?php

declare(strict_types=1);

namespace MageOS\PageBuilderComponents\Test\Unit;

use Magento\Framework\App\State;
use MageOS\PageBuilderComponents\Model\ComponentRegistry;
use MageOS\PageBuilderComponents\Model\DefinitionReader;
use MageOS\PageBuilderComponents\Test\Unit\Fixture\AllFieldsComponent;
use MageOS\PageBuilderComponents\Test\Unit\Fixture\MinimalComponent;
use Psr\Log\NullLogger;

/**
 * A registry of the valid fixture components, for tests of the classes that read it.
 */
class RegistryFactory
{
    /**
     * @return ComponentRegistry
     */
    public static function create(): ComponentRegistry
    {
        return new ComponentRegistry(
            new DefinitionReader(),
            (new \ReflectionClass(State::class))->newInstanceWithoutConstructor(),
            new NullLogger(),
            new ArrayCache(),
            [
                'test_all_fields' => AllFieldsComponent::class,
                'test_minimal' => MinimalComponent::class,
            ]
        );
    }
}
