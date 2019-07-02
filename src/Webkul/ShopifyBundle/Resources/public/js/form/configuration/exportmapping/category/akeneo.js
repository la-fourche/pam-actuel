define([
    'jquery',
    'underscore',
    'shopify/form/configuration/exportmapping/modal',
    'pim/user-context',
    'oro/translator',
    'pim/fetcher-registry',
    'pim/initselect2',
    'webkul/shopifyconnector/template/configuration/exportmapping/category/akeneo'
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
            const akeneoCategoryId = this.$('select').select2('val');
            model.set('akeneoCategoryId', akeneoCategoryId);
            model.set('akeneoCategoryName', this.$("select option:selected").text());
        },

        /**
         * Renders the form
         *
         * @return {Promise}
         */
        render() {
            
            if (!this.configured) return this;
            const fetcher = FetcherRegistry.getFetcher('akeneo-category');
            const akeneoCategoryId = this.getFormData().akeneoCategoryId;
            fetcher.fetchAll().then(function (categories) {
                const selectedAkeneoCategoryId = akeneoCategoryId || (categories.length ? categories[0].code : 0);

                this.$el.html(this.template({
                    label: __('webkul_shopify_connector.form.configuration.export_mapping.properties.akeneo_category'),
                    akeneoCategoryId: selectedAkeneoCategoryId,
                    required: __('pim_enrich.form.required'),
                    categories: categories,
                    error: this.parent.validationErrors['akeneoCategoryId'],
                    type: this.getFormData().type,
                    locale: UserContext.get('uiLocale')
                }));

                this.getFormModel().set('akeneoCategoryId', selectedAkeneoCategoryId);
                this.getFormModel().set('akeneoCategoryName', this.$("select option:selected").text());

                initSelect2.init(this.$('select'))
            
            }.bind(this));

            this.delegateEvents();
        }
    });
});
