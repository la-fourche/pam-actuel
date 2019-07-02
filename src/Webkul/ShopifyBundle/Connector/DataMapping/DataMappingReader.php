<?php

namespace Webkul\ShopifyBundle\Connector\DataMapping;

use Symfony\Component\HttpFoundation\Response;
use Webkul\ShopifyBundle\Services\ShopifyConnector;
use Webkul\ShopifyBundle\Connector\Reader\Import\ProductReader;


class DataMappingReader extends ProductReader implements \ItemReaderInterface, \InitializableInterface, \StepExecutionAwareInterface
{
    const AKENEO_ENTITY_NAME = 'product';

    const AKENEO_VARIANT_ENTITY_NAME = 'product';

    protected $itemIterator;

    /** @var EntityManager */
    protected $em;

     /** @var \FileStorerInterface */
    protected $storer;

    protected $items;

     /** @var \FileInfoRepositoryInterface */
    protected $fileInfoRepository;

    protected $uploadDir;

    protected $page;
    
    public function __construct (
        ShopifyConnector $connectorService,
        \Doctrine\ORM\EntityManager $em,
        \FileStorerInterface $storer,
        \FileInfoRepositoryInterface $fileInfoRepository,
        $uploadDir
    )
    { 
        parent::__construct($connectorService, $em, $storer, $fileInfoRepository, $uploadDir);
        $this->em = $em;
        $this->storer = $storer;
        $this->fileInfoRepository = $fileInfoRepository;
        $this->uploadDir = $uploadDir;
    }
  
    public function initialize()
    {
        $this->page = 0;   
    }

    public function read()
    { 
        if($this->itemIterator === null){
            $this->page++;
            $this->items = $this->getProductsByPage($this->page);
            $this->itemIterator = new \ArrayIterator([]);
            if(!empty($this->items)) {
                $this->itemIterator = new \ArrayIterator($this->items);
            }
        }   

        $item = $this->itemIterator->current();
        
        if($item !== null) {
            $this->stepExecution->incrementSummaryInfo('read');
            $this->itemIterator->next();
        } else {
            $this->page++;
            $this->items = $this->getProductsByPage($this->page);
            if(!empty($this->items)) {
                $this->itemIterator = new \ArrayIterator($this->items);
            }
            $item = $this->itemIterator->current();
            if($item !== null) {
                $this->stepExecution->incrementSummaryInfo('read');
                $this->itemIterator->next();
            }
        }

        return  $item;
    }

    protected function getProductsByPage($page) 
    {
        $items = [];
        $fields = ['fields' => 'id,title,handle,variants', 'page' => $page];

        try {
            $response = $this->connectorService->getProductsByFields($fields);
        } catch (\Exception $e) {
            $this->stepExecution->addWarning('Warning', new \DataInvalidItem([$e->getMessage()]));
        }

        if(empty($response['error'])) {
            $items = $this->formateData($response);                
        }

        return $items;
    }

    /**
     * [ [a] ];
     */
    protected function formateData($products = array())
    {
        $items = [];

        $type = self::AKENEO_ENTITY_NAME;

        foreach ($products as $product) {
            if(isset($product['variants'])) {
                foreach ($product['variants'] as $key => $variant) {
                    if(!isset($variant['title']) 
                        && !isset($variant['id'])
                        && !isset($variant['product_id'])
                        && !isset($variant['sku'])) {
                            continue;
                    }

                    if(isset($variant['title']) && $variant['title'] != "Default Title") {
                        $type = self::AKENEO_VARIANT_ENTITY_NAME;
                    } else {
                        $type = self::AKENEO_ENTITY_NAME;
                    }

                    if($type == self::AKENEO_VARIANT_ENTITY_NAME) {
                        $externalId = $variant['id'];
                        $relatedId = $variant['product_id'];
                        // Parent Product Mapping
                        if($key === 0) {
                            $items[] = [
                                'code' => $this->connectorService->verifyCode($product['handle']),
                                'externalId' => $product['id'],
                                'relatedId' => null,
                                'entityType' => self::AKENEO_ENTITY_NAME,
                                'type' => 'product_model'
                            ];
                        }
                    } else {
                        $externalId = $product['id'];
                        $relatedId = $variant['id'];
                    }

                    $items[] = [
                        'code' => $variant['sku'],
                        'externalId' => $externalId,
                        'relatedId' => $relatedId,
                        'entityType' => $type,
                        'type' => 'product'
                    ]; 
                }            
            }
        }

        return $items;
    }

   

}
