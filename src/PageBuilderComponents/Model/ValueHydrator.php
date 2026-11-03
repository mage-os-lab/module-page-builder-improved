<?php

declare(strict_types=1);

namespace MageOS\PageBuilderComponents\Model;

use Magento\Cms\Model\Template\FilterProvider;
use Magento\Email\Model\Template\Filter as EmailTemplateFilter;
use Magento\Framework\UrlInterface;
use Magento\Store\Model\Store;
use Magento\Store\Model\StoreManagerInterface;
use MageOS\PageBuilderComponents\Field\ImageField;
use MageOS\PageBuilderComponents\Field\SelectField;
use MageOS\PageBuilderComponents\Field\UrlField;
use MageOS\PageBuilderComponents\Field\WysiwygField;
use MageOS\PageBuilderComponents\Model\Value\Link;

/**
 * Turns a component's stored field values into the values of its properties.
 *
 * A field missing from the stored data keeps its property's default.
 */
class ValueHydrator
{
    /**
     * @param DirectiveValueCodec $codec
     * @param LinkResolver $linkResolver
     * @param StoreManagerInterface $storeManager
     * @param FilterProvider $filterProvider
     */
    public function __construct(
        private readonly DirectiveValueCodec $codec,
        private readonly LinkResolver $linkResolver,
        private readonly StoreManagerInterface $storeManager,
        private readonly FilterProvider $filterProvider,
    ) {
    }

    /**
     * Property values from the stored (encoded) data, for the fields that have a value.
     *
     * @param ComponentDefinition $definition
     * @param array $data Block data, as the widget directive passes it.
     * @phpstan-param array<string, mixed> $data
     * @return array<string, mixed> Property name => value.
     */
    public function hydrate(ComponentDefinition $definition, array $data): array
    {
        $values = [];
        foreach ($definition->fields as $name => $fieldDefinition) {
            $stored = $data[$name] ?? null;
            if (!\is_scalar($stored)) {
                continue;
            }
            $value = $this->convert($fieldDefinition, $this->codec->decode((string) $stored));
            if ($value !== null || $fieldDefinition->nullable) {
                $values[$name] = $value;
            }
        }

        return $values;
    }

    /**
     * Convert one decoded value. Null means "keep the default", unless the property is nullable.
     *
     * @param FieldDefinition $definition
     * @param string $value
     * @return mixed
     */
    private function convert(FieldDefinition $definition, string $value): mixed
    {
        $field = $definition->field;

        return match (true) {
            $field instanceof UrlField => $this->convertLink($definition, $value),
            $field instanceof ImageField => $this->toMediaUrl($value),
            $field instanceof WysiwygField => $value !== '' ? $this->filterHtml($value) : '',
            $field instanceof SelectField && !\array_key_exists($value, $field->getOptions()) => null,
            default => $this->cast($definition->propertyType, $value),
        };
    }

    /**
     * A link field's value: a Link, or its URL for a string property.
     *
     * @param FieldDefinition $definition
     * @param string $value
     * @return Link|string|null
     */
    private function convertLink(FieldDefinition $definition, string $value): Link|string|null
    {
        $link = $this->linkResolver->resolve($value);
        if ($definition->propertyType === Link::class) {
            return $link;
        }

        return $link !== null ? $link->url : '';
    }

    /**
     * Cast to the property's builtin type; null when the value does not fit it.
     *
     * @param string $type
     * @param string $value
     * @return string|int|bool|null
     */
    private function cast(string $type, string $value): string|int|bool|null
    {
        return match ($type) {
            'bool' => match ($value) {
                '1', 'true' => true,
                '0', 'false' => false,
                default => null,
            },
            'int' => preg_match('/^-?\d+$/', $value) ? (int) $value : null,
            default => $value,
        };
    }

    /**
     * Full URL of a path under the media directory. A full http(s) URL, or an absolute path on this site, is kept
     * as it is. Anything else that is not a plain path (another scheme such as `javascript:` or `data:`, a
     * protocol-relative `//host`, a backslash or a control character) gives no image.
     *
     * @param string $path
     * @return string
     */
    private function toMediaUrl(string $path): string
    {
        $path = trim($path);
        if ($path === '' || preg_match('/[\x00-\x1f\x7f\\\\]/', $path)) {
            return '';
        }
        if (preg_match('#^https?://[^/]#i', $path)) {
            return $path;
        }
        if (str_starts_with($path, '//') || preg_match('/^[a-z][a-z0-9+.-]*:/i', $path)) {
            return '';
        }
        if (str_starts_with($path, '/')) {
            return $path;
        }
        /** @var Store $store */
        $store = $this->storeManager->getStore();

        return $store->getBaseUrl(UrlInterface::URL_TYPE_MEDIA) . $path;
    }

    /**
     * Run HTML through the CMS block filter of the current store.
     *
     * @param string $html
     * @return string
     */
    private function filterHtml(string $html): string
    {
        $filter = $this->filterProvider->getBlockFilter();
        // Typed as the framework filter; the CMS block filter it returns takes a store.
        if ($filter instanceof EmailTemplateFilter) {
            $filter->setStoreId((int) $this->storeManager->getStore()->getId());
        }

        return $filter->filter($html);
    }
}
