import { test, expect } from '../support/fixtures';
import { heading, row, text } from '../support/content';

test.describe('Current tree', () => {
    test('lists the stage\'s elements, nested, with a summary of their text', async ({ seedPage, editor }) => {
        const cmsPage = await seedPage(row(heading('E2E heading'), text('<p>E2E text</p>')), 'tree');

        await editor.open(cmsPage.id);
        await editor.openTree();

        await expect(editor.treeNodes).toHaveCount(3);
        expect(await editor.treeNodes.evaluateAll((nodes) => nodes.map((node) => node.getAttribute('data-content-type'))))
            .toEqual(['row', 'heading', 'text']);
        const indents = await editor.treeNodes.evaluateAll((nodes) => nodes.map((node) => parseInt((node as HTMLElement).style.paddingLeft, 10)));

        expect(indents[1]).toBeGreaterThan(indents[0]);
        expect(indents[2]).toBe(indents[1]);
        await expect(editor.treeNode('heading').locator('.mageos-tree-summary')).toHaveText('E2E heading');
        await expect(editor.treeNode('row').locator('.mageos-tree-toggle')).toHaveAttribute('aria-expanded', 'true');
    });

    test('selects, duplicates and removes elements', async ({ seedPage, editor }) => {
        const cmsPage = await seedPage(row(heading('E2E heading'), text('<p>E2E text</p>')), 'tree-actions');

        await editor.open(cmsPage.id);
        await editor.openTree();

        const headingNode = editor.treeNode('heading').first();

        await editor.selectNode(headingNode);
        await expect(headingNode.locator('.mageos-tree-label')).toHaveAttribute('aria-current', 'true');
        await expect(editor.treeNodes.locator('.mageos-tree-label[aria-current="true"]')).toHaveCount(1);

        await editor.treeAction(headingNode, 'duplicate');
        await expect(editor.treeNode('heading')).toHaveCount(2);
        await expect(editor.stageElements('heading')).toHaveCount(2);

        await editor.treeAction(editor.treeNode('heading').nth(1), 'remove');
        await editor.confirmRemoval();
        await expect(editor.treeNode('heading')).toHaveCount(1);
        await expect(editor.stageElements('heading')).toHaveCount(1);
    });
});
