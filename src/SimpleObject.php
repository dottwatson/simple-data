<?php 
namespace SimpleData;

use SimpleData\SimpleValue;
use SimpleData\SimpleData;
use SimpleData\SimpleArray;


class SimpleObject extends SimpleData{
    const TYPE = 'OBJECT';

    /**
     * Reset data and indexes where items are added removed
     *
     * @param object $data
     * @return static
     */
    public function refresh(&$data = null){
        if(
            !is_object($data) ||
            is_a($data,SimpleArray::class) ||
            is_a($data,SimpleValue::class)
        ){
            throw new SimpleDataException("Only objects must be passed to ".static::class);
        }

        if(is_a($data,SimpleObject::class)){
            $data = $data->raw();
        }
        
        $this->keys             = [];
        $this->__construct($data,$this->parent);

        return $this;
    }


    /**
     * @inheritDoc
     */
    public function isObject()
    {
        return true;
    }
}