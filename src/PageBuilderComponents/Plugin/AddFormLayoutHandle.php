<?php

declare(strict_types=1);

namespace MageOS\PageBuilderComponents\Plugin;

use Magento\Framework\View\Model\Layout\Merge;
use MageOS\PageBuilderComponents\Model\ComponentRegistry;
use WeakMap;

/**
 * Gives each component's form the layout handle Page Builder loads it by.
 *
 * Core declares `pagebuilder_<type>_form.xml` layout files for its forms; this adds the same update for a
 * component's form when the handle is requested, so no layout file is needed.
 */
class AddFormLayoutHandle
{
    /**
     * Handles already given their update, per layout merge.
     *
     * @var WeakMap<Merge, array<string, true>>
     */
    private WeakMap $added;

    /**
     * @param ComponentRegistry $componentRegistry
     */
    public function __construct(
        private readonly ComponentRegistry $componentRegistry,
    ) {
        $this->added = new WeakMap();
    }

    /**
     * Add the form's layout update when its handle is added.
     *
     * @param Merge $subject
     * @param Merge $result
     * @param string[]|string $handleName
     * @return Merge
     */
    public function afterAddHandle(Merge $subject, Merge $result, $handleName): Merge
    {
        $added = $this->added[$subject] ?? [];
        foreach ((array) $handleName as $handle) {
            if (!\is_string($handle) || isset($added[$handle])) {
                continue;
            }
            $definition = $this->componentRegistry->getByFormName($handle);
            if ($definition === null) {
                continue;
            }
            $added[$handle] = true;

            $subject->addUpdate(\sprintf(
                '<update handle="styles"/><referenceContainer name="content"><uiComponent name="%s"/>'
                . '</referenceContainer>',
                $definition->getFormName()
            ));
        }
        $this->added[$subject] = $added;

        return $result;
    }
}
