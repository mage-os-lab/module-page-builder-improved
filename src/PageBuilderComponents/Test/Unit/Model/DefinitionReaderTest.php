<?php

declare(strict_types=1);

namespace MageOS\PageBuilderComponents\Test\Unit\Model;

use MageOS\PageBuilderComponents\Exception\InvalidComponentException;
use MageOS\PageBuilderComponents\Field\InputField;
use MageOS\PageBuilderComponents\Field\SelectField;
use MageOS\PageBuilderComponents\Model\DefinitionReader;
use MageOS\PageBuilderComponents\Model\Value\Link;
use MageOS\PageBuilderComponents\Test\Unit\Fixture\AllFieldsComponent;
use MageOS\PageBuilderComponents\Test\Unit\Fixture\Invalid;
use MageOS\PageBuilderComponents\Test\Unit\Fixture\MinimalComponent;
use PHPUnit\Framework\TestCase;

class DefinitionReaderTest extends TestCase
{
    public function testReadsTheComponentAttribute(): void
    {
        $definition = (new DefinitionReader())->read('test_all_fields', AllFieldsComponent::class);

        self::assertSame('test_all_fields', $definition->name);
        self::assertSame('All <Fields> & "More"', $definition->label);
        self::assertSame('MageOS_PageBuilderComponents::test/all-fields.phtml', $definition->template);
        self::assertSame('elements', $definition->menuSection);
        self::assertSame('icon-pagebuilder-heading', $definition->icon);
        self::assertSame(5, $definition->sortOrder);
        self::assertSame(AllFieldsComponent::class, $definition->blockClass);
        self::assertSame('mageos_pbc_test_all_fields_form', $definition->getFormName());
        self::assertSame(
            ['MageOS_PageBuilderComponents::css/test/all-fields.css', 'Vendor_Module::css/other.min.css'],
            $definition->stylesheets
        );
    }

    public function testAppliesTheAttributeDefaults(): void
    {
        $definition = (new DefinitionReader())->read('test_minimal', MinimalComponent::class);

        self::assertSame('add_content', $definition->menuSection);
        self::assertSame('icon-pagebuilder-block', $definition->icon);
        self::assertSame(100, $definition->sortOrder);
        self::assertSame([], $definition->stylesheets);
    }

    public function testReadsFieldsInSortOrderAndSkipsPropertiesWithoutAField(): void
    {
        $fields = (new DefinitionReader())->read('test_all_fields', AllFieldsComponent::class)->fields;

        self::assertSame(
            ['title', 'count', 'intro', 'content', 'image', 'link', 'plainLink', 'layout', 'overlay', 'products'],
            array_keys($fields)
        );
        self::assertInstanceOf(InputField::class, $fields['title']->field);
        self::assertTrue($fields['title']->field->isRequired());
        self::assertInstanceOf(SelectField::class, $fields['layout']->field);
    }

    public function testRecordsPropertyTypesAndDefaultsAsStoredStrings(): void
    {
        $fields = (new DefinitionReader())->read('test_all_fields', AllFieldsComponent::class)->fields;

        self::assertSame(['string', false, 'Default title'], $this->describe($fields['title']));
        self::assertSame(['int', false, '3'], $this->describe($fields['count']));
        self::assertSame(['bool', false, '1'], $this->describe($fields['overlay']));
        self::assertSame([Link::class, true, ''], $this->describe($fields['link']));
        self::assertSame(['string', false, 'left'], $this->describe($fields['layout']));
    }

    public function testRefusesAComponentRegisteredUnderAnotherName(): void
    {
        $this->expectException(InvalidComponentException::class);
        $this->expectExceptionMessage('registered as "other_name" but named "test_minimal"');

        (new DefinitionReader())->read('other_name', MinimalComponent::class);
    }

    /**
     * @return array<string, array{string, string}>
     */
    public static function invalidComponents(): array
    {
        return Invalid::cases();
    }

    /**
     * @dataProvider invalidComponents
     */
    public function testRefusesAnInvalidComponentWithAMessageSayingWhy(string $class, string $message): void
    {
        $this->expectException(InvalidComponentException::class);
        $this->expectExceptionMessage($message);

        $name = str_ends_with($class, 'BadName') ? 'Test-Invalid' : 'test_invalid';
        (new DefinitionReader())->read($name, $class);
    }

    /**
     * @param \MageOS\PageBuilderComponents\Model\FieldDefinition $field
     * @return array{string, bool, string}
     */
    private function describe($field): array
    {
        return [$field->propertyType, $field->nullable, $field->default];
    }
}
