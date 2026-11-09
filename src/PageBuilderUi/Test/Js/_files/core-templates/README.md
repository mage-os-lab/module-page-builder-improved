# Core templates the module copies

Magento_PageBuilder's `view/adminhtml/web/template/page-builder.html` and `panel.html`, as they were when
`view/adminhtml/web/template/page-builder.html` and `panel.html` were copied from them: magento/module-page-builder
2.2.6-p5, with line endings and trailing spaces normalised. They are `.txt`, so the HTML sniffs leave core's
markup alone.

`Test/Js/panel.test.js` fails when the installed core templates no longer match these, and shows what core
changed. Re-sync the copies with that change, then copy the new core templates here.

Page Builder 2.2.7 (Magento 2.4.9, Mage-OS 3.x) has the same templates as far as the page goes: only the license
comment at the top changed, and `<input .../>` became `<input ...>`. The test leaves both out of the comparison,
so these copies match 2.2.6 and 2.2.7.
