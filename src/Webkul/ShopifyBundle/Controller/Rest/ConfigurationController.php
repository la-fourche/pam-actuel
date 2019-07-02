<?php

namespace Webkul\ShopifyBundle\Controller\Rest;

use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Oro\Bundle\SecurityBundle\Annotation\AclAncestor;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Constraints\Optional;
use Symfony\Component\Validator\Constraints\Url;
use Symfony\Component\Form\FormError;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\Intl\Intl;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;

/**
 * Configuration rest controller in charge of the shopify connector configuration managements
 */
class ConfigurationController extends Controller
{
    const SECTION = 'shopify_connector';
    const SETTING_SECTION = 'shopify_connector_settings';
    const IMPORT_SETTING_SECTION = 'shopify_connector_importsettings';
    const IMPORT_FAMILY_SETTING_SECTION = 'shopify_connector_otherimportsetting';
    const DEFAULTS_SECTION = 'shopify_connector_defaults';
    const OTHER_SETTING_SECTION = 'shopify_connector_others';
    const QUICK_EXPORT_SETTING_SECTION = 'shopify_connector_quickexport';
    const MULTI_SELECT_FIELDS_SECTION = 'shopify_connector_multiselect';
    const QUICK_EXPORT_CODE = 'shopify_product_quick_export';

    public $page;

    /**
     * Get the current configuration
     * 
     * @AclAncestor("webkul_shopify_connector_configuration")
     *
     * @return JsonResponse
     */
    public function credentialAction(Request $request)
    {
        $connectorService = $this->get('shopify.connector.service');

        switch($request->getMethod()) {
            case 'POST':
                $params = $request->request->all() ? : json_decode($request->getContent(), true);
                switch($request->get('tab')) {
                    case 'credential':
                        $form = $this->getConfigForm();
                        $credentials = !empty($params['credentials']) ? $params['credentials'] : [];
                        $credentials['hostname'] = $request->getHost();
                        $credentials['scheme'] = $request->getScheme();
                        
                        $form->submit($credentials);
                        if($form->isValid() && $connectorService->checkCredentials($credentials)) {
                            $connectorService->saveCredentials($credentials);
                            $this->checkAndSaveQuickJob();

                            return new JsonResponse($credentials);
                        } else {
                            $form->get('apiKey')->addError(new FormError('invalid details'));
                            return new JsonResponse($this->getFormErrors($form), Response::HTTP_BAD_REQUEST); 
                        }
                        break;
    
                    case 'exportMapping':

                       if(isset($params['multiselect'])) {
                            $connectorService->saveSettings($params['multiselect'], self::MULTI_SELECT_FIELDS_SECTION);
                       }
                        if(isset($params['defaults'])) {
                            $connectorService->saveSettings($params['defaults'], self::DEFAULTS_SECTION);
                        }
                        if(isset($params['quicksettings'])) {
                            $connectorService->saveSettings($params['quicksettings'], self::QUICK_EXPORT_SETTING_SECTION);
                        }
                        if(!empty($params['settings'])) {
                            $connectorService->saveSettings($params['settings']);
                        }
                        if(isset($params['others'])) {
                            $connectorService->saveSettings($params['others'], self::OTHER_SETTING_SECTION);
                        }   
                        break;
                    case 'importMapping':

                       if(!empty($params['otherimportsetting'])) {
                            $connectorService->saveSettings($params['otherimportsetting'], self::IMPORT_FAMILY_SETTING_SECTION);
                        }
                        if(isset($params['importsettings'])) {
                            $connectorService->saveAttributeMapping($params['importsettings'], self::IMPORT_SETTING_SECTION);
                        }
                        if(isset($params['others'])) {
                            $connectorService->saveSettings($params['others'], self::OTHER_SETTING_SECTION);
                        }   

                        break;
                    case 'otherSettings':

                        if(isset($params['others'])) {
                            $connectorService->saveSettings($params['others'], self::OTHER_SETTING_SECTION);
                        }                
                        
                        break;
                }
                break;
            case 'GET':
                $data = [];
                $data['credentials'] = $connectorService->getCredentials();
                $data['settings']    = $connectorService->getScalarSettings();
                $data['defaults']    = $connectorService->getSettings(self::DEFAULTS_SECTION);
                $data['others']      = $connectorService->getScalarSettings(self::OTHER_SETTING_SECTION);
                $data['quicksettings'] = $connectorService->getScalarSettings(self::QUICK_EXPORT_SETTING_SECTION);
                $data['importsettings'] = $connectorService->getScalarSettings(self::IMPORT_SETTING_SECTION);
                $data['otherimportsetting'] = $connectorService->getScalarSettings( self::IMPORT_FAMILY_SETTING_SECTION);
                $data['multiselect'] = $connectorService->getScalarSettings( self::MULTI_SELECT_FIELDS_SECTION);

                
                return new JsonResponse($data);
                break;
        }
        exit(0);
    }
    /**
     * Get the current configuration
     * 
     * @AclAncestor("webkul_shopify_connector_configuration")
     *
     * @return JsonResponse
     */
    public function getDataAction()
    { 
        $connectorService = $this->get('shopify.connector.service');
        $multiselect = $connectorService->getScalarSettings( self::MULTI_SELECT_FIELDS_SECTION);
        
        foreach($this->mappingFields as $index => $field) {
            if(isset($field["name"]) && array_key_exists($field["name"], $multiselect)) {
                $this->mappingFields[$index]["multiselect"] = $multiselect[$field["name"]];
            }
        }
        
        return new JsonResponse($this->mappingFields);
    }

    protected function checkAndSaveQuickJob()
    {
        $jobInstance = $this->get('pim_enrich.repository.job_instance')->findOneBy(['code' => self::QUICK_EXPORT_CODE]);
    
        if(!$jobInstance) {
            $em = $this->getDoctrine()->getManager();
            $jobInstance = new \JobInstance();
            $jobInstance->setCode(self::QUICK_EXPORT_CODE);            
            $jobInstance->setJobName('shopify_quick_export');
            $jobInstance->setLabel('Shopify quick export');
            $jobInstance->setConnector('Shopify Export Connector');
            $jobInstance->setType('quick_export');
            $em->persist($jobInstance);
            $em->flush();
        }    
    }
    private function getConfigForm() 
    {
        $form = $this->createFormBuilder(null, [
                    'allow_extra_fields' => true,
                    'csrf_protection' => false
                ]);
        $form->add('shopUrl', null, [
            'constraints' => [
                new Url(),
                new NotBlank()                
            ]
        ]);
        $form->add('apiKey', null, [
            'constraints' => [
                new NotBlank()                
            ]
        ]);
        $form->add('apiPassword', null, [
            'constraints' => [
                new NotBlank()                
            ]
        ]);                

        return $form->getForm();
    }

    private function getFormErrors($form) 
    {
    	$errorContext = [];
        foreach ($form->getErrors(true) as $key => $error) {
            $errorContext[$error->getOrigin()->getName()] = $error->getMessage();
        }

        return $errorContext;
    }

    /**
    * returns curl response for given route
    *
    * @param string $url
    * @param string $method like GET, POST
    * @param array headers (optional)
    * @AclAncestor("webkul_shopify_connector_configuration")
    * @return string $response
    */
    protected function requestByCurl($url, $method, $payload = null, $headers = [])
    {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL,$url);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);

        if($payload) {
            if(empty($headers)) {
                $headers = [
                    'Content-Type: application/json',
                ];
            }
            curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
        }
        if(!empty($headers)) {
            curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        }

        $response = curl_exec($ch);

        return $response;
    }    

    public function getActiveCurrenciesAction(){
        $currencies = [];
        $repo = $this->get('pim_catalog.repository.currency');
        $codes = $repo->getActivatedCurrencyCodes();
        
        foreach($codes as $code)
        {
            $currencies[$currencyCode] = Intl::getCurrencyBundle()->getCurrencyName($code);
        }
        
        return new JsonResponse($currencies);
    }

    public function getLogFileAction() 
    {
        $log_dir = $this->getParameter('logs_dir');
        $env = $this->getParameter('kernel.environment');
        $path = $log_dir."/webkul_shopify_batch.".$env.".log";
        
        $fs=new Filesystem();
        if(!$fs->exists($path)) {
            $fs->touch($path);
        }
        
        $response = new Response();
        $response->headers->set('Content-type', 'application/octet-stream');
        $response->headers->set('Content-Disposition', sprintf('attachment; filename="%s"', "webkul_shopify_batch.".$env.".log" ));
        $response->setContent(file_get_contents($path));
        $response->setStatusCode(200);
        $response->headers->set('Content-Transfer-Encoding', 'binary');
        $response->headers->set('Pragma', 'no-cache');
        $response->headers->set('Expires', '0');
        
        return $response;
    }

    public function getRemoteLocationsAction()
    {
        $connectorService = $this->get('shopify.connector.service');
        $credentials = $connectorService->getCredentials();
        if(empty($credentials['shopUrl']) && empty($credentials['apiKey'])) {
            
            return new JsonResponse([]);
        } else {
            $response  = $connectorService->requestApiAction('locations',[],[]); 
    
            if(isset($response['code']) && $response['code'] == Response::HTTP_OK && isset($response['locations'])) {
                return new JsonResponse($response['locations']);
            } else {
                return new JsonResponse([]);
            }
        }
    }

    public function getShopifyApiUrlAction()
    {
        $connectorService = $this->get('shopify.connector.service');
        $apiUrl = $connectorService->getApiUrl();
        
        return new JsonResponse(['apiUrl' => $apiUrl]);
    }

    public function deleteCredentailAction($id)
    {
        $mapping = $this->get('webkul_product_export.repository.credentials')->find($id);
        if(!$mapping) {
            throw new NotFoundHttpException(
                    sprintf('Instance with id "%s" not found', $id)
                );
        }
        $em = $this->getDoctrine()->getManager();
        $em->remove($mapping);
        $em->flush();
 
        return new Response(null, Response::HTTP_NO_CONTENT);
    }

    public function toggleAction(CredentialsConfig $configValue)
    {
        try{
            $em = $this->getDoctrine()->getManager();
 
            //change current active value
            $configValue->activation();
            $em->persist($configValue);
            $em->flush();
             
        } catch(Exception $e) {
            return new JsonResponse(['route' => 'webkul_shopify_connector_configuration']);
        }
         
        return new JsonResponse(['route' => 'webkul_shopify_connector_configuration']);
    }

    public function getProductsCodeAction()
    {
        
        $productRepo = $this->get('pim_catalog.repository.product');
        $productIdentifierQuery = $productRepo->createQueryBuilder('p')
                                   ->select('p.identifier')
                                   ->getQuery();
        $productIdentifiers = $productIdentifierQuery->execute();                         
        $productmodelRepo = $this->get('pim_catalog.repository.product_model');
        $productmodelCodeQuery = $productmodelRepo->createQueryBuilder('pm')
                                             ->select('pm.code')
                                             ->getQuery();
        $productmodelCodes = $productmodelCodeQuery->execute();
        
        //product  codes
        foreach($productIdentifiers as $productIdentifier) {
           $productCodes [] = $productIdentifier["identifier"];
           
           
        }
        
        //product model codes
        foreach($productmodelCodes as $productmodelCode) {
            $productmodelCodesArray [] = $productmodelCode["code"];
            
         }
    
        $productsAndproductmodelsCodes = array_merge($productCodes,  $productmodelCodesArray);
        
        return new JsonResponse($productsAndproductmodelsCodes);
        
    }

    public function getShopifyProductsAction()
    {
        $connectorService = $this->get('shopify.connector.service');
        $items = [];
        $page = 1;
        
        while(1) {
            $item = $connectorService->getProductsByFields(['fields' => 'title,id,variants','page' => $page]);
            if(is_array($item) && !empty($item)){
                $items = array_merge($items, $item);
            } else {
                break;
            }

            $page++;
        }
        
        return new JsonResponse($items);
        
    }

    public function getAkeneoCategoriesAction()
    {
        $categories = $this->objectFilter->filterCollection(
            $this->repository->getOrderedAndSortedByTreeCategories(),
            'pim.internal_api.product_category.view'
        );

        return new JsonResponse(
            $this->normalizer->normalize($categories, 'internal_api')
        );
    }

    public function getShopifyCategoriesAction()
    {
        $connectorService = $this->get('shopify.connector.service');
        $categories = [];
        $page = 1;
        while(1) {
            $category = $connectorService->getShopifyCategories(['fields'=> 'id,title,handle', 'limit' => 50,'page' => $page]);
            if(is_array($category) && !empty($category)) {
                $categories = array_merge($categories, $category);
                
            } else {
                break;
            }
            $page++;    
        }

        return new JsonResponse($categories);
    }

    
    private $mappingFields = [
        [
            'name' => 'title',
            'label' => 'shopify.useas.name',
            'types' => [
                'pim_catalog_text',
            ],
            'default' => true,
            'mapping' => ['export', 'import'],
            'tooltip' => 'supported attributes types: text',      
            'multiselect' => false,
        ],        
        [
            'name' => 'body_html',
            'label' => 'shopify.useas.description',
            'types' => [
                'pim_catalog_text',
                'pim_catalog_textarea',
            ],
            'default' => true,
            'mapping' => ['export', 'import'],
            'tooltip' => 'supported attributes types: text, textarea',            
            'multiselect' => false,
        ],
        [ 
            'name' => 'price',
            'label' => 'shopify.useas.price',
            'types' => [
                'pim_catalog_price_collection',
                // 'pim_catalog_number',
            ],
            'mapping' => ['export', 'import'],
            'default' => true,
            'tooltip' => 'supported attributes types: price',                      
        ],
        [ 
            'name' => 'weight',
            'label' => 'shopify.useas.weight',
            'types' => [
                'pim_catalog_metric',
                'pim_catalog_number',
            ],
            'mapping' => ['export', 'import'],
            'default' => true,
            'tooltip' => 'supported attributes types: number, metric',
        ],
        [ 
            'name' => 'inventory_quantity',
            'label' => 'shopify.useas.quantity',
            'types' => [
                'pim_catalog_number',
            ],
            'mapping' => ['export', 'import'],
            'default' => true,
            'tooltip' => 'supported attributes types: number',
        ],
        [ 
            'name' => 'inventory_management',
            'label' => 'shopify.useas.inventory_management',
            'types' => [
                'pim_catalog_text',
            ],
            'mapping' => ['export', 'import'],
            'default' => true,
            'tooltip' => 'supported attributes types: text',
        ],
        [ 
            'name' => 'inventory_policy',
            'label' => 'shopify.useas.allow_purchase_out_of_stock',
            'types' => [
                'pim_catalog_boolean',
            ],
            'default' => true,
            'mapping' => ['export', 'import'],
            'tooltip' => 'supported attributes types: yes/no',
        ],        
        [ 
            'name' => 'vendor',
            'label' => 'shopify.useas.vendor',
            'types' => [
                'pim_catalog_text',
                'pim_catalog_simpleselect',
            ],
            'default' => true,
            'mapping' => ['export', 'import'],
            'tooltip' => 'supported attributes types: text, simple select',
        ],
        [ 
            'name' => 'product_type',
            'label' => 'shopify.useas.product_type',
            'types' => [
                'pim_catalog_text',
                'pim_catalog_simpleselect',
            ], 
            'default' => true,
            'mapping' => ['export', 'import'],
            'tooltip' => 'supported attributes types: text, simple select',                       
        ],
        [ 
            'name' => 'tags',
            'label' => 'shopify.useas.tags.comma.separated',
            'types' => [
                'pim_catalog_textarea',
                'pim_catalog_text',
                'pim_catalog_date',
                'pim_catalog_metric',
                'pim_catalog_multiselect',
                'pim_catalog_number',
                'pim_catalog_simpleselect',
                'pim_catalog_boolean',
                'pim_catalog_file',
                'pim_catalog_image',
                'pim_catalog_price_collection',
                'pim_catalog_identifier'
            ],
            'default' => true,
            'mapping' => ['export', 'import'],
            'tooltip' => 'supported attributes types: textarea, text, price, date, metric, select, multiselect, number, yes/no, identifier',
            'multiselect' => true,
        ],
        [ 
            'name' => 'barcode',
            'label' => 'shopify.useas.barcode',
            'types' => [
                'pim_catalog_text',
                'pim_catalog_number',
            ],
            'default' => true,
            'mapping' => ['export', 'import'],
            'tooltip' => 'supported attributes types: text, number',
        ],         
        [
            'name' => 'compare_at_price',
            'label' => 'shopify.useas.compare_at_price',
            'types' => [
                'pim_catalog_price_collection',
            ],
            'default' => true,
            'mapping' => ['export', 'import'],
            'tooltip' => 'supported attributes types: price',
        ],
        [
            'name' => 'metafields_global_title_tag',
            'label' => 'shopify.useas.seo_title',
            'types' => [
                'pim_catalog_text',
            ],
            'default' => true,
            'mapping' => ['export', 'import'],
            'tooltip' => 'supported attributes types: text',
            'multiselect' => false,
        ],
        [
            'name' => 'metafields_global_description_tag',
            'label' => 'shopify.useas.seo_description',
            'types' => [
                'pim_catalog_text',
                'pim_catalog_textarea',
            ],
            'default' => true,
            'mapping' => ['export', 'import'],
            'tooltip' => 'supported attributes types: text, textarea',
            'multiselect' => false,
        ],
        [
            'name' => 'handle',
            'label' => 'shopify.useas.handle',
            'types' => [
                'pim_catalog_text',
            ],
            'default' => true,
            'mapping' => ['export', 'import'],
            'tooltip' => 'supported attributes types: text',
            'multiselect' => false,
        ],
        [
            'name' => 'taxable',
            'label' => 'shopify.useas.taxable',
            'types' => [
                'pim_catalog_boolean',
            ],
            'default' => true,
            'mapping' => ['export', 'import'],
            'tooltip' => 'supported attributes types: yes/no',            
        ],
        [
            'name' => 'fulfillment_service',
            'label' => 'shopify.useas.fulfillment_service',
            'types' => [
                'pim_catalog_text',
                'pim_catalog_simpleselect',
            ],
            'default' => true,
            'mapping' => ['export', 'import'],
            'tooltip' => 'supported attributes types: text, simple select',                     
        ],
        [
            'name' => 'inventory_locations',
            'label' => 'shopify.defaults.inventory_location',
            'types' => [
                'pim_catalog_simpleselect',
            ],
            'default' => false,
            'mapping' => ['export'],
            'tooltip' => 'supported attributes types: simple select',                     
        ],
        [
            'name' => 'cost',
            'label' => 'shopify.defaults.inventory_cost',
            'types' => [
                'pim_catalog_price_collection',
            ],
            'default' => false,
            'mapping' => ['export'],
            'tooltip' => 'supported attributes types: price',                     
        ],
    ];

}
