<?php

namespace Drupal\mz_crud;

/**
 * Class CRUDService.
 */
class CRUDService implements CRUDInterface
{

    /**
     * Constructs a new CRUDService object.
     */
    public function __construct()
    {

    }

    public function paragraph($type, $fields, $reference_object = null)
    {
        return $this->save('paragraph', $type, $fields, $reference_object);
    }

    public function save($entity_type, $type, $fields, $reference_object = null)
    {
        $id_label = \Drupal::entityTypeManager()->getDefinition($entity_type)->getKey('id');
        $key_label = \Drupal::entityTypeManager()->getDefinition($entity_type)->getKey('label');
        if ($reference_object == null) {
            $reference_object = $this;
        }
        if (isset($fields[$id_label]) && is_numeric($fields[$id_label])) {
            $entity_new = \Drupal::entityTypeManager()->getStorage($entity_type)->load($fields[$id_label]);
        } else {
            $entity_new = $this->create_init_entity($entity_type, $type);
        }

        if (!empty($fields)) {
            $keys = array_keys($fields);
            if (!in_array($id_label, $keys)) {
                $fields[$id_label] = null;
            }
            if ($key_label!="" && !in_array($key_label, $keys)) {
                $fields[$key_label] = $key_label.' - EMPTY';
            }
            foreach ($fields as $key => $field) {

                if ($entity_new->hasField($key)) {
                    $status = true;
                    $field_type = $entity_new->get($key)->getFieldDefinition()->getType();
                    $setting_field = $entity_new->get($key)->getFieldDefinition()->getSettings();
                    //hook by type
                    if ($reference_object && $field_type && method_exists($reference_object, $field_type)) {
                        $entity_new = $reference_object->{$field_type}($entity_new, $key, $field);
                        $status = false;
                    }
                    ///hook by type
                    if($setting_field && isset($setting_field['target_type'])){
                        $func = $field_type.'_'.$setting_field['target_type'];
                        if ($reference_object && method_exists($reference_object, $func)) {
                            $entity_new = $reference_object->{$func}($entity_new, $key, $field);
                            $status = false;
                        }
                    }
                    //default
                    if ($status) {
                        $this->item_default($entity_new, $key, $field);
                    }

                }
            }

        }else{

        }
        $status = $entity_new->save();
        if($status){
          return  $entity_new ;
        }else{
          return null ;
        }
    }

    // ********* SAVE FUNCTION ENTITY ******** //

    protected function create_init_entity($entity_type, $bundle, $id = null)
    {
        $entity_def = \Drupal::entityTypeManager()->getDefinition($entity_type);
        $array = array(
            $entity_def->get('entity_keys')['bundle'] => $bundle
        );
        return \Drupal::entityTypeManager()->getStorage($entity_type)->create($array);
    }

    public function item_default($entity_parent, $field_name, $field_value)
    {
        $entity_parent->set($field_name, $field_value);
        return $entity_parent;
    }
    //********** Reference format *********//
    //default
    public function node($type, $fields, $reference_object = null)
    {
        return $this->save('node', $type, $fields, $reference_object);
    }

    //string
    public function string($entity_parent, $field_name, $field_value)
    {
        return $this->item_default($entity_parent, $field_name, $field_value);
    }

    //float
    public function float($entity_parent, $field_name, $field_value)
    {
        return $this->item_default($entity_parent, $field_name, $field_value);
    }
    // image
    public function image($entity_parent, $field_name, $field_value){
         $field_images =[];
         if(is_string($field_value)){
             $field_images[] = $this->saveImgFile($entity_parent, $field_name, $field_value);
         }
         if(is_array($field_value)){
             foreach ($field_value as $key => $image){
                 if(is_string($image) && is_numeric($key)){
                  $field_images[] = $this->saveImgFile($entity_parent,$field_name, $image);
                 }
                 if(isset($image['url'])){
                     $image_url = $image['url'] ;
                     $field_images[] = $this->saveImgFile($entity_parent,$field_name,$image_url,$image);
                 }
             }
             if(isset($field_value['url'])){
                 $image_url = $field_value['url'] ;
                 $field_images[] = $this->saveImgFile($entity_parent,$field_name,$image_url,$field_value);
             }
         }
         if(!empty($field_images)){
             $entity_parent->set($field_name,$field_images);
         }
         return $entity_parent ;
    }

    public function  saveImgFile($entity_parent ,$field_image,$field_value,$array=[]){
        $field_image_result =null;
        $filename = end(explode('/',$field_value));
        $data = file_get_contents($field_value);
        if($data){
            $setting = $entity_parent->get($field_image)->getSettings() ;
            $file_directory = ($setting['file_directory']);
            $path_root = 'public://'.$file_directory.'/' ;
            // Replace the token.
            $token_service = \Drupal::token();
            $path_root = $token_service->replace($path_root);
            if (file_prepare_directory($path_root, FILE_CREATE_DIRECTORY)) {
                $file = file_save_data($data,$path_root."/".$filename, FILE_EXISTS_REPLACE);
                $field_image_result = array(
                    'target_id'=>$file->id(),
                    'alt' => isset($array["alt"])?$array["alt"]: "",
                    'title' =>  isset($array["title"])?$array["title"]: "",
                );
            }else{
                $message = "Directory not found  " . $path_root ;
                \Drupal::logger("mig_crud")->error($message);
            }
        }else{
            $message = "Image not found ";
            \Drupal::logger("mig_crud")->error($message);
        }
        return $field_image_result ;
    }
    // paragraph
    public function entity_reference_revisions($entity_parent, $field_name, $field_value)
    {
        if (is_object($field_value)) {
            $paragraph[] = [
                'target_id' => $field_value->id(),
                'target_revision_id' => $field_value->getRevisionId(),
            ];
            $entity_parent->set($field_name, $paragraph);
        }
        if (is_numeric($field_value)) {
            $paragraph[] = [
                'target_id' => $field_value,
                'target_revision_id' => $field_value,
            ];
            $entity_parent->set($field_name, $paragraph);
        }
        if (is_array($field_value) && !empty($field_value)){
            foreach ($field_value as $item){
                if(is_numeric($item)){
                    $field_items[]= array(
                        "target_id"=> $item,
                        'target_revision_id' => $item
                    );
                }
                if(is_object($item) && $item->id()){
                    $field_items[]= array(
                        'target_id' => $item->id(),
                        'target_revision_id' => $item->getRevisionId(),
                    );
                }
            }
            $entity_parent->set($field_name, $field_items);
        }
        return $entity_parent;
    }
    //entity_reference
    public function entity_reference_media($entity_parent, $field_name, $field_value)
    {
        $setting_field = $entity_parent->get($field_name)->getFieldDefinition()->getSettings();
        $bundle = end($setting_field['handler_settings']['target_bundles']);
        $key_label = \Drupal::entityTypeManager()->getDefinition('media')->getKey('label');
        if(is_string($field_value)){
            switch ($bundle) {
                // Main module help for the content_export module.
                case 'image':
                    $filename = end(explode('/',$field_value));
                    $media = $this->save('media', $bundle,
                        [   'field_media_image'=>$field_value,
                            $key_label=>$filename
                        ]
                    );
                    $entity_parent->{$field_name}->entity = $media;
                 break;
            }

        }
        if (is_numeric($field_value)) {
            $entity_parent->{$field_name}->target_id = $field_value;
        }
        if (is_object($field_value)) {
            $entity_parent->{$field_name}->entity = $field_value;
        }
        if (is_array($field_value) && !empty($field_value)){
            foreach ($field_value as $item){
                if(is_numeric($item)){
                    $field_items[]= array("target_id"=> $item);
                }
                if(is_object($item)&& $item->id()){
                    $field_items[]= array(
                        'target_id' => $item->id()
                    );
                }
                if(is_string($item)){
                    switch ($bundle) {
                        // Main module help for the content_export module.
                        case 'image':
                            $filename = end(explode('/',$item));
                            $media = $this->save('media', $bundle,
                                [
                                    'field_media_image'=>$item,
                                    $key_label=>$filename
                                ]
                            );
                            $field_items[]= array(
                                'target_id' => $media->id()
                            );
                            break;
                    }
                }

            }
            $entity_parent->set($field_name, $field_items);
        }
        return $entity_parent;
    }
    protected function is_exits($entity_type,$bundle,$value){

        $key_label = \Drupal::entityTypeManager()->getDefinition($entity_type)->getKey('label');
        $bundle_label = \Drupal::entityTypeManager()->getDefinition($entity_type)->getKey('bundle');
        $query = \Drupal::entityQuery('taxonomy_term');
        $query->condition($key_label, $value);
        $query->condition($bundle_label, $bundle);
        $query->range(0,1);
        return $query->execute();

    }
    public function entity_reference_taxonomy_term($entity_parent, $field_name, $field_value)
    {
        $setting_field = $entity_parent->get($field_name)->getFieldDefinition()->getSettings();
        $bundle = end($setting_field['handler_settings']['target_bundles']);
        $entity_type = ($setting_field['target_type']);
        if (is_string($field_value)) {
            $term_exist = $this->is_exits($entity_type,$bundle,$field_value);
            if(sizeof($term_exist)==0){
                $term = $this->save('taxonomy_term', $bundle,
                    [
                        'name'=>$field_value
                    ]
                );
                $entity_parent->{$field_name}->entity = $term;
            }else{
                $entity_parent->{$field_name}->target_id = end($term_exist);
            }
        }
        if (is_object($field_value)) {
            $entity_parent->{$field_name}->entity = $field_value;
        }
        if (is_numeric($field_value)) {
            $entity_parent->{$field_name}->target_id = $field_value;
        }
        if (is_array($field_value) && !empty($field_value)){
            foreach ($field_value as $item){
                if(is_numeric($item)){
                    $field_items[]= array("target_id"=> $item);
                }
                if(is_object($item)&& $item->id()){
                    $field_items[]= array(
                        'target_id' => $item->id()
                    );
                }

                if (is_string($item)) {
                    $term_exist = $this->is_exits($entity_type,$bundle,$item);
                    if(sizeof($term_exist)==0){
                        $term = $this->save('taxonomy_term', $bundle,
                            [
                                'name'=>$field_value
                            ]
                        );
                        $entity_parent->{$field_name}->entity = $term;
                    }else{
                        $entity_parent->{$field_name}->target_id = end($term_exist);
                    }
                }
            }
            $entity_parent->set($field_name, $field_items);
        }
        return $entity_parent;
    }
    //entity_reference
    public function entity_reference($entity_parent, $field_name, $field_value)
    {

        if (is_object($field_value)) {
            $entity_parent->{$field_name}->entity = $field_value;
        }
        if (is_numeric($field_value)) {
            $entity_parent->{$field_name}->target_id = $field_value;
        }
        if (is_array($field_value) && !empty($field_value)){
            foreach ($field_value as $item){
                if(is_numeric($item)){
                $field_items[]= array("target_id"=> $item);
                }
                if(is_object($item)&& $item->id()){
                    $field_items[]= array(
                        'target_id' => $item->id()
                    );
                }
            }
            $entity_parent->set($field_name, $field_items);
        }
        return $entity_parent;
    }

    public function entity_reference_user($entity_parent, $field_name, $field_value)
    {
       return $this->entity_reference($entity_parent, $field_name, $field_value);
    }


}
