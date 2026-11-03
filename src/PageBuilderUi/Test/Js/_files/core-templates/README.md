# Core templates the module copies

Magento_PageBuilder's `view/adminhtml/web/template/page-builder.html` and `panel.html`, as they were when
`view/adminhtml/web/template/page-builder.html` and `panel.html` were copied from them: magento/module-page-builder
2.2.6-p5, with line endings and trailing spaces normalised. They are `.txt`, so the HTML sniffs leave core's
markup alone.

`Test/Js/panel.test.js` fails when the installed core templates no longer match these, and shows what core
changed. Re-sync the copies with that change, then copy the new core templates here.
