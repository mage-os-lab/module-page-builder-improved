<?php

declare(strict_types=1);

namespace MageOS\PageBuilderComponents\Test\Unit\Fixture;

/**
 * Names the invalid component fixtures, so tests can loop over them.
 *
 * Each class in Fixture/Invalid is wrong in exactly one way, which its docblock states.
 */
class Invalid
{
    /**
     * Fixture class => part of the expected message.
     *
     * @return array<string, array{string, string}> Class names, one of which does not exist on purpose.
     */
    public static function cases(): array
    {
        $ns = __NAMESPACE__ . '\\Invalid\\';

        return [
            'no attribute' => [$ns . 'NoAttribute', 'must have one #['],
            'not a component block' => [$ns . 'NotABlock', 'must be a concrete subclass'],
            'bad name' => [$ns . 'BadName', 'its name must be lowercase'],
            'bad template' => [$ns . 'BadTemplate', 'its template must look like'],
            'sort order out of range' => [$ns . 'BadSortOrder', 'its sort order must be from 0 to 127'],
            'icon not css classes' => [$ns . 'BadIcon', 'its icon must be CSS class names'],
            'stylesheet not an asset id' => [$ns . 'BadStylesheet', 'its stylesheet "https://evil.test/x.css" must'],
            'stylesheet outside the module' => [$ns . 'StylesheetOutsideModule', 'its stylesheet "Vendor_Module::../'],
            'reserved field' => [$ns . 'ReservedField', '"type" is reserved'],
            'protected field' => [$ns . 'ProtectedField', 'must be public'],
            'wrong property type' => [$ns . 'WrongType', 'must be typed bool'],
            'two field attributes' => [$ns . 'TwoFields', 'one field attribute'],
            'select default not an option' => [$ns . 'BadSelectDefault', 'is not one of the options'],
            'missing class' => [$ns . 'DoesNotExist', 'does not exist'],
        ];
    }
}
