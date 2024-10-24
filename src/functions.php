<?php 
use SimpleData\SimpleArray;
use SimpleData\SimpleObject;
use SimpleData\SimpleValue;


if(!function_exists('simple_data')){
    /**
     * Create a datawalker 
     *
     * @param mixed $target
     * @return SimpleArray|SimpleObject|SimpleValue
     */
    function simple_data($data){
        if(
            is_a($data,SimpleObject::class) ||
            is_a($data,SimpleArray::class) ||
            is_a($data,SimpleValue::class)
        ){
            return $data;
        }

        if(is_object($data)){
            return new SimpleObject($data);
        }

        if(is_array($data)){
            return new SimpleArray($data);
        }

        return new SimpleValue($data);
    }
}



?>