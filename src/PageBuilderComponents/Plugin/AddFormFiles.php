<?php

declare(strict_types=1);

namespace MageOS\PageBuilderComponents\Plugin;

use Magento\Ui\Config\Reader\FileResolver;
use MageOS\PageBuilderComponents\Model\ComponentRegistry;
use MageOS\PageBuilderComponents\Model\Config\FormXmlGenerator;

/**
 * Gives the UI component reader each component's edit form, built from its attributes.
 *
 * Modules' `ui_component/<form>.xml` files for the same form are merged over it.
 */
class AddFormFiles
{
    private const VIRTUAL_PATH = 'virtual://MageOS_PageBuilderComponents/ui_component/%s.xml';

    /**
     * @param ComponentRegistry $componentRegistry
     * @param FormXmlGenerator $formXmlGenerator
     */
    public function __construct(
        private readonly ComponentRegistry $componentRegistry,
        private readonly FormXmlGenerator $formXmlGenerator,
    ) {
    }

    /**
     * Add the component's form to the files of a component form.
     *
     * @param FileResolver $subject
     * @param string[]|\Traversable $result Path => XML.
     * @param string $filename `<form name>.xml`
     * @phpstan-param array<string, string>|\Traversable<string, string> $result
     * @return string[]|\Traversable
     * @phpstan-return array<string, string>|\Traversable<string, string>
     * @SuppressWarnings("PHPMD.UnusedFormalParameter")
     */
    public function afterGet(FileResolver $subject, $result, $filename)
    {
        if (!\is_string($filename) || !str_ends_with($filename, '.xml')) {
            return $result;
        }
        $formName = substr($filename, 0, -4);
        $definition = $this->componentRegistry->getByFormName($formName);
        if ($definition === null) {
            return $result;
        }

        return [\sprintf(self::VIRTUAL_PATH, $formName) => $this->formXmlGenerator->generate($definition)]
            + (\is_array($result) ? $result : iterator_to_array($result));
    }
}
