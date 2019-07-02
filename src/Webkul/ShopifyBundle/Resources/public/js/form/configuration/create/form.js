define([
    'jquery',
    'underscore',
    'custom/form/configuration/create/modal',
    'pim/user-context',
    'oro/translator',
    'pim/fetcher-registry',
    'pim/initselect2',
    'custom/template/configuration/tab/credential',
    'routing',
    'oro/messenger',
    'oro/loading-mask'
], function(
    $,
    _,
    BaseModal,
    UserContext,
    __,
    FetcherRegistry,
    initSelect2,
    template,
    Routing,
    messenger,
    LoadingMask
    ) {
 
    return BaseModal.extend({
        loadingMask: null,
            updateFailureMessage: __('error to fetch token'),
            updateSuccessMessage: __('pim_enrich.entity.info.update_successful'),
            isGroup: true,
            label: __('custom.credential.tab'),
            template: _.template(template),
            code: 'custom_connector_credential',
            controls: [{
                'label' : 'custom.form.properties.host_name.title',
                'name': 'hostName',
                'type': 'text'
            }, {
                'label' : 'custom.form.properties.apiUser.title',
                'name': 'apiUser',
                'type': 'text'
            }, {
                'label' : 'custom.form.properties.apiKey.title',
                'name': 'apiKey',
                'type': 'password'
            }],
 
            errors: [],
            events: {
                'change .AknFormContainer-Credential input': 'updateModel',
            },
            
             /**
             * {@inheritdoc}
             */
            render: function () {
                $('#container .AknButtonList[data-drop-zone="buttons"] div:nth-of-type(1)').hide();
                 
                self = this;
                var controls;
                var controls2;
                 
                this.$el.html(this.template({
                    controls: self.controls,
                    controls2: self.controls2,
                    model: self.getFormData(),
                    errors: this.parent.validationErrors
                }));
                this.delegateEvents();
            },
            /**
             * Update model after value change
             *
             * @param {Event} event
             */
            updateModel: function (event) {
                var data = this.getFormData();
                switch(event.target.id) {
                    case 'pim_enrich_entity_form_hostName':
                        data['hostName'] = event.target.value
                        break;
                    case 'pim_enrich_entity_form_apiUser':
                        data['apiUser'] = event.target.value
                        break;
                    case 'pim_enrich_entity_form_apiKey':
                        data['apiKey'] = event.target.value
                }
                 
                this.setData(data);
            },
             
            stringify: function(formData) {
                if('undefined' != typeof(formData['mapping']) && formData['mapping'] instanceof Array) {
                    formData['mapping'] = $.extend({}, formData['mapping']);
                }
 
                return JSON.stringify(formData);                
            },
 
            /**
             * {@inheritdoc}
             */
            getSaveUrl: function () {
                var route = Routing.generate('webkul_shopify_connector_configuration_post');
                return route;
            },
            /**
             * Sets errors
             *
             * @param {Object} errors
             */
            setValidationErrors: function (errors) {
                this.parent.validationErrors = errors.response;
                this.render();
            },
 
            /**
             * Resets errors
             */
            resetValidationErrors: function () {
                this.parent.validationErrors = {};
                this.render();
            },
 
                        /**
             * Show the loading mask
             */
            showLoadingMask: function () {
                this.loadingMask = new LoadingMask();
                this.loadingMask.render().$el.appendTo(this.getRoot().$el).show();
            },
 
            /**
             * Hide the loading mask
             */
            hideLoadingMask: function () {
                this.loadingMask.hide().$el.remove();
            },
          
    });
});
