define([
    'jquery',
    'underscore',
    'shopify/form/configuration/exportmapping/modal',
    'pim/user-context',
    'oro/translator',
    'pim/fetcher-registry',
    'pim/initselect2',
    'shopify/template/configuration/exportmapping/product/shopifyRelated'
], function(
    $,
    _,
    BaseModal,
    UserContext,
    __,
    FetcherRegistry,
    initSelect2,
    template
    ) {

    return BaseModal.extend({
        options: {},
        template: _.template(template),
        events: {
            'change select': 'updateModel'
        },

        /**
         * Model update callback
         */
        updateModel() {
            const model = this.getFormModel();
        },

        /**
         * Renders the form
         *
         * @return {Promise}
         */
        render() {
            console.log('here', this.getFormData());
            if (!this.configured) return this;

            const fetcher = FetcherRegistry.getFetcher('shopify-product-relatedid');
            
            fetcher.fetchAll().then(function (shopifyproducts) {
                this.$el.html(this.template({
                    label: __('webkul_shopify_connector.form.configuration.export_mapping.properties.shopify_product_relatedId'),
                    shopifyproducts: shopifyproducts,
                    required:__('pim_enrich.form.required'),
                    error: this.parent.validationErrors['shopifyproducts'],
                    type: this.getFormData().type
                }));
                initSelect2.init(this.$('select'))
            }.bind(this));

            this.delegateEvents();
        }
    });
});
