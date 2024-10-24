<?php 
namespace SimpleData;

use SimpleData\SimpleValue;
use SimpleData\SimpleData;
use SimpleData\SimpleObject;


class SimpleArray extends SimpleData{
    const TYPE = 'ARRAY';


    /**
     * Reset data and indexes where items are added removed
     *
     * @param array $data
     * @return static
     */
    public function refresh(&$data = null){
        $data = $data ?? $this->raw();
        
        if(
            is_a($data,SimpleObject::class) ||
            is_a($data,SimpleValue::class)
        ){
            throw new SimpleDataException("Only array must be passed to ".static::class);
        }

        if(is_a($data,SimpleArray::class)){
            $data = $data->raw();
        }

        $this->__construct($data,$this->parent,$this->key);

        $parent = $this->parent();
        if($parent){
            $parent->set($this->key(),$this->raw());
        }

        return $this;
    }



    /**
     * Merge an array or a Waker object into current item
     *
     * @param array|SimpleData
     * @return self
     */
    public function merge($data){
        if(is_object($data) && is_a($data,static::class)){
            $data = $data->value();
        }
        elseif(!is_array($data)){
            $data = [$data];
        }

        $finalData = array_merge_recursive($this->data,$data);
        $this->refresh($finalData);
        return $this;
    }


    /**
     * Returns the array items count
     *
     * @return int
     */
    public function count(){
        return count($this->keys());
    }



    /**
     * Remove the first item in the array if exists and returns it
     *
     * @return mixed
     */
    public function shift(){
        if(!$this->keys){
            return null;
        }
        
        $data = (array)$this->data;
        $item = array_shift($data);

        $this->refresh($data);

        return simple_data($item);
    }

    /**
     * Remove the last item in the array if exists and returns it
     *
     * @return mixed
     */
    public function pop(){
        if(!$this->keys){
            return null;
        }
        
        $data = (array)$this->data;
        $item = array_pop($data);

        $this->refresh($data);

        return simple_data($item);
    }


    /**
     * Appends all the items, passed ss arguments, to the array
     *
     * @return self
     */
    public function append(){
        $args = func_get_args();
        $data = (array)$this->data;
        foreach($args as $item){
            $data[] = $item;
        }

        $this->refresh($data);

        return $this;
    }


    /**
     * Prepends all items, passed as arguments, to the array
     *
     * @return self
     */
    public function prepend(){
        $args = func_get_args();
        $data = (array)$this->data;

        array_unshift($data,...$args);

        $this->refresh($data);

        return $this;
    }

    /**
     * @inheritDoc
     */
    public function isArray()
    {
        return true;
    }

    /**
     * @inheritDoc
     */
    public function isNumericArray()
    {
        return strpos(json_encode($this->raw()),'[') === 0;
    }

}