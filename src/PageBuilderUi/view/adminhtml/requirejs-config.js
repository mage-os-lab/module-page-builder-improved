/**
 * Page Builder's panel, header and edit form, with a fresh new UI.
 */
var config = {
    config: {
        mixins: {
            'Magento_PageBuilder/js/panel': {
                'MageOS_PageBuilderUi/js/panel-mixin': true
            },
            'Magento_PageBuilder/js/page-builder': {
                'MageOS_PageBuilderUi/js/page-builder-mixin': true
            },
            'Magento_PageBuilder/js/modal/modal': {
                'MageOS_PageBuilderUi/js/modal/modal-mixin': true
            }
        }
    }
};
