<?php

namespace Webkul\ShopifyBundle\Controller\Rest;

use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Webkul\ShopifyBundle\Repository\DataMappingRepository;
use Doctrine\ORM\EntityManager;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Webkul\ShopifyBundle\Entity\DataMapping;

class ExportMappingController extends Controller
{
    /** @var EntityManager */
    protected $em;

    /** @var DataMappingRepository */
    protected $dataMappingRepository;

    /** @var CategoryRepositoryInterface */
    protected $repository;

     /** @var NormalizerInterface */
     protected $normalizer;
    
      /** @var ObjectFilterInterface */
    protected $objectFilter;
 
    public function __construct(
        \CategoryRepositoryInterface $repository,
        NormalizerInterface $normalizer,
        \ObjectFilterInterface $objectFilter,
        DataMappingRepository $dataMappingRepository,
        EntityManager $em
    ) {
        
        $this->repository = $repository;
        $this->normalizer = $normalizer;
        $this->objectFilter = $objectFilter;
        $this->dataMappingRepository = $dataMappingRepository;
        $this->em = $em;
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

    public function deleteAction($id)
    {
        $mapping = $this->dataMappingRepository->find($id);
        if(!$mapping) {
            throw new NotFoundHttpException(
                    sprintf('Mapping with id "%s" not found', $id)
                );
        }
        
        $this->em->remove($mapping);
        $this->em->flush();
          
        return new Response("message", Response::HTTP_NO_CONTENT);
    }

    public function createAction(Request $request)
    {
        $data = json_decode($request->getContent(), true);
        $externalId;
        $code;
        
        if(isset($data['type']) && isset($data['apiUrl']) ) {
            if($data['apiUrl'] == '' || $data['apiUrl'] == NULL || filter_var($data['apiUrl'], FILTER_VALIDATE_URL) === FALSE) {
                
                return new JsonResponse(['apiUrl' => 'Invalid Url'],400);
            }  

            $codeIndex = $data['type'] === 'product' ? 'akeneoProductSku' : 'akeneoCategoryId';
            $externalIdIndex = $data['type'] === 'product' ? 'shopifyProductId' : 'shopifyCategoryId';
             
            if(isset($data[$codeIndex])) {
                $code = $data[$codeIndex];
            }
            if(isset($data[$externalIdIndex])) {
                $externalId = $data[$externalIdIndex];
            }
             
            if($code && $externalId ) {
                $checkMapping = $this->dataMappingRepository->findOneBy(['code' => $code]);
   
                if($checkMapping ) {
                    if($checkMapping->getExternalId()!= $externalId) {

                        return new jsonResponse([$codeIndex => 'Already Mapped'],400);
                    }
                } else {
                    $checkMapping = $this->dataMappingRepository->findOneBy(['externalId' => $externalId]);

                    if($checkMapping) {
                        
                      return new JsonResponse([$externalIdIndex => 'Already Mapped'],400);     
                   }

                }
                $mapping = $this->dataMappingRepository->findOneBy([
                    'entityType' => $data['type'],
                    'externalId' => $externalId,
                    'code' => $code,
                ]);
    
                if(!$mapping)
                {
                        $mapping = new DataMapping();
                        $mapping->setEntityType($data['type']);
                        $mapping->setExternalId($externalId);
                        $mapping->setCode($code);
                        $mapping->setApiUrl($data['apiUrl']);
                        
                        if($data['shopifyProductRelatedId'] && $data['type'] == 'product')
                            $mapping->setRelatedId($data['shopifyProductRelatedId']);
    
                        $this->em->persist($mapping);
                        $this->em->flush();
    
                    
                    return new JsonResponse([
                        'meta' => [
                            'id' => $mapping->getId()
                        ]
                    ]);
                } else {
                    return new JsonResponse([$externalIdIndex => 'Already Mapped'],400);
                }
            }  
        }  
        
        return new JsonResponse([$codeIndex => 'Already Mapped'],400);
    } 
  
   public function getTypes()
   {
       return [
           'Category' => 'category',
           'Product' => 'product'
        ];
    }
}

