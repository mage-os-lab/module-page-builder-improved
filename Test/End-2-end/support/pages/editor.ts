import { expect, type Locator, type Page } from '@playwright/test';
import { adminUrl } from '../env';

export type Viewport = 'desktop' | 'tablet' | 'mobile';

/**
 * Page Builder on a CMS page's edit page, with MageOS_PageBuilderUi's panel, tree, header and edit form.
 * Selectors are the modules' own classes (the ones Test/Screenshots/shot.js checks) and core Page Builder's.
 */
export class PageBuilderEditor {
    readonly stageWrapper: Locator;
    readonly stage: Locator;
    readonly canvas: Locator;
    readonly panel: Locator;
    readonly collapseButton: Locator;
    readonly search: Locator;
    readonly searchResults: Locator;
    readonly sectionList: Locator;
    readonly sections: Locator;
    readonly treeNodes: Locator;
    readonly savePageButton: Locator;
    readonly fullScreenButton: Locator;

    constructor(readonly page: Page) {
        this.stageWrapper = page.locator('.pagebuilder-stage-wrapper.mageos-panel-host');
        this.stage = page.locator('.pagebuilder-stage');
        this.canvas = page.locator('.pagebuilder-canvas');
        this.panel = page.locator('.mageos-panel');
        this.collapseButton = page.locator('.mageos-panel-collapse');
        this.search = page.locator('#search-content-types-input');
        this.searchResults = page.locator('#search-results .mageos-inserter-item');
        this.sectionList = page.locator('#pagebuilder-panel-menu-sections');
        this.sections = this.sectionList.locator('> li');
        this.treeNodes = page.locator('.mageos-panel-tree .mageos-tree-node');
        this.savePageButton = page.locator('.mageos-save-page');
        this.fullScreenButton = page.locator('.mageos-fullscreen-toggle');
    }

    /**
     * Opens a CMS page's edit page and waits for its stage. In full screen through "Edit with Page Builder"'s
     * fragment, as the grid action does; otherwise by opening the Content fieldset, as an editor would, where
     * Page Builder shows the stage inline, without the panel.
     */
    async open(pageId: number, { fullScreen = true } = {}): Promise<void> {
        await this.page.goto(adminUrl(`cms/page/edit/page_id/${pageId}/`) + (fullScreen ? '#edit-with-page-builder' : ''));
        if (!fullScreen) {
            await this.page.locator('[data-index="content"] .fieldset-wrapper-title').click();
        }
        await this.waitForStage({ withPanel: fullScreen });
    }

    /** Waits for the stage to render its content, and in full screen for the panel. */
    async waitForStage({ withPanel = true } = {}): Promise<void> {
        await expect(this.stage.locator('.pagebuilder-root-container')).toBeVisible({ timeout: 60_000 });
        await expect(this.stage.locator('.pagebuilder-stage-loading')).toBeHidden();
        if (withPanel) {
            await expect(this.panel).toBeVisible();
        }
    }

    stageElements(contentType: string): Locator {
        return this.stage.locator(`[data-content-type="${contentType}"]`);
    }

    tab(name: 'add' | 'tree'): Locator {
        return this.page.locator(`.mageos-panel-tab[data-tab="${name}"]`);
    }

    async openTree(): Promise<void> {
        await this.tab('tree').click();
        await expect(this.page.locator('.mageos-panel-tree')).toBeVisible();
    }

    /** A tile in the inserter's sections, by its label. */
    tile(label: string): Locator {
        return this.page.locator('#pagebuilder-panel-menu-sections .mageos-inserter-item')
            .filter({ has: this.page.locator('.content-type-label', { hasText: new RegExp(`^\\s*${escape(label)}\\s*$`) }) });
    }

    /** The tree's nodes for one content type (e.g. 'heading', 'column-group', 'mageos_hero'). */
    treeNode(contentType: string): Locator {
        return this.treeNodes.and(this.page.locator(`[data-content-type="${contentType}"]`));
    }

    /**
     * Selects a tree node. By its name: on hover, the node's actions cover the end of the row, the label's middle
     * included.
     */
    async selectNode(node: Locator): Promise<void> {
        await node.locator('.mageos-tree-name').click();
    }

    /** Runs one of a tree node's actions (they show over the row on hover). */
    async treeAction(node: Locator, action: 'move-up' | 'move-down' | 'duplicate' | 'edit' | 'remove'): Promise<void> {
        await node.hover();
        await node.locator(`.mageos-tree-${action}`).click();
    }

    /** Opens an element's edit form from the tree. */
    async editFromTree(contentType: string, index = 0): Promise<EditForm> {
        await this.openTree();
        await this.treeAction(this.treeNode(contentType).nth(index), 'edit');
        const form = new EditForm(this.page);

        await form.waitForOpen();

        return form;
    }

    /** Accepts core's "Confirm Item Removal" dialog. */
    async confirmRemoval(): Promise<void> {
        const dialog = this.page.locator('.modal-popup._show').filter({ hasText: 'Confirm Item Removal' });

        await dialog.locator('.action-accept').click();
        await expect(dialog).toBeHidden();
    }

    viewportButton(viewport: Viewport): Locator {
        return this.page.locator(`.page-builder-viewport.${viewport}-switcher`);
    }

    async switchViewport(viewport: Viewport): Promise<void> {
        await this.viewportButton(viewport).click();
        await expect(this.stageWrapper).toHaveClass(new RegExp(`\\b${viewport}-viewport\\b`));
    }

    /**
     * Drags a tile from the inserter into a stage container (e.g. a row's inner element), at its end. jQuery UI's
     * draggable needs real, stepped mouse moves: a small first move starts the drag, then the way to the target.
     * Released once the stage shows where the element will go.
     *
     * The stage takes drops a moment after it looks ready, with nothing in the DOM to wait for: a drag that
     * starts too early shows no placeholder. It is then released where nothing takes it, which adds nothing, and
     * tried again.
     */
    async dragTileTo(label: string, target: Locator, attempts = 3): Promise<void> {
        const tile = this.tile(label);
        const placeholder = this.stage.locator('.pagebuilder-sortable-placeholder');

        await expect(tile).toHaveClass(/\bui-draggable\b/);
        for (let attempt = 1; attempt <= attempts; attempt++) {
            const from = await tile.boundingBox();
            const to = await target.boundingBox();

            if (!from || !to) {
                throw new Error(`dragTileTo(${label}): the tile or the target is not visible`);
            }
            const start = { x: from.x + from.width / 2, y: from.y + from.height / 2 };

            await this.page.mouse.move(start.x, start.y);
            await this.page.mouse.down();
            await this.page.mouse.move(start.x + 10, start.y + 10, { steps: 5 });
            await this.page.mouse.move(to.x + to.width / 2, to.y + to.height - 5, { steps: 25 });
            const placed = await placeholder.waitFor({ state: 'attached', timeout: 2_000 }).then(() => true, () => false);

            if (placed) {
                await this.page.mouse.up();

                return;
            }
            await this.page.mouse.move(start.x, start.y, { steps: 5 });
            await this.page.mouse.up();
            await this.page.waitForTimeout(500);
        }
        throw new Error(`dragTileTo(${label}): the stage showed no drop placeholder in ${attempts} attempts`);
    }

    /**
     * "Save page" in the header: it submits the CMS form, which reloads the edit page with its message. Waits for
     * that page and its stage.
     */
    async savePage(): Promise<void> {
        await this.savePageButton.click();
        await expect(this.page.locator('.message-success')).toContainText('You saved the page', { timeout: 60_000 });
        await this.page.waitForLoadState('load');
    }

    /** The page's messages after a save, e.g. warnings next to the success. */
    messages(): Locator {
        return this.page.locator('#messages .message');
    }
}

/** An element's edit form: core's slide-in modal, a tabbed sidebar with MageOS_PageBuilderUi. */
export class EditForm {
    readonly root: Locator;
    readonly title: Locator;
    readonly tabs: Locator;

    constructor(private readonly page: Page) {
        this.root = page.locator('.mageos-pb-edit-modal._show');
        this.title = this.root.locator('.modal-title');
        this.tabs = this.root.locator('.mageos-form-tab-button');
    }

    async waitForOpen(): Promise<void> {
        await expect(this.root).toBeVisible();
        // Magento_Ui renders the form asynchronously; the tabs follow it.
        await expect(this.tabs.first()).toBeVisible();
    }

    tab(label: string): Locator {
        return this.tabs.filter({ hasText: new RegExp(`^\\s*${escape(label)}\\s*$`) });
    }

    /** A top level fieldset, by its data-index ('general' is the Content tab's). */
    fieldset(index: string): Locator {
        return this.root.locator(`[data-index="${index}"]`).first();
    }

    field(name: string): Locator {
        return this.root.locator(`[name="${name}"]`).first();
    }

    async fill(name: string, value: string): Promise<void> {
        await this.field(name).fill(value);
    }

    async select(name: string, value: string): Promise<void> {
        await this.field(name).selectOption(value);
    }

    async save(): Promise<void> {
        await this.root.locator('button.save.primary').click();
        await expect(this.root).toBeHidden();
    }

    async close(): Promise<void> {
        await this.root.locator('.action-close').first().click();
        await expect(this.root).toBeHidden();
    }
}

function escape(text: string): string {
    return text.replace(/[.*+?^${}()|[\]\\]/g, '\\$&');
}
