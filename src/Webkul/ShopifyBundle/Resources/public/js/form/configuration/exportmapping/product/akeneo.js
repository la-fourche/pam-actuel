define([
    'jquery',
    'underscore',
    'shopify/form/configuration/exportmapping/modal',
    'pim/user-context',
    'oro/translator',
    'pim/fetcher-registry',
    'pim/initselect2',
    'shopify/template/configuration/exportmapping/product/akeneo'
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
            const akeneoProductSku = this.$('select').select2('val');
            model.set('akeneoProductSku', akeneoProductSku);
            model.set('akeneoProductName', this.$("select option:selected").text());
           
            
        },

        /**
         * Renders the form
         *
         * @return {Promise}
         */
        render() {
            if (!this.configured) return this;
            const fetcher = FetcherRegistry.getFetcher('akeneo-product');
           
            fetcher.fetchAll().then(function (products) {

                const akeneoProductSku = this.getFormData().akeneoProductSku;
                const akeneoProductName = this.getFormData().akeneoProductName;
                const selectedAkeneoProductSku = akeneoProductSku || (products.length ? products[0]:0);
                this.$el.html(this.template({
                    label: __('webkul_shopify_connector.form.configuration.export_mapping.properties.akeneo_product'),
                    productCodes: products,
                    akeneoProductSku: selectedAkeneoProductSku,
                    required: __('pim_enrich.form.required'),
                    error: this.parent.validationErrors['akeneoProductSku'],
                    type: this.getFormData().type,
                }));

                this.getFormModel().set('akeneoProductSku', selectedAkeneoProductSku);
                this.getFormModel().set('akeneoProductName',this.$("select option:selected").text());
                initSelect2.init(this.$('select'))
            }.bind(this));

            this.delegateEvents();
        }
    });
});
