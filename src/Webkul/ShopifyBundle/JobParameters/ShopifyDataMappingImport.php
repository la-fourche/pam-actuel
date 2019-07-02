<?php

namespace Webkul\ShopifyBundle\JobParameters;

use Symfony\Component\Validator\Constraints\Collection;
use Symfony\Component\Validator\Constraints\Url;
use Symfony\Component\Validator\Constraints\Optional;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Constraints\Blank;
use Symfony\Component\Validator\Constraints\Count;
use Symfony\Component\Validator\Constraints\Type;
use Webkul\ShopifyBundle\Classes\Validators\Credential;

class ShopifyDataMappingImport implements
    \ConstraintCollectionProviderInterface,
    \DefaultValuesProviderInterface
    {
        /** @var string[] */
        private $supportedJobNames;
    
        /**
         * @param string[]                              $supportedJobNames
         */
        public function __construct(
            array $supportedJobNames
        ) {
            $this->supportedJobNames = $supportedJobNames;
        }
    
        /**
         * {@inheritdoc}
         */
        public function getDefaultValues()
        {
            // $channels = $this->channelRepository->getFullChannels();
            // $channels = [];
            // $defaultChannelCode = (0 !== count($channels)) ? $channels[0]->getCode() : null;
    
            // $localesCodes = $this->localeRepository->getActivatedLocaleCodes();
            $localesCodes = [];
            $defaultLocaleCode = (0 !== count($localesCodes)) ? $localesCodes[0] : null;
    
            $parameters['filters'] = [
                'structure' => [
                    // 'scope'   => $defaultChannelCode,
                    'locales' => [],

                ],
            ];
            $parameters['with_media'] = true;
            $parameters['realTimeVersioning'] = false;
            $parameters['enabledComparison'] = false;
            
            
    
            return $parameters;
        }
    
        /**
         * {@inheritdoc}
         */
        public function getConstraintCollection()
        {
            $constraintFields['user_to_notify'] = new Optional();
    
            $constraintFields['filters'] = new Optional();
            /* more strict filter, structure contraint */ 
            // $constraintFields['filters'] = [
            //     new Collection(
            //         [
            //             'fields'           => [
            //                 'structure' => [
            //                     new Collection(
            //                         [
            //                             'fields'             => [
            //                                 'locales'    => new Optional(), //[ new NotBlank(), new Count(1)]
            //                                 'locale'     => new NotBlank(),
            //                                 'currency'   => new NotBlank(),
            //                                 'scope'      => new NotBlank(),
            //                                 'attributes' => new Type(
            //                                     [
            //                                         'type'  =>  'array',
            //                                         'groups' => ['Default', 'DataFilters'],
            //                                     ]
            //                                 ),
            //                             ],
            //                             'allowMissingFields' => true,
            //                         ]
            //                     ),
            //                 ],
            //             ],
            //             'allowExtraFields' => true,
            //         ]
            //     ),
            // ];        
            $constraintFields['with_media'] = new Optional();
            $constraintFields['realTimeVersioning'] = new Optional();
            $constraintFields['enabledComparison'] = new Optional();
            $constraintFields['product_only'] = new Optional();
    
            $constraintFields['shopUrl'] = [ new Url(), new Credential() ];
            $constraintFields['apiKey'] = [ new Optional() ];
            $constraintFields['apiPassword'] = [ new Optional() ];        
            
            return new Collection([
                            'fields' => $constraintFields,
                            'allowMissingFields' => true, 
                            'allowExtraFields' => true,                       
                        ]);
        }
    
        /**
         * {@inheritdoc}
         */
        public function supports(\JobInterface $job)
        {
            return in_array($job->getName(), $this->supportedJobNames);
        }

        public function filterData()
        {
            if(class_exists('\Pim\Component\Connector\Validator\Constraints\FilterData')){
                return new \Pim\Component\Connector\Validator\Constraints\FilterData(['groups' => ['Default', 'DataFilters']]);
            }else{
                return new \Pim\Component\Connector\Validator\Constraints\ProductFilterData(['groups' => ['Default', 'DataFilters']]);
            }
        }
    }
