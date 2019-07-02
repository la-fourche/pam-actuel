<?php
namespace Webkul\ShopifyBundle\Classes\DataFormatter\Export;

use Webkul\ShopifyBundle\Services\ShopifyConnector;

/*
 * Formate the tags according to the attribute and seprator
 * 
 * @author Navneet Kumar <navneetkumar.symfony813@webkul.com>
 * @copyright Copyright (c) Webkul Software Private Limited (https://webkul.com)
 * @license   https://store.webkul.com/license.html
 */
 

class TagsFormatter 
{
    /** @var ShopifyConnector */
    private $connectorService;

    /** @var string should contain the other mapping section*/
    CONST  OTHERMAPPING_SECTION = "shopify_connector_others";

    /**
     * @param ShopifyConnector $connectorService
     */
    public function __construct(ShopifyConnector $connectorService) 
    {
        $this->connectorService = $connectorService;
    }

    /**
     * 
     * formate the tags according to the other setting 
     * 
     * @var array fields
     * 
     * @var array attributes
     * 
     * @var string locale
     * 
     */
    public function formateTags($fields, $attributes, $locale)
    {
        //get the tags setting 
        $tags = $this->tagsByMapping($fields, $attributes, $locale);
        
        return $tags;
    }

    public function tagsByMapping($fields, $attributes, $locale) 
    {
        $tags = [];
        if(is_array($fields)) {
            $otherSetting = $this->connectorService->getSettings($this::OTHERMAPPING_SECTION);
            if(isset($otherSetting['enable_named_tags_attribute']) && filter_var($otherSetting['enable_named_tags_attribute'], FILTER_VALIDATE_BOOLEAN) ) {
                    $tags = $this->getTagsAsNamedTags($fields, $attributes, $locale);
            } elseif(isset($otherSetting['enable_tags_attribute']) &&  filter_var($otherSetting['enable_tags_attribute'], FILTER_VALIDATE_BOOLEAN)) {
                    $tags = $this->getTagsAsAttributeLabel($fields, $attributes, $locale);
            } else {
                    $tags = $this->getTags($fields, $attributes, $locale);
            }
        }
        return $tags;
    }

    /**
     * return the tags as formatted named tags
     */
    protected function getTagsAsNamedTags($fields, $attributes, $locale) 
    {
        $tags = [];
        $attributeTypes = $this->connectorService->getAttributeAndTypes();
        foreach($fields as $tagField) {
            if(is_array($attributes) && array_key_exists($tagField, $attributes)) {            

                $seprator = ':';
                
                //find the attribute lable according to locale
                $attributeLabel = $this->connectorService->getAttributeLabelByCodeAndLocale($tagField, $locale);
                $attributeLabel = str_replace('_', ' ', $attributeLabel);
                
                if(getType($attributes[$tagField]) === "string") {
                    //add the seprator with attribute label
                    $attributes[$tagField] = explode(',', $attributes[$tagField]);
                }
                if(is_array($attributes[$tagField])) {
                    if(strcasecmp($tagField , "GroupCode") === 0) {
                        foreach($attributes[$tagField] as $groupCode) {
                            // Group formate   = [Group Type Code] Seprator(:) [Group Code]
                            $attributes[$tagField] = $this->connectorService->getGroupTypeByCode($groupCode) . $seprator . str_replace('_', ' ', $groupCode);
                            $tags = array_merge($tags, (array)$attributes[$tagField]);
                        }
                    } else {
                        $attributeType = $attributeTypes[$tagField] ?? '';

                        if($attributeType === "pim_catalog_boolean" && isset($attributes[$tagField][0])) {
                            $attributes[$tagField][0]  = $attributes[$tagField][0] === "Yes" ? "true" : "false";
                        }

                        if(in_array($attributeType, array_keys($this->formateAttributeTypes))) {
                            foreach($attributes[$tagField] as $tag) {
                                $attributes[$tagField] = $attributeLabel . $seprator . $this->formateAttributeTypes[$attributeType] . $seprator. $tag;
                                $tags = array_merge($tags, (array)$attributes[$tagField]);
                            }
                        } else {
                            // multiselect type formating
                            foreach($attributes[$tagField] as $tag) {
                                $attributes[$tagField] = $attributeLabel . $seprator. $tag;
                                $tags = array_merge($tags, (array)$attributes[$tagField]);
                            }
                        }
                    } 
                } else {
                    $attributeType = $attributeTypes[$tagField] ?? '';
                    if($attributeType === "pim_catalog_boolean"  && isset($attributes[$tagField][0]) ) {
                        $attributes[$tagField][0]  = $attributes[$tagField][0] === "Yes" ? "true" : "false";
                    }
                    if(in_array($attributeType, array_keys($this->formateAttributeTypes))) {
                        $attributes[$tagField] = $attributeLabel . $seprator . $this->formateAttributeTypes[$attributeType] . $seprator. $attributes[$tagField];
                    } else {
                        $attributes[$tagField] = $attributeLabel . $seprator. $attributes[$tagField];                                
                    }
                    $tags = array_merge($tags, (array)$attributes[$tagField]);
                }                          
            }
        }
        
        $tags = implode(',', $tags); 

        return $tags;
    }

    /**
     * return the tags with attribute labels
     */
    protected function getTagsAsAttributeLabel($fields, $attributes, $locale)
    {
        $tags = [];
        $otherSetting = $this->connectorService->getSettings($this::OTHERMAPPING_SECTION);
     

       
        foreach($fields as $tagField) {
            if(is_array($attributes) && !empty($attributes[$tagField])) {            

                if(isset($otherSetting['tag-seprator'])) {
                    $seprator = $this->seprators[$otherSetting['tag-seprator']] ?? ':';
                } else {
                    $seprator = ':';
                }
                //find the attribute lable according to locale
                $attributeLabel = $this->connectorService->getAttributeLabelByCodeAndLocale($tagField, $locale);

                if(getType($attributes[$tagField]) === "string") {
                    //add the seprator with attribute label
                    $attributes[$tagField] =  explode(',', $attributes[$tagField]);
                }
              
                if(is_array($attributes[$tagField])) {
                    if(strcasecmp($tagField , "GroupCode") === 0) {
                        foreach($attributes[$tagField] as $groupCode) {
                            $attributes[$tagField] = $tagField . ' ' . $seprator . str_replace('_', ' ', $groupCode);
                            $tags = array_merge($tags, (array)$attributes[$tagField]);
                        }
                    } else {
                        foreach($attributes[$tagField] as $tag) {
                           
                            $attributes[$tagField] = $attributeLabel . $seprator. ' ' . $tag;
                            $tags = array_merge($tags, (array)$attributes[$tagField]);

                        }

                    }
                } else {
                    $tagField = $attributeLabel . $seprator. ' ' . $attributes[$tagField];
                    $attributes[$tagField] = $tagField;
                    $tags = array_merge($tags, (array)$attributes[$tagField]);
                }
            }
        }

        return implode(',', $tags);
    }


    /**
     * return the normal assign tags
     */
    protected function getTags($fields, $attributes, $locale)
    {
        $tags = [];
        $attributeTypes = $this->connectorService->getAttributeAndTypes();

        foreach($fields as $tagField) {
            if(is_array($attributes) && array_key_exists($tagField, $attributes)) {            
                if(isset($attributeTypes[$tagField]) && 'pim_catalog_boolean' === $attributeTypes[$tagField] ) {
                    $attributeLabel = $this->connectorService->getAttributeLabelByCodeAndLocale($tagField, $locale);
                    $attributes[$tagField] = $attributeLabel . '=' . $attributes[$tagField];  
                }

                $tags = array_merge($tags, (array)$attributes[$tagField]);
            }
        }

        $tags = implode(',', $tags); 

        return $tags;
    }

    /**
     * return the attribute type
     */
    protected function getAttributeType($attributeCode) 
    {
        $type = null;

        $attribute = $this->connectorService->getAttributeByCode($attributeCode);
        if($attribute) {
            $type = $attribute->getType();
        }

        return $type;
    }

    protected $formateAttributeTypes = [
        'pim_catalog_price_collection' => 'number',
        'pim_catalog_number'=> 'number',
        'pim_catalog_boolean' => 'boolean',
    ];

    private $seprators = [
        'colon' => ':',
        'dash' => '-',
        'space' => ' ',
    ];
}