<?php

declare(strict_types=1);

namespace MageOS\PageBuilderComponents\Model;

use MageOS\PageBuilderComponents\Attribute\PageBuilderComponent;
use MageOS\PageBuilderComponents\Block\AbstractComponent;
use MageOS\PageBuilderComponents\Exception\InvalidComponentException;
use MageOS\PageBuilderComponents\Field\FieldInterface;
use MageOS\PageBuilderComponents\Field\SelectField;
use ReflectionAttribute;
use ReflectionClass;
use ReflectionNamedType;
use ReflectionProperty;
use Throwable;

/**
 * Reads a component's definition from the attributes on its block class, and refuses what cannot work.
 */
class DefinitionReader
{
    private const NAME_PATTERN = '/^[a-z][a-z0-9_]{0,63}$/';
    private const FIELD_PATTERN = '/^[a-zA-Z][a-zA-Z0-9_]{0,63}$/';
    private const TEMPLATE_PATTERN = '#^[A-Za-z0-9]+_[A-Za-z0-9]+::[A-Za-z0-9_\-/.]+\.phtml$#';
    private const MENU_SECTION_PATTERN = '/^[a-z][a-z0-9_]*$/';
    private const ICON_PATTERN = '/^[a-z0-9_-]+( [a-z0-9_-]+)*$/i';
    private const STYLESHEET_PATTERN = '/^[A-Z][A-Za-z0-9]*_[A-Za-z0-9]+::[\w\/.-]+\.css$/';

    /**
     * Widget directive parameters that core, or this module, gives a meaning of their own.
     */
    private const RESERVED_FIELDS = [
        'type', 'template', 'id', 'name', 'store_id', 'type_name', 'conditions', 'cache_lifetime', 'cache_key',
        'cache_tags', 'module_name', 'data', 'appearance', 'css_classes', 'margins_and_padding', 'html',
    ];

    /**
     * Read the definition of a component.
     *
     * @param string $registeredName Key the class is registered under on the registry.
     * @param string $class
     * @throws InvalidComponentException
     * @return ComponentDefinition
     */
    public function read(string $registeredName, string $class): ComponentDefinition
    {
        if (!class_exists($class)) {
            throw new InvalidComponentException(
                \sprintf('Page Builder component "%s": class %s does not exist.', $registeredName, $class)
            );
        }
        $reflection = new ReflectionClass($class);
        if (!$reflection->isSubclassOf(AbstractComponent::class) || $reflection->isAbstract()) {
            throw new InvalidComponentException(\sprintf(
                'Page Builder component "%s": %s must be a concrete subclass of %s.',
                $registeredName,
                $class,
                AbstractComponent::class
            ));
        }

        $attribute = $this->readComponentAttribute($registeredName, $reflection);
        $this->validateComponent($registeredName, $class, $attribute);

        return new ComponentDefinition(
            $attribute->name,
            $attribute->label,
            $attribute->template,
            $attribute->menuSection,
            $attribute->icon,
            $attribute->sortOrder,
            $reflection->getName(),
            $this->readFields($attribute->name, $reflection),
            array_values($attribute->stylesheets)
        );
    }

    /**
     * Read the class's component attribute.
     *
     * @param string $registeredName
     * @param ReflectionClass<AbstractComponent> $reflection
     * @return PageBuilderComponent
     */
    private function readComponentAttribute(string $registeredName, ReflectionClass $reflection): PageBuilderComponent
    {
        $attributes = $reflection->getAttributes(PageBuilderComponent::class);
        if (\count($attributes) !== 1) {
            throw new InvalidComponentException(\sprintf(
                'Page Builder component "%s": %s must have one #[%s] attribute.',
                $registeredName,
                $reflection->getName(),
                PageBuilderComponent::class
            ));
        }

        try {
            return $attributes[0]->newInstance();
        } catch (Throwable $e) {
            throw new InvalidComponentException(
                \sprintf('Page Builder component "%s": %s', $registeredName, $e->getMessage()),
                0,
                $e
            );
        }
    }

    /**
     * Refuse names and paths that would break the content type, the directive or the template lookup.
     *
     * @param string $registeredName
     * @param string $class
     * @param PageBuilderComponent $attribute
     * @return void
     */
    private function validateComponent(string $registeredName, string $class, PageBuilderComponent $attribute): void
    {
        $problem = match (true) {
            !preg_match(self::NAME_PATTERN, $attribute->name) =>
                'its name must be lowercase letters, digits and "_", starting with a letter, up to 64 characters',
            $attribute->name !== $registeredName => \sprintf(
                'it is registered as "%s" but named "%s"; use the same name',
                $registeredName,
                $attribute->name
            ),
            trim($attribute->label) === '' => 'its label is empty',
            !preg_match(self::TEMPLATE_PATTERN, $attribute->template) =>
                'its template must look like "Vendor_Module::path/to/file.phtml"',
            !preg_match(self::MENU_SECTION_PATTERN, $attribute->menuSection) =>
                'its menu section must be lowercase letters, digits and "_"',
            !preg_match(self::ICON_PATTERN, $attribute->icon) =>
                'its icon must be CSS class names (letters, digits, "_" and "-"), separated by single spaces',
            // Page Builder's content type schema types sortOrder as xs:byte.
            $attribute->sortOrder < 0 || $attribute->sortOrder > 127 => 'its sort order must be from 0 to 127',
            default => $this->findInvalidStylesheet($attribute->stylesheets),
        };

        if ($problem !== null) {
            throw new InvalidComponentException(
                \sprintf('Page Builder component "%s" (%s): %s.', $registeredName, $class, $problem)
            );
        }
    }

    /**
     * The problem with the first stylesheet that is not a view asset id like `Vendor_Module::css/file.css`.
     *
     * @param array<mixed> $stylesheets
     * @return string|null
     */
    private function findInvalidStylesheet(array $stylesheets): ?string
    {
        foreach ($stylesheets as $stylesheet) {
            if (!\is_string($stylesheet) || !preg_match(self::STYLESHEET_PATTERN, $stylesheet)
                || str_contains($stylesheet, '..')
            ) {
                return \sprintf(
                    'its stylesheet %s must look like "Vendor_Module::css/file.css"',
                    json_encode($stylesheet, JSON_UNESCAPED_SLASHES)
                );
            }
        }

        return null;
    }

    /**
     * Read the fields from the class's public properties.
     *
     * @param string $componentName
     * @param ReflectionClass<AbstractComponent> $reflection
     * @return array<string, FieldDefinition>
     */
    private function readFields(string $componentName, ReflectionClass $reflection): array
    {
        $fields = [];
        foreach ($reflection->getProperties() as $property) {
            $attributes = $property->getAttributes(FieldInterface::class, ReflectionAttribute::IS_INSTANCEOF);
            if ($attributes === []) {
                continue;
            }
            $fail = static function (string $problem) use ($componentName, $property): never {
                throw new InvalidComponentException(\sprintf(
                    'Page Builder component "%s", field $%s: %s.',
                    $componentName,
                    $property->getName(),
                    $problem
                ));
            };
            if (\count($attributes) > 1) {
                $fail('a property can have one field attribute');
            }
            try {
                $field = $attributes[0]->newInstance();
            } catch (Throwable $e) {
                $fail($e->getMessage());
            }
            $fields[$property->getName()] = $this->readField($property, $field, $fail);
        }

        uasort(
            $fields,
            static fn (FieldDefinition $a, FieldDefinition $b): int
                => $a->field->getSortOrder() <=> $b->field->getSortOrder()
        );

        return $fields;
    }

    /**
     * Read one field, checking its property can hold what the field hydrates.
     *
     * @param ReflectionProperty $property
     * @param FieldInterface $field
     * @param callable $fail Throws with the problem it is given.
     * @phpstan-param callable(string): never $fail
     * @return FieldDefinition
     */
    private function readField(ReflectionProperty $property, FieldInterface $field, callable $fail): FieldDefinition
    {
        $name = $property->getName();
        if (!preg_match(self::FIELD_PATTERN, $name)) {
            $fail('the name must be letters, digits and "_", starting with a letter, up to 64 characters');
        }
        if (\in_array(strtolower($name), self::RESERVED_FIELDS, true)) {
            $fail(\sprintf('"%s" is reserved; rename the property', $name));
        }
        if (!$property->isPublic() || $property->isStatic() || $property->isReadOnly()) {
            $fail('the property must be public, not static and not readonly');
        }

        $type = $property->getType();
        $accepted = $field->getPropertyTypes();
        if (!$type instanceof ReflectionNamedType || !\in_array($type->getName(), $accepted, true)) {
            $fail(\sprintf('the property must be typed %s', implode(' or ', $accepted)));
        }
        if (!$property->hasDefaultValue()) {
            $fail('the property needs a default value');
        }

        $default = $property->getDefaultValue();
        if (\is_object($default) || (!$type->isBuiltin() && $default !== null)) {
            $fail('an object property must default to null');
        }
        $default = match (true) {
            $default === null => '',
            \is_bool($default) => $default ? '1' : '0',
            default => (string) $default,
        };

        if ($field instanceof SelectField
            && $default !== ''
            && !\array_key_exists($default, $field->getOptions())
        ) {
            $fail(\sprintf('the default "%s" is not one of the options', $default));
        }

        return new FieldDefinition($name, $field, $type->getName(), $type->allowsNull(), $default);
    }
}
