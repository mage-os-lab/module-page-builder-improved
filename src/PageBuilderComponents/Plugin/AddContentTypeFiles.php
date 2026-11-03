<?php

declare(strict_types=1);

namespace MageOS\PageBuilderComponents\Plugin;

use Magento\PageBuilder\Model\Config\FileResolver;
use MageOS\PageBuilderComponents\Model\ComponentRegistry;
use MageOS\PageBuilderComponents\Model\Config\ContentTypeXmlGenerator;

/**
 * Gives Page Builder's config reader one content type file per component, built from its attributes.
 *
 * They are merged before the modules' files, so a module can extend or change a component's content type with a
 * `content_type/<name>.xml` of its own. The flip side: a component named like an existing content type would
 * be merged into it, which is why component names should carry a vendor prefix.
 */
class AddContentTypeFiles
{
    private const CONTENT_TYPE_FILES = 'content_type/*.xml';
    private const VIRTUAL_PATH = 'virtual://MageOS_PageBuilderComponents/pagebuilder/content_type/%s.xml';

    /**
     * @param ComponentRegistry $componentRegistry
     * @param ContentTypeXmlGenerator $contentTypeXmlGenerator
     */
    public function __construct(
        private readonly ComponentRegistry $componentRegistry,
        private readonly ContentTypeXmlGenerator $contentTypeXmlGenerator,
    ) {
    }

    /**
     * Add the components' content types to the content type files.
     *
     * @param FileResolver $subject
     * @param string[] $result Path => XML.
     * @param string $filename
     * @phpstan-param array<string, string> $result
     * @return string[]
     * @phpstan-return array<string, string>
     * @SuppressWarnings("PHPMD.UnusedFormalParameter")
     */
    public function afterGet(FileResolver $subject, array $result, $filename): array
    {
        if ($filename !== self::CONTENT_TYPE_FILES) {
            return $result;
        }

        $components = [];
        foreach ($this->componentRegistry->getAll() as $definition) {
            $components[\sprintf(self::VIRTUAL_PATH, $definition->name)]
                = $this->contentTypeXmlGenerator->generate($definition);
        }

        return $components + $result;
    }
}
