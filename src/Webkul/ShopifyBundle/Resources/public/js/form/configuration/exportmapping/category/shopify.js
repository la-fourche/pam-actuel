define([
    'jquery',
    'underscore',
    'shopify/form/configuration/exportmapping/modal',
    'pim/user-context',
    'oro/translator',
    'pim/fetcher-registry',
    'pim/initselect2',
    'oro/loading-mask',
    'webkul/shopifyconnector/template/configuration/exportmapping/category/shopify'
], function(
    $,
    _,
    BaseForm,
    UserContext,
    __,
    FetcherRegistry,
    initSelect2,
    LoadingMask,
    template
    ) {

    return BaseForm.extend({
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
            const shopifyCategoryId = this.$('select').select2('val');
            model.set('shopifyCategoryId', shopifyCategoryId);
            model.set('shopifyCategoryName', this.$("select option:selected").text());
        },
    

        /**
         * Renders the form
         *
         * @return {Promise}
         */
         render() {
            if (!this.configured) return this;
            var loadingMask = new LoadingMask();
            loadingMask.render().$el.appendTo(this.getRoot().$el).show();
            const fetcher = FetcherRegistry.getFetcher('shopify-category');
            const shopifyCategoryId = this.getFormData().shopifyCategoryId;
            fetcher.fetchAll().then(function (categories) {
               const selectedShopifyCategoryId = shopifyCategoryId || (categories.length ? categories[0].id : 0);
                this.$el.html(this.template({
                    label: __('webkul_shopify_connector.form.configuration.export_mapping.properties.shopify_category'),
                    shopifyCategoryId: selectedShopifyCategoryId,
                    required: __('pim_enrich.form.required'),
                    categories: categories,
                    error: this.parent.validationErrors['shopifyCategoryId'],
                    type: this.getFormData().type
                }));
               
                this.getFormModel().set('shopifyCategoryId', selectedShopifyCategoryId);
                this.getFormModel().set('shopifyCategoryName', this.$("select option:selected").text());

                initSelect2.init(this.$('select'))
                loadingMask.hide().$el.remove();
            }.bind(this));

            this.delegateEvents();
        }
    });
});
