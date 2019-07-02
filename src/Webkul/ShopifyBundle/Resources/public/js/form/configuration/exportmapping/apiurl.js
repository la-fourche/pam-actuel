define([
    'jquery',
    'underscore',
    'shopify/form/configuration/exportmapping/modal',
    'pim/form',
    'pim/user-context',
    'oro/translator',
    'pim/initselect2',
    'pim/fetcher-registry',
    'shopify/template/configuration/exportmapping/apiurl'
], function( 
    $,
    _,
    BaseModal,
    BaseForm,
    UserContext,
    __,
    initSelect2,
    FetcherRegistry,
    template
    ) {

    return BaseForm.extend({
        options: {},
        template: _.template(template), 
        events: {
            'change input': 'updateModel',
        } ,     
        updateModel(event) {
           const apiUrl = $(event.target).val();
           this.getFormModel().set('apiUrl',  apiUrl);
        },
        
        render() {
            if (!this.configured) return this;
            const fetcher = FetcherRegistry.getFetcher('shopify-apiurl');
            
            fetcher.fetchAll().then(function (apiUrl) {
                console.log(this.getFormData().apiUrl);
                 this.$el.html(this.template({
                    label: __('webkul_shopify_connector.form.configuration.export_mapping.properties.akeneo_apiUrl'),
                    apiUrl: typeof this.getFormData().apiUrl != 'undefined' ? this.getFormData().apiUrl : apiUrl.apiUrl,
                    required: __('pim_enrich.form.required'),
                    error: this.parent.validationErrors['apiUrl'],
                    type: this.getFormData().type,        
                }));
                
                this.getFormModel().set('apiUrl', apiUrl.apiUrl); 
            
                 
            }.bind(this));

            this.delegateEvents();
            
        }
    });
});
