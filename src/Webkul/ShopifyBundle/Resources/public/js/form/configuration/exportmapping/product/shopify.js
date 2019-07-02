define([
    'jquery',
    'underscore',
    'shopify/form/configuration/exportmapping/modal',
    'pim/user-context',
    'oro/translator',
    'pim/fetcher-registry',
    'pim/initselect2',
    'oro/loading-mask',
    'shopify/template/configuration/exportmapping/product/shopify'
], function(
    $,
    _,
    BaseModal,
    UserContext,
    __,
    FetcherRegistry,
    initSelect2,
    LoadingMask,
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
        updateModel(event) {
            var relatedId = '';
            const model = this.getFormModel();
            const shopifyProductId = this.$('select').select2('val');
            const data = this.$('select').select2('data');
            if(data) {
                relatedId = data.element[0].attributes[1].nodeValue ;
            }
            model.set('shopifyProductId', shopifyProductId);
            model.set('shopifyProductName', this.$("select option:selected").text());
            model.set('shopifyProductRelatedId', relatedId);

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
            const fetcher = FetcherRegistry.getFetcher('shopify-product');

            fetcher.fetchAll().then(function (shopifyproducts) {
                const shopifyProductId = this.getFormData().shopifyProductId;
                const shopifyProductName = this.getFormData().shopifyProductName;
                const selectedShopifyProductId =  shopifyProductId ||  ( (shopifyproducts.length)
                                                ?  (shopifyproducts[0].id )
                                                : 0 );
                this.$el.html(this.template({
                    label: __('webkul_shopify_connector.form.configuration.export_mapping.properties.shopify_product'),
                    shopifyproducts: shopifyproducts,
                    shopifyProductId: selectedShopifyProductId, 
                    required:__('pim_enrich.form.required'),
                    error: this.parent.validationErrors['shopifyProductId'],
                    type: this.getFormData().type
                }));
    
                this.getFormModel().set('shopifyProductId', selectedShopifyProductId);
                this.getFormModel().set('shopifyProductName',this.$("select option:selected").text());
                if(selectedShopifyProductId) {
                  var selectedProduct =  _.findWhere(shopifyproducts, {id: selectedShopifyProductId});
                  if(typeof selectedProduct !== "undefined" ){
                    const shopifyProductRelatedId = selectedProduct.variants[0].id;
                    this.getFormModel().set('shopifyProductRelatedId', shopifyProductRelatedId);
                  }  
                }
               initSelect2.init(this.$('select'))
               loadingMask.hide().$el.remove();
            }.bind(this));
            
            this.delegateEvents();

            return this;
        }
    });
});
