<?php

namespace Webkul\ShopifyBundle\Services;

use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Oro\Bundle\ConfigBundle\Entity\ConfigValue;
use Webkul\ShopifyBundle\Classes\ApiClient;
use Webkul\ShopifyBundle\Entity\DataMapping;
use Webkul\ShopifyBundle\Entity\CategoryMapping;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class ShopifyConnector
{
    const SECTION = 'shopify_connector';
    const SETTING_SECTION = 'shopify_connector_settings';
    const SECTION_ATTRIBUTE_MAPPING = 'shopify_connector_importsettings';

    private $em;
    private $container;
    private $stepExecution;
    private $settings = [];
    private $imageAttributeCodes = [];
    private $attributeTypes = [];
    private $attributeGroupCodes = [];
    private $attributeLabels = [];
    private $matchedSkuLogger;
    private $unmatchedSkuLogger;

    protected $requiredFields = ['shopUrl', 'apiKey', 'apiPassword', 'hostname', 'scheme'];

    public function __construct($container, \Doctrine\ORM\EntityManager $em)
    {
        $this->container = $container;
        $this->em = $em;
    }

    public function setStepExecution($stepExecution)
    {
        $this->stepExecution = $stepExecution;
    }

    public function getCredentials()
    {
        static $credentials;

        if(empty($credentials)) {
            /* job wise credentials */ 
            $params = $this->stepExecution ? $this->stepExecution->getJobparameters()->all() : null;
            /* common credentials */ 
            $repo = $this->em->getRepository('OroConfigBundle:ConfigValue');
            $configs = $repo->findBy([
                'section' => self::SECTION
                ]);
                
            $commonCredentials = $this->indexValuesByName($configs);

            if(!empty($this->stepExecution) && $this->stepExecution instanceOf \StepExecution && 
                !empty($params['shopUrl']) && !empty($params['apiKey']) && !empty($params['apiPassword'])) {

                $credentials = [
                    'shopUrl' => $params['shopUrl'],
                    'apiKey' => $params['apiKey'],
                    'apiPassword' => $params['apiPassword'],
                    'hostname' => !empty($commonCredentials['hostname']) ? $commonCredentials['hostname'] : '',
                    'scheme' => !empty($commonCredentials['scheme']) ? $commonCredentials['scheme'] : ''
                ];
            } else {
                $credentials = $commonCredentials;
            }
        }
        return $credentials;
    }

    public function checkCredentials($params)
    {
        $oauthClient = new ApiClient($params['shopUrl'], $params['apiKey'], $params['apiPassword']);
        $response = $oauthClient->request('getOneProduct', [], []);

        return !empty($response['code']) && Response::HTTP_OK == $response['code'];
    }

    public function saveCredentials($params)
    {
        $repo = $this->em->getRepository('OroConfigBundle:ConfigValue');
        foreach($params as $key => $value) {
            if(in_array($key, $this->requiredFields) && gettype($value) == 'string') {
                $field = $repo->findOneBy([
                    'section' => self::SECTION,
                    'name' => $key,
                    ]);
                if(!$field) {
                    $field = new ConfigValue();
                }
                $field->setName($key);
                $field->setSection(self::SECTION);
                $field->setValue($value);
                $this->em->persist($field);
            }
            $this->em->flush();                          
        }
    }

    private function indexValuesByName($values) 
    {
        $result = [];
        foreach($values as $value) {
            
            $result[$value->getName()] = $value->getValue();
        }    
        return $result;
    }

    public function getAttributeMappings()
    {
        $repo = $this->em->getRepository('OroConfigBundle:ConfigValue');        
        $attrMappings = $repo->findBy([
            'section' => self::SECTION_ATTRIBUTE_MAPPING
            
            ]);
        
        return $this->indexValuesByName($attrMappings);
    }

    public function saveAttributeMapping($attributeData, $section)
    {
         
        $repo =  $this->em->getRepository('OroConfigBundle:ConfigValue');
        /* remove extra mapping not recieved in new save request */ 
        
        $extraMappings = array_diff(array_keys($this->getAttributeMappings()), array_keys($attributeData));
        
        foreach($extraMappings as $mCode => $aCode) {
            $mapping = $repo->findOneBy([
                'name' => $aCode,
                'section' => self::SECTION_ATTRIBUTE_MAPPING
            ]);
            if($mapping) {
                $this->em->remove($mapping);
            }
        }

        /* save attribute mappings */
        foreach($attributeData as $mCode => $aCode) {
            $mCode = strip_tags($mCode);
            $aCode = strip_tags($aCode);
            
            $attribute = $repo->findOneBy([
                'name' => $mCode,
                'section' => self::SECTION_ATTRIBUTE_MAPPING
            ]);
            if($attribute) {
                $attribute->setValue($aCode);
                $this->em->persist($attribute);
            } else {
                $attribute = new ConfigValue();
                $attribute->setSection(self::SECTION_ATTRIBUTE_MAPPING);
                $attribute->setName($mCode);
                $attribute->setValue($aCode);
                $this->em->persist($attribute);
            }
        }

        $this->em->flush();
    }

    public function saveSettings($params, $section = self::SETTING_SECTION)
    {
        $repo = $this->em->getRepository('OroConfigBundle:ConfigValue');
        foreach($params as $key => $value) {
            if(gettype($value) === 'array') {
                $value = json_encode($value);
            }
            if(gettype($value) == 'boolean') {
                $value = ($value === true) ? "true" : "false";
            } 
            
            if(gettype($value) == 'string' || gettype($value) == 'NULL') {
                $field = $repo->findOneBy([
                    'section' => $section,
                    'name' => $key,
                    ]);
                    
                if(null != $value) {
                    if(!$field) {
                        $field = new ConfigValue();
                    }
                    $field->setName($key);
                    $field->setSection($section);
                    $field->setValue($value);
                    $this->em->persist($field);
                } else if($field) {
                    $this->em->remove($field);
                }
            }

            $this->em->flush();                             
        }
    }

    public function getSettings($section = self::SETTING_SECTION)
    {
        $repo = $this->em->getRepository('OroConfigBundle:ConfigValue');
        if(empty($this->settings[$section])) {
            $configs = $repo->findBy([
                'section' => $section
                ]);
                
                $this->settings[$section] = $this->indexValuesByName($configs);
        }
      
        
        return $this->settings[$section];
    }     

    public function getScalarSettings($section = self::SETTING_SECTION)
    {
        $settings = $this->getSettings($section);
        foreach($settings as $key => $value) {
            $value = json_decode($value);
            if($value !== null && json_last_error() === JSON_ERROR_NONE) {
                $settings[$key] = $value;
            }
        }

        return $settings;
    }

    public function getMappingByCode($code, $entity)
    {
        $credentials = $this->getCredentials();
        $apiUrl = array_key_exists('shopUrl', $credentials) ? $credentials['shopUrl']  : '';
        $apiUrl = $this->formatApiUrl($apiUrl);
        $repository = $this->container->get('webkul_shopify.repository.credentials');
        $mapping = $repository->findOneBy([
            'code'   => $code,
            'entityType' => $entity,
            'apiUrl' => $apiUrl,
        ]);
    
        return $mapping;
    }

    public function getCountMappingData(array $entityType)
    {
        $credentials = $this->getCredentials();
        $apiUrl = array_key_exists('shopUrl', $credentials) ? $credentials['shopUrl']  : '';
        $apiUrl = $this->formatApiUrl($apiUrl);
        $repository = $this->container->get('webkul_shopify.repository.credentials');
      
        return $repository->createQueryBuilder('c')
                ->select('count(c.id)')
                ->where('c.entityType  in(:entityType)')
                ->andwhere('c.apiUrl = :apiUrl')
                ->setParameter('entityType', $entityType)
                ->setParameter('apiUrl', $apiUrl)
                ->getQuery()->getSingleScalarResult();
    }
    
    public function deleteCountMappingData(array $entityType)
    {
        $credentials = $this->getCredentials();
        $apiUrl = array_key_exists('shopUrl', $credentials) ? $credentials['shopUrl']  : '';
        $apiUrl = $this->formatApiUrl($apiUrl);
        $repository = $this->container->get('webkul_shopify.repository.credentials');

        $results = $repository->createQueryBuilder('c')
                ->delete()
                ->where('c.entityType in (:entityType)')
                ->andwhere('c.apiUrl = :apiUrl')
                ->setParameter('entityType', $entityType)
                ->setParameter('apiUrl', $apiUrl)
                ->getQuery()->execute();
        
        return $results;
    }
    public function findCodeByExternalId($externalId, $entityType)
    {

        $credentials = $this->getCredentials();
        $apiUrl = array_key_exists('shopUrl', $credentials) ? $credentials['shopUrl']  : '';
        $apiUrl = $this->formatApiUrl($apiUrl);
        $repository = $this->container->get('webkul_shopify.repository.credentials');
        $mapping = $repository->findOneBy([
            'externalId'   => $externalId,
            'entityType' => $entityType,
            'apiUrl' => $apiUrl,
        ]);

        return $mapping ? $mapping->getCode() : null;
    }

public function addOrUpdateMapping($mapping, $code, $entity, $externalId, $relatedId = null, $jobInstanceId = null, $relatedSource = null)
    {
        $credentials = $this->getCredentials();
        $apiUrl = array_key_exists('shopUrl', $credentials) ? $credentials['shopUrl']  : '';
        $apiUrl = $this->formatApiUrl($apiUrl);

        if(!($mapping && $mapping instanceof DataMapping)) {
            $mapping = new DataMapping();
        } 
        
        $mapping->setApiUrl($apiUrl);
        $mapping->setEntityType($entity);
        $mapping->setCode($code);
        $mapping->setExternalId($externalId);

        if($relatedSource) {
            $mapping->setRelatedSource($relatedSource);
        }
        if($relatedId) {
            $mapping->setRelatedId($relatedId);
        }
        if($jobInstanceId) {
            $mapping->setJobInstanceId($jobInstanceId);
        }        
        $this->em->persist($mapping);
        $this->em->flush();
    }

    public function deleteMapping($mapping)
    {
        if($mapping) {
            $this->em->remove($mapping);
            $this->em->flush();
        }
    }

    public function requestApiAction($action, $data, $parameters = [])
    {
        $credentials = $this->getCredentials();
        if(empty($credentials['shopUrl']) && $this->stepExecution) {
            $msg = 'Error! Save credentials first';
            $this->stepExecution->addWarning($msg, [] , new \DataInvalidItem([]));
            exit();
        }
        
        $oauthClient = new ApiClient($credentials['shopUrl'], $credentials['apiKey'], $credentials['apiPassword']);
        $settings = $this->getSettings('shopify_connector_others');
        // logger set by user setting
        if(!empty($settings['enable_request_log']) && $settings['enable_request_log']== "true") {
            $logger = $this->container->get('webkul_shopify_jobs.logger');
        } else {
            $logger = null;
        }
        
        $response = $oauthClient->request($action, $parameters, $data, $logger);
        
        // Shopify has a limit of 1 call per second
        // sleep(1);

        // LF avoiding the limit
        usleep(200000);

        if(!empty($settings['enable_response_log']) && $settings['enable_response_log']== "true") {
            $logger = $this->container->get('webkul_shopify_jobs.logger');
            $logger->info("Response: " . json_encode($response));
        }
        
        return $response;
    }

    public function getAttributeGroupCodeByAttributeCode($code)
    {
        $this->attributeGroupCodes = [];
        if(empty($this->attributeGroupCodes[$code])) {
            $qb = $this->container->get('pim_api.repository.attribute')->createQueryBuilder('a')
                    ->select('a.id, a.code as attributeCode, g.code as groupCode')
                    ->leftJoin('a.group', 'g');            
            
            $results = $qb->getQuery()->getArrayResult();
            
            $groupCodes = [];
            foreach($results as $key => $value) {
                if(isset($value['groupCode'])) {                   
                    $groupCodes[$value['attributeCode']]  = $value['groupCode'];
                }
            }
            $this->attributeGroupCodes = $groupCodes;
        }
        return array_key_exists($code, $this->attributeGroupCodes) ? $this->attributeGroupCodes[$code] : null;
    }

    public function getImageAttributeCodes()
    {
        if(empty($this->imageAttributeCodes)) {
            $this->imageAttributeCodes = $this->container->get('pim_catalog.repository.attribute')->getAttributeCodesByType(
                'pim_catalog_image'
            );
        }
        
        return $this->imageAttributeCodes;
    }

    public function getAttributeAndTypes()
    {
        if(empty($this->attributeTypes)) {
            $em = $this->container->get('doctrine.orm.entity_manager');

            $results = $this->container->get('pim_catalog.repository.attribute')->createQueryBuilder('a')
                ->select('a.code, a.type')
                ->getQuery()
                ->getArrayResult();

            $attributes = [];
            if (!empty($results)) {
                foreach ($results as $attribute) {
                    $attributes[$attribute['code']] = $attribute['type'];
                }
            }

            $this->attributeTypes = $attributes;
        }

        return $this->attributeTypes;
    }

    public function generateImageUrl($filename, $host = null)
    {
        $filename = urldecode($filename);
        $credentials = $this->getCredentials();
        $host = !empty($credentials['hostname']) ? $credentials['hostname'] : null;
        $scheme = !empty($credentials['scheme']) ? $credentials['scheme'] : 'http';
        if($host) {
            $context = $this->container->get('router')->getContext();
            $context->setHost($host);
            $context->setScheme($scheme);
        }
        $request = new Request();
        try {
            $url = $this->container->get('router')->generate('webkul_shopify_media_download', [
                                        'filename' => urlencode($filename)
                                     ]   , UrlGeneratorInterface::ABSOLUTE_URL);
                                     
        } catch(\Exception $e) {
            $url  = '';
        }

        return $url;
    }

    public function generateFileUrl($filename, $host = null)
    {
        $filename = urldecode($filename);
        $credentials = $this->getCredentials();
        $host = !empty($credentials['hostname']) ? $credentials['hostname'] : null;
        $scheme = !empty($credentials['scheme']) ? $credentials['scheme'] : 'http';        
        if($host) {
            $context = $this->container->get('router')->getContext();
            $context->setHost($host);
            $context->setScheme($scheme);
        }
        $request = new Request();
        try {
            $url = $this->container->get('router')->generate('pim_enrich_media_download', [
                                        'filename' => urlencode($filename)
                                     ]   , UrlGeneratorInterface::ABSOLUTE_URL);
        } catch(\Exception $e) {
            $url  = '';
        }

        return $url;
    }

     

    public function mappedAfterImport($itemId, $code, $entity, $jobInstanceId = null, $relatedId = null , $relatedSource = null)
    {
        $credentials = $this->getCredentials();
        $apiUrl = array_key_exists('shopUrl', $credentials) ? $credentials['shopUrl']  : '';
        
        $repo = $this->container->get('webkul_shopify.repository.credentials');
        $mapping = $repo->findOneBy([
            'externalId' => $itemId,
            ]);
        if($mapping && !empty($relatedSource)) {
            $relatedSource = json_decode($relatedSource);
            $relatedSource2 = json_decode($mapping->getRelatedSource());
            if(is_array($relatedSource2)) {
                $relatedSource = array_merge($relatedSource, $relatedSource2);
            }
            $relatedSource = json_encode($relatedSource);
        }
        $externalId = $itemId;

        $this->addOrUpdateMapping($mapping, $code, $entity, $externalId, $relatedId, $jobInstanceId, $relatedSource);
    }


    public function findCategories($productId)
    {
        $categoriesByHandle = [];
        $custom_collections_response = $this->requestApiAction(
            'getCategoriesByProductId', 
            '',
            ['product_id' => $productId]
        );

        if(!empty($custom_collections_response['custom_collections'])) {
            foreach($custom_collections_response['custom_collections'] as $collection) {
                if(!empty($collection['id']) ) {
                    $categoryCode = $this->categoryCodeFindInDb($collection['id']);
                    if($categoryCode) {
                        $categoriesByHandle[] = $categoryCode;
                    }
                }
            }   
        }

        $setting = $this->getSettings('shopify_connector_others');
        if(!empty($setting['smart_collection']) && $setting['smart_collection'] == "true") {
            //for smart colletions
            $smart_collections_response = $this->requestApiAction(
                'getSmartCategoriesByProductId', 
                '',
                ['product_id' => $productId]
            );

            if(!empty($smart_collections_response['smart_collections'])) {
                foreach($smart_collections_response['smart_collections'] as $smartCollection) {
                    
                    if(!empty($smartCollection['id'])) {
                        $categoryCode = $this->categoryCodeFindInDb($smartCollection['id']);
                        if($categoryCode) {
                            $categoriesByHandle[] = $categoryCode;
                        }
                    }
                }   
            }
        }
        
        return $categoriesByHandle;
    }

    public function verifyCode($code)
    {
        $code = str_replace("-", "_", $code);
        $code = str_replace(" ", "_", $code);
        $code = preg_replace("/[^a-zA-Z0-9_]/", "", $code);

        return $code;
    }

    public function categoryCodeFindInDb($categoryId){
        $categoryCode = $this->findCodeByExternalId($categoryId, 'category');
        $categoryEntity = $this->container->get('pim_catalog.repository.category')->findOneByIdentifier($categoryCode);
        if($categoryEntity) {
            $categoryCode = $categoryEntity->getCode();
        }
        
        return $categoryCode;
    }

    public function getOptionAttributes($product){
        $optionAttributes = [];
        foreach($product['options'] as $option){
            if($option['name']!== null){
                $code = $this->verifyCode(strtolower($option['name']));
                $results = $this->container->get('pim_catalog.repository.attribute')->createQueryBuilder('a')
                -> select('a.code')
                -> where('a.code = :code')
                -> setParameter('code', $code)
                -> getQuery()->getResult();
                
                if($results !== null){
                    foreach($results as $result){
                        $optionAttributes[] = $result['code'];
                    }
                }
            }
        }
        
        return $optionAttributes;
    }
    
    public function getAttributeByLocaleScope($field)
    {
        
        $results = $this->container->get('pim_catalog.repository.attribute')->createQueryBuilder('a')
                -> select('a.code, a.type, a.localizable as localizable, a.scopable as scopable')
                -> where('a.code = :code')
                -> setParameter('code', $field)
                -> getQuery()->getResult();
        
                return $results;
    }

    public function getMetaField($name, $metaFields) 
    {

        if($name == 'metafields_global_description_tag') {
            if(array_key_exists('description_tag' , $metaFields)){
            
                return $metaFields['description_tag'];
            }
        }else if($name == 'metafields_global_title_tag') {
            
            if(array_key_exists('title_tag', $metaFields)){
                
                return $metaFields['title_tag'];
            }
        }
    }

    public function normalizeMetaFieldArray($metaFields) 
    {
        $items = [];
        foreach($metaFields as $metaField) {
            $items[$metaField["key"]] = $metaField["value"];
        }

        return $items;
    }

    public function getOptionNameByCodeAndLocale($code, $locale)
    {
        try {
            $option = $this->container->get('pim_catalog.repository.attribute_option')->findOneByIdentifier($code);
        } catch(\Exception $e) {
            $option = null;
        }
        
        if($option) {
            $option->setLocale($locale);
            $optionValue = $option->__toString() !== '[' . $option->getCode() . ']' ? $option->__toString() : $option->getCode();

            return $optionValue;
        }         
    }    
    public function findFamilyVariantByCode($code, $entity) 
    {
        if($entity === 'productmodel'){
            try {
                $repo = $this->container->get('pim_catalog.repository.product_model');

                $result = $repo->createQueryBuilder('p')
                                ->leftJoin('p.familyVariant', 'f')
                                ->where('p.code = :code')
                                ->setParameter('code', $code)
                                ->select('f.code')
                                ->getQuery()->getResult();
                
                if(isset($result[0])){
                    return $result[0]['code'] ? $result[0]['code'] : null;
                }

            } catch(\Exception $e){
                $family = null;
            }
        }

    }

    public function getApiUrl()
    {
        $credentials = $this->getCredentials();
        $apiUrl = array_key_exists('shopUrl', $credentials) ? $credentials['shopUrl']  : '';
        $apiUrl = $this->formatApiUrl($apiUrl);

        return $apiUrl;
    }

    public function findFamilyByCode($code, $entity){
        
        if($entity === 'product') {
            try {
                $repo = $this->container->get('pim_catalog.repository.product');

                $result = $repo->createQueryBuilder('p')
                                ->leftJoin('p.family', 'f')
                                ->where('p.identifier = :identifier')
                                ->setParameter('identifier', $code)
                                ->select('f.code')
                                ->getQuery()->getResult();
                                
                if(isset($result[0])){
                    return $result[0]['code'] ? $result[0]['code'] : null;
                }

            } catch(\Exception $e) {
                $family = null;
            }
        } else if($entity === 'productmodel') {
            try {
                $repo = $this->container->get('pim_catalog.repository.product_model');

                $result = $repo->createQueryBuilder('p')
                                ->leftJoin('p.familyVariant', 'fv')
                                ->leftJoin('fv.family', 'f')
                                ->where('p.code = :code')
                                ->setParameter('code', $code)
                                ->select('f.code')
                                ->getQuery()->getResult();
                                
                if(isset($result[0])) {
                    return $result[0]['code'] ? $result[0]['code'] : null;
                }

            } catch(\Exception $e){
                $family = null;
            }
        }
    }


    public function getFamilyVariantByIdentifier($identifier)
    {
        return $this->container->get('pim_catalog.repository.family_variant')->findOneByIdentifier($identifier); 
    }

    public function addVariant($variant)
    {
        $familyVariant = $this->container->get('pim_catalog.factory.family_variant')->create();

        try {
            $this->container->get('pim_catalog.updater.family_variant')->update($familyVariant, $variant);
        } catch (PropertyException $exception) {
            $error = true;
        }
        if(empty($error)) {
            $this->em->persist($familyVariant);
            $this->em->flush();

            return $familyVariant;
        }
    }

    public function getFamilyByCode($code)
    {
        return $this->container->get('pim_catalog.repository.family')->findOneByIdentifier($code);
    }

    public function getHandleAttributesOfProductIdentifiers($attributeCode, $identifiers, $locale, $channel)
    {
        $values = [];
        $attribute = $this->getAttributeByCode($attributeCode);
        $pqbFactory = $this->container->get('pim_catalog.query.product_query_builder_factory');
        $pqb = $pqbFactory->create([])
                ->addFilter('identifier', 'IN', $identifiers);
        $productsCursor = $pqb->execute();

        if($attribute) {
            $values = $this->getAttributeValuesFromCursor($productsCursor, $attribute, $locale, $channel);
        }

        return $values;                   
    }

    public function getHandleAttributeOfProductModelIdentifiers($attributeCode, $identifiers, $locale, $channel)
    {
        $values = [];
        $attribute = $this->getAttributeByCode($attributeCode);        
        $pqbFactory = $this->container->get('pim_catalog.query.product_model_query_builder_factory');
        $pqb = $pqbFactory->create([])
                ->addFilter('identifier', 'IN', $identifiers);
        $productsCursor = $pqb->execute();
        if($attribute) {
            $values = $this->getAttributeValuesFromCursor($productsCursor, $attribute, $locale, $channel);
        }

        return $values;              
    }

    public function getHandleAttributeByGroupIdentifiers($attributeCode, $identifiers, $locale, $channel)
    {
        $values = [];
        if(!empty($identifiers)) {
            $attribute = $this->getAttributeByCode($attributeCode);        
            $pqbFactory = $this->container->get('pim_catalog.query.product_query_builder_factory');            
            $pqb = $pqbFactory->create([])
                    ->addFilter('groups', 'IN', $identifiers);
            $productsCursor = $pqb->execute();
            if($attribute) {
                $values = $this->getAttributeValuesFromCursor($productsCursor, $attribute, $locale, $channel);
                /* can add for models in  groups  */ 
            }

        }

        return $values;              
    }

    protected function getAttributeValuesFromCursor($productsCursor, $attribute, $locale, $channel)
    {
        $values = [];
        if($attribute) {
            foreach ($productsCursor as $product) {
                $val = $product->getValue($attribute->getCode(), $attribute->isLocalizable() ? $locale : null, $attribute->isScopable() ? $channel : null );
                if($val) {
                    $values[] = $val->getData();
                }
            }
        }

        return $values;
    }
    
    protected $attributes = [];

    public function getAttributeByCode($code)
    {
        if(empty($this->attributes[$code])) {
            $this->attributes[$code] = $this->container->get('pim_catalog.repository.attribute')->findOneByIdentifier($code);
        }

        return $this->attributes[$code];
    }

    public function getAttributeLabelByCodeAndLocale($code, $locale)
    {
        if(empty($this->attributeLabels[$code . '-' . $locale])) {
            $attribute = $this->getAttributeByCode($code);
            if($attribute) {
                $attribute->setLocale($locale);
                $label = $attribute->__toString() !== '['.$code.']' ? $attribute->__toString() : $code;
            } else {
                /* code as fallback label */
                $label = $code;
            }

            $this->attributeLabels[$code . '-' . $locale] = $label;
        }

        return $this->attributeLabels[$code . '-' . $locale];
    }

    protected function formatApiUrl($url)
    {
        $url = str_replace(['http://'], ['https://'], $url);

        return \rtrim($url, '/');
    }

    /**
     *  return the formate tags class service 
     */
    public function getTagsFormatterService()
    {
        return $this->container->get("webkul_shopify_tags_formatter");
    }

    /**
     *  return the webkul_shopify_product_data_formatter service
     */
    public function getProductDataFormatterService()
    {
        return $this->container->get('webkul_shopify_product_data_formatter');
    }
        
    /**
     *  return the family label or code  by familyCode or locale
     * 
     * @var String $familyCode
     * 
     * @var String $locale
     * 
     * @return String $label
     * 
     */
    public function getFamilyLabelByCode($familyCode, $locale) 
    {
        $family = $this->container->get("pim_catalog.repository.family")->findOneByIdentifier($familyCode);
        $label = '';

        if($family) {
            if($locale) {
                $family->setLocale($locale);
            }
            $label = $family->getLabel();
        } 

        return $label;
    }

    /**
     * retun the group type by code
     */
    public function getGroupTypeByCode($groupCode) 
    {
        $group = $this->container->get("pim_catalog.repository.group")->findOneByIdentifier($groupCode);
        $groupTypeCode = '';
        if($group) {
            $groupType = $group->getType();
            if($groupType) {
                $groupTypeCode = $groupType->getCode();
            }
        }
        
        return str_replace('_', ' ', $groupTypeCode); 
    }

    /**
     * remove the extra zeros in the matric attribute value
     * 
     * @var $attributeValue
     * 
     * @return $formatedValue
     */
    public function formateMatricValue($attributeValue)
    {   
        $otherSetting = $this->getSettings("shopify_connector_others");
        
        if(isset($otherSetting['roundof-attribute-value']) && filter_var($otherSetting['roundof-attribute-value'], FILTER_VALIDATE_BOOLEAN) && getType($attributeValue) === "string") {
        
            $formatedValue = explode('.', $attributeValue);
            $integerPart = $formatedValue[0] ?? 0;
     
                $rightPart = explode(' ', $formatedValue[1] ?? 0);

                $fractionalPart = rtrim($rightPart[0] ?? 0, '0');
                $unit = $rightPart[1] ?? '';

                if(empty($fractionalPart))  {
                    $attributeValue = $integerPart . ' ' . $unit ;
                } else {
                    $attributeValue = $integerPart . '.' . $fractionalPart . ' ' . $unit ;
                }
            }

            return $attributeValue;
    }

    
    /**
     * @var int $page
     * @var array $fields
     * 
     * To fetch the products from the shopify
     * 
     */
    public function getProductsByFields(array $fields = []) 
    {
        $items = [];
        try{
            $response = $this->requestApiAction('getProductsByFields', [], $fields);
        }catch(\Exception $e){
            $response['error'] = $e->getMessage();
        }

        if(empty($response['error']) && $response['code'] === Response::HTTP_OK){
            $products = $response['products'];
        } else {
            $products = $response;
        }
     
        return $products;
    }

    public function getShopifyCategories(array $fields = [])
    {
       $categories = [];

       $response = $this->requestApiAction(
           'getCategoriesByLimitPageFields',
           [],
           $fields
       );
       if($response['code'] === Response::HTTP_OK) {
           $categories = $response['custom_collections'] ?? [];
       }
     
       return $categories;
    }  
    
    public function getShopifyProducts()
    {
        $credentials = $this->getCredentials();
        $apiUrl = array_key_exists('shopUrl', $credentials) ? $credentials['shopUrl']  : '';
        $apiUrl = $this->formatApiUrl($apiUrl);

    }
    
    public function getProductVariantsTags($item, $mappedFields, $tags, $locale, $baseCurrency)
    {   
        $tagFormatter = $this->container->get("webkul_shopify_tags_formatter");
        $oldTags = explode(",", $tags);
        $variantData = $this->getChildProductsByProductModelCode($item['parent'],$item['allVariantAttributes'], $mappedFields);

        $variantDataValue = [];
        $tagsData = [];
        $formatTags = [];
        
        foreach($variantData as $variantValue ) {
            $variantDataValue[] = array_intersect_key($variantValue,array_flip($mappedFields));
        }

        foreach($variantDataValue as $values ) {

            foreach($values as $key => $value) {
         
                foreach($value as $localValue) {
                    if(null != $localValue['locale'] &&  $localValue['locale'] != $locale) {
                        continue;
                    }
                    if(is_array($localValue['data'])) {
                        
                        foreach($localValue['data'] as $lValue) {

                            if(isset($lValue['currency']) ) {
                                if($lValue['currency'] !== $baseCurrency) {
                                    continue;
                                }
                                 
                                $tagsData[][$key]  = $lValue['amount'];
                                break;
                            } else {

                                $tagsData[][$key] = $lValue['amount'] ?? $lValue;
                                break;
                            }
                        }
                    } else {
                        $tagsData[][$key] = $this->getOptionValue($key, $localValue['data'])['labels'][$locale];
                    }                    
                    break;
                }
                
            }
        }
        $newData = $this->tagsDataUnique($tagsData);
       
        foreach($newData as $key=>$value) {
          $formatTags[] = $tagFormatter->formateTags($mappedFields,$value, $locale);
        }

        return implode(",",array_unique(array_merge($formatTags,$oldTags)));
    }
    function tagsDataUnique($input)
    {
        $serialized = array_map('serialize', $input);
        $unique = array_unique($serialized);

        return array_intersect_key($input, $unique);
    } 
    
    public function getOptionValue($attribute, $code)
    {
        $attrCode = $attribute . '.' . $code;       
        $attributeOptions = $this->container->get('pim_catalog.repository.attribute_option')->findOneByIdentifier($attrCode);
        if(!empty($attributeOptions)) {
            $serializer = $this->container->get('pim_serializer');
            return $serializer->normalize($attributeOptions, 'standard');
        } 
    }

    public function getRepositoryCode($repo)
    {
        return $this->container->get($repo);
    }
    
    public function getChildProductsByProductModelCode($productModelCode, $variantAttributes= [])
    {
        $model = $this->container->get('pim_catalog.repository.product_model')->findOneByIdentifier($productModelCode);
        $childsModels = $this->container->get('pim_catalog.repository.product_model')->findChildrenProductModels($model);
        $variantAttributesData = [];

        if(!empty($childsModels)) {
            foreach($childsModels as $childModel) {
                if($childModel instanceof \Pim\Component\Catalog\Model\ProductModelInterface) {
                    $childs[] = $this->container->get('pim_catalog.repository.product_model')->findChildrenProducts($childModel);
                }
            }
        } else {
            $childs = $this->container->get('pim_catalog.repository.product_model')->findChildrenProducts($model);
        }
        
        $data = [];
        $serializer = $this->container->get('pim_serializer');       
        $normalizeData = $serializer->normalize($childs, 'standard');
        foreach($normalizeData as $normalizeDataValue) {  
            if(!isset($normalizeDataValue['values'])) {
                foreach($normalizeDataValue as $value) {   
                    $variantAttributesData[] = array_intersect_key($value['values'], array_flip($variantAttributes));
                }    
            } else {
                $variantAttributesData[] = array_intersect_key($normalizeDataValue['values'], array_flip($variantAttributes));

            }
        }
        return $variantAttributesData;
    } 
    
    public function getProductByIdentifier($identifier = null)
    {
        $flag = false;
        $product = $this->container->get('pim_catalog.repository.product')->findOneByIdentifier($identifier);
        if($product) {
            $flag = true;
        } 
        return $flag;
    }

    public function getProductModelByCode($code = null)
    {
        $flag = false;
        $product = $this->container->get('pim_catalog.repository.product_model')->findOneByCode($code);
        if($product) {
            $flag = true;
        } 
        return $flag;
    }

    public function importMatchedProductLogger($data)
    {
        if(!$this->matchedSkuLogger) {
            $this->matchedSkuLogger = $this->container->get('webkul_shopify_jobs.import_matched.product.logger');
        }
        $this->matchedSkuLogger->info("Matched SKU : " . json_encode($data));
    }

    public function importUnmatchedProductLogger($data)
    {
        if(!$this->unmatchedSkuLogger) {
            $this->unmatchedSkuLogger = $this->container->get('webkul_shopify_jobs.import_unmatched.product.logger');
        }
        $this->unmatchedSkuLogger->info("Unmatched SKU : " . json_encode($data));
    }

    public function getPimRepository(string $type) 
    {
        $repository;
        
        if(!in_array($type,  ['locale', 'category', 'attribute', 'attribute_option', 'channel', 'family', 'product', 'productModel'])) {
            return null;
        }

        switch($type) {
            case 'locale': 
                $repository = 'pim_catalog.repository.locale';

                break;
            case 'category':
                $repository = 'pim_catalog.repository.category';

                break;
            case 'attribute':
                $repository = 'pim_catalog.repository.attribute';

                break;
            case 'attribute_option':
                $repository = 'pim_catalog.repository.attribute_option';

                break;
            case 'channel':
                $repository = 'pim_catalog.repository.channel';

                break;
            case 'family':
                $repository = 'pim_catalog.repository.family';

                break;
            case 'product':
                $repository = 'pim_catalog.repository.product';

                break;
            case 'productModel':
                $repository = 'pim_catalog.repository.product_model';

                break;
        }

        return $this->container->get($repository);
    }
}
