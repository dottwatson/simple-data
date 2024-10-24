<?php 
namespace SimpleData;

use ArrayObject;
use SimpleData\SimpleValue;
use SimpleData\SimpleArray;
use SimpleData\SimpleObject;

abstract class SimpleData{
    public static $PATH_SEPARATOR = '/';
    public static $SEARCH_WILDCARD = '*';

    /**
     * Internal data
     *
     * @var object|\ArrayObject
     */
    protected $data;
    
    /**
     * The parent array node
     *
     * @var \SimpleData\SimpleArray|\SimpleData\SimpleObject
     */
    protected $parent = null;

    /**
     * the item key
     *
     * @var string|int|float
     */
    protected $key;

    /**
     * Initialize
     *
     * @param array|object $data
     * @param SimpleData|null $parent
     */
    public function __construct(&$data,SimpleData &$parent = null,$key = null){
        if($this->isArray()){
            if(!is_array($data) && !is_a($data,SimpleArray::class)){
                throw new SimpleDataException("Trying to create a SimpleArray instance with a not array value");
            }

            $data = (is_a($data,SimpleArray::class))?$data->raw():$data;
            $this->data = new ArrayObject($data);

            $this->data->setFlags(ArrayObject::ARRAY_AS_PROPS);
        }
        elseif($this->isObject()){
            if(!is_object($data) && !is_a($data,SimpleObject::class)){
                throw new SimpleDataException("Trying to create a SimpleObject instance with a not object value");
            }

            $data = (is_a($data,SimpleObject::class))?$data->raw():$data;
            $this->data = $data;
        }
        
        $this->key      = $key;
        $this->parent   = &$parent;
    }

    /**
     * Returns the data keys
     *
     * @return array
     */
    public function keys(){
        if($this->isObject()){
            $props = get_object_vars($this->data);
            return array_keys($props);
        }
        elseif($this->isArray()){
            return array_keys($this->data->getArrayCopy());
        }
    }

    /**
     * Get indexed items in current data
     *
     * @return SimpleArray[]|SimpleObject[]|Value[]
     */
    public function items(){
        $results = [];
        foreach($this->keys() as $key){
            $results[$key] = $this->get($key);
        }

        return $results;
    }

    /**
     * Alias of raw()
     *
     * @return mixed
     */
    public function value(){
        return $this->data;
    }


    /**
     * Check if key exists
     *
     * @param string $key
     * @return boolean
     */
    public function has(string $key){
        return in_array($key,$this->keys());
    }


    /**
     * Returns the current key in parent
     *
     * @return string|int|float|null
     */
    public function key(){
        return $this->key;
    }



    /**
     * Returns the real value, deeply converted
     *
     * @return mixed
     */
    public function raw(){

        $data = $this->data;

        if($this->isArray()){
            $data = (array)$this->data;
            array_walk_recursive($data,function(&$item){
                if(
                    is_a($item,SimpleObject::class) ||
                    is_a($item,SimpleArray::class) ||
                    is_a($item,SimpleValue::class)
                ){
                    $item =  $item->raw();
                }
            });
        }
        elseif($this->isObject()){
            $data = $this->data;
            $props = get_object_vars($data);
            foreach($props as $key=>$prop){
                if(
                    is_a($prop,SimpleObject::class) ||
                    is_a($prop,SimpleArray::class) ||
                    is_a($prop,SimpleValue::class)
                ){
                    $data->{$key} = $prop->raw();
                }
            }
        }

        return $data;
    }

    /**
     * Returns the parent node in the array
     *
     * @return SimpleArray|SimpleObject|null
     */
    public function parent(){
        return $this->parent;
    }


    /**
     * Reload the state of data, useful when data changes
     *
     * @param array|object $data
     * @return static
     */
    abstract public function refresh(&$data = null);
    

    /**
     * Returns an item from array by its key
     *
     * @param string|int|float $key
     * @return SimpleArray|SimpleObject|SimpleValue|null
     */
    public function get($key){
        $key        = (string)$key;
        $keyInfo    = $this->parseKey($key);
        $fn         = strtolower($keyInfo['fn']);
        $fnValue    = $keyInfo['value'];
        $currentKey = $keyInfo['key'];

        if($currentKey == ''){
            if(!$fn){
                return $this;
            }
        
            switch($fn){
                case 'parent':
                    return $this->parent();
                break;
                case 'ntChild':
                    return $this->nthChild($fnValue);
                break;
                case 'first':
                    return $this->first();
                break;
                case 'last':
                    return $this->last();
                break;
                case 'closest':
                    return $this->closest($fnValue);
                break;
            }
        }

        if($this->has($currentKey)){
            if($fn && in_array(strtolower($fn),['parent','ntChild','first','last','closest'])){
                $item = $this->get($currentKey);
                return ($item && $item->iterable() )
                    ?call_user_func([$item,$fn],$fnValue)
                    :null;
            }

            if(is_object($this->data->{$currentKey})){
                if(
                    is_a($this->data->{$currentKey},SimpleObject::class) ||
                    is_a($this->data->{$currentKey},SimpleArray::class) ||
                    is_a($this->data->{$currentKey},SimpleValue::class)
                    ){
                        return $this->data->{$currentKey};
                }
                else{
                    return new SimpleObject($this->data->{$currentKey},$this,$currentKey);
                }
            }
            elseif(is_array($this->data->{$currentKey})){
                return new SimpleArray($this->data->{$currentKey},$this,$currentKey);
            }
            else{
                return new SimpleValue($this->data->{$currentKey},$this,$currentKey);
            }
        }
    }
    
    /**
     * Returns the full data path , included current key 
     *
     * @param string $separator
     * @return string
     */
    public function path(string $separator = null){
        $separator = $separator ?? static::$PATH_SEPARATOR;

        $path    = ($this->key() !== null)?[$this->key()]:[];
        $current = $this;
        while($parent = $current->parent()){
            if($parent->key() !== null){
                array_unshift($path,$parent->key());
            }
            $current = $parent;
        }

        return implode($separator,$path);
    }


    /**
     * Returns an item or null, based on its path relative to current element where search starts
     *
     * @param string $path
     * @param string $separator
     * @return SimpleArray|SimpleObject|SimpleValue|null
     */
    public function find(string $path,string $separator = null){
        $separator = $separator ?? static::$PATH_SEPARATOR;

        if($path === ''){ 
            return null;            
        }
        elseif($path == '*'){
            return $this->iterable()?$this:null;
        }

        $bits = explode($separator,$path);

        if(in_array('*',$bits)){
            $paths = $this->buildFullPaths($bits,$separator);
            $results = [];

            foreach($paths as $path){
                $value = $this->find($path,$separator);

                if(!is_null($value)){
                    $results[] =$value;
                }
            }

            return simple_data($results);
        }
        else{
            $item = $this;
            while(($bit = array_shift($bits)) !== null){

                $currentItem = $item->get($bit);

                if(count($bits) == 0){
                    return $currentItem;
                }
                elseif( $currentItem === null || !$currentItem->iterable() ){
                    return null;
                }
    
                $item = $currentItem;
            }
            
            return null;
        }
    }


    /**
     * Returns the first item in the array or object properties if exists
     *
     * @return SimpleArray|SimpleObject|SimpleValue|null
     */
    public function first(){
        $keys = $this->keys();

        if(!$keys){
            return null;
        }

        $getKey = array_shift($keys);

        return $this->get($getKey);
    }


    /**
     * Returns the last item in the array or object properties if exists
     *
     * @return SimpleArray|SimpleObject|Value|null
     */
    public function last(){
        $keys = $this->keys();

        if(!$keys){
            return null;
        }

        $getKey = array_pop($keys);

        return $this->get($getKey);
    }

    /**
     * Returns the first matching parent where its key matches the given key
     *
     * @param string|int $key
     * @return SimpleArray|SimpleObject|null
     */
    public function closest($key){
        $currentItem = $this;
        while($parent = $currentItem->parent()){
            if($parent->key() == $key){
                return $parent;
            }
            $currentItem = $parent;
        }
    
        return null;
    }


    /**
     * adds a pair key => value item. If exists, will be overwritten
     *
     * @param string $key
     * @param mixed $value
     * @return static
     */
    public function set($key,$value = null,string $separator = null){
        $separator      = $separator ?? static::$PATH_SEPARATOR;
        $bits           = explode($separator,(string)$key);


        $currentNode = &$this->data;
        while(count($bits) > 0){
            $bit            = array_shift($bits);
            $currentNode    = &$currentNode->{$bit} ?? null;
        }

        $currentNode = $value;

        if($this->parent()){
            $this->parent()->set($this->key(),$this->raw());
        }

        return $this;
    }

    /**
     * unset item in objct or array, or iteself without key. 
     * If this is a value,remove it from parent and makes this object orphan.
     *
     * @return SimpleArray|SimpleObject|Value|false
     */
    public function unset(string $key = '',string $separator = '/')
    {
        $separator = $separator ?? static::$PATH_SEPARATOR;
        
        if($key !== '' && $this->iterable() && $this->has($key)){
            $deletingObject = $this->get($key,$separator);

            if($deletingObject){
                $deletingObject->remove();
            }

            return $this;
        }
        elseif($key === ''){
            return $this->remove();
        }
    }

    /**
     * remove this item 
     *
     * @return SimpleArray|SimpleObject|Value
     */
    public function remove()
    {
        $parent = $this->parent();
        $key    = $this->key();

        
        if($parent){
            $data = $parent->raw();
            if($parent->isObject() && $key !== null){
                unset($data->{$key});
            }
            elseif($parent->isArray() && $key !== null){
                $isArray        = $parent->isArray();
                $isNumericArray = $parent->isNumericArray();
                unset($data[$key]);
                if($isArray && $isNumericArray){
                    $data = array_values($data);
                }
            }

            
            $parent->refresh($data);
        }


        $this->key      = null;
        $this->parent   = null;

        return $this;
    }


    /**
     * search a value in the data. Wildcard * is allowed
     * Returns a pair key=>value array where key is the path and value a SimpleData o SimpleValue object
     *
     * @param mixed $value
     * @param string|null $separator
     * @param boolean $insensitive
     * @return array<SimpleArray|SimpleObject|SimpleValue>
     */
    public function search($value,string $separator = null,bool $insensitive = false)
    {
        $separator  = $separator ?? SimpleData::$PATH_SEPARATOR;
        $results    = [];
        $items      = $this->items();

        while($item = array_shift($items)){
            if($item->iterable()){
                foreach($item->search($value,$separator) as $foundItem){
                    $results[] = $foundItem;
                }
            }
            else{
                $match = $item->match($value);
                if($match){
                    $results[] = $item;
                }
            }
        }
    
        return $results;
    }

    /**
     * search a value in the data with insensitive mode. Wildcard * is allowed
     * Returns a pair key=>value array where key is the path and value a SimpleData o SimpleValue object
     *
     * @see \SimpleData\SimpleData::search()
     * @param mixed $value
     * @param string|null $separator
     * @return array
     */
    public function isearch($value,string $separator = null)
    {
        return $this->search($value,$separator,true);
    }


    public function flatten(bool $rawValues = false,string $separator = null)
    {
        $separator  = $separator ?? SimpleData::$PATH_SEPARATOR;
        $results    = [];
        $items      = $this->items();

        while($item = array_shift($items)){
            if($item->iterable()){
                $results = [...$results,...$item->flatten($rawValues,$separator)];
            }
            else{
                $results[$item->path($separator)] = ($rawValues)
                    ?$item->raw()
                    :$item;
            }
        }
    
        return $results;
    }

    /**
     * Retrieve a child from its numeric index or a closure. 
     * The childs counters starts from 1
     * If $keyNumber is a Closure, the other extra parameters will be sent to the closure 
     *
     * @param int|Closure $keyNumber
     * @return SimpleData|Value|null
     */
    public function nthChild($keyNumber){
        $args           = func_get_args();
        $keyNumber      = $this->realKeyClosure(...$args);
        $keyNumber -=   1;      

        return(isset($this->keys[$keyNumber]))
            ?$this->get($this->keys[$keyNumber])
            :null;
    }


    /**
     * Tell if is a valid array (a traversable SimpleData)
     *
     * @return boolean
     */
    public function iterable(){
        return true;
    }

    /**
     * check if current instance is object
     *
     * @return boolean
     */
    public function isObject()
    {
        return false;
    }

    /**
     * check if current instance is array
     *
     * @return boolean
     */
    public function isArray()
    {
        return false;
    }

    /**
     * check if current instance is a numeric array
     *
     * @return boolean
     */
    public function isNumericArray()
    {
        return false;
    }

    /**
     * check if current instance is a numeric array
     *
     * @return boolean
     */
    public function isValue()
    {
        return false;
    }

    

    /**
     * Check if variable 
     *
     * @param int|Closure $keyNumber
     * @return string
     */
    protected function realKeyClosure($key){
        $args   = func_get_args();
        if(is_object($key) && is_a($key,\Closure::class)){
            $args = func_get_args();
            $closure = array_shift($args);
            
            $key = $closure(...$args);
        }

        return $key;
    }


    /**
     * parse a requested array key and evaluitate if is comprensive of pseudo selectors
     *
     * @param string $key
     * @return array
     */
    protected function parseKey($key){
        preg_match('#^(?<key>.*)?(::(?P<pseudo_rule>(?P<fn>.+)\((?P<value>.*)\)))?$#U',$key,$info);
        return [
            'key'   =>$info['key'],
            'fn'    =>(isset($info['fn']))?$info['fn']:false,
            'value' =>(isset($info['value']))?$info['value']:null,
        ];
        
    }

    /**
     * Build full paths for collection result
     *
     * @param array $pathBits
     * @param string $separator
     * @return array
     */
    protected function buildFullPaths(array $pathBits,string $separator = null){
        $separator = $separator ?? static::$PATH_SEPARATOR;
        $arrayPaths = [];
        $node       = $this;
        $k          = 0;
        $cntBits    = count($pathBits);

        while($bit = array_shift($pathBits)){
            $arrayPaths[$k] = [];
            if($bit == '*'){
                if(!$node || !$node->iterable()){
                }
                else{
                    $arrayPaths[$k] = array_merge($arrayPaths[$k],$node->keys());
                    $tmp = [];
                    foreach($node->items() as $item){
                        if($item->iterable()){
                            $tmp = array_merge($tmp,$item->items());
                        }
                        else{
                            $tmp = array_merge($tmp,[$item]);
                        }
                    }
                    $node = simple_data($tmp);
                }
            }
            else{
                $arrayPaths[$k] = [$bit];
                $node           = $node->get($bit);
            }
            $k++;
        }
    
        $paths  = [];

        foreach($arrayPaths as $i=>$arrayPath){
            if($i == 0){
                $paths = $arrayPath;
            }
            else{
                $tmp = [];
                foreach($arrayPaths[$i]  as $nextArrayItem){
                    foreach($paths as $k=>$pathItem){
                        $newItem = $pathItem.$separator.$nextArrayItem;
                        $tmp[] = $newItem;
                    }
                }
                $paths = $tmp;
            }
        }

        return $paths;
    }


    public function root()
    {
        $item   = $this;
        $cnt    = 0;
        while($item->parent()){
            $item = $item->parent();                        
            $cnt++;
        }

        return($cnt > 0)?$this:$item;
    }

    public function jsonPath()
    {

        $path    = [];
        $current = $this;
        while($parent = $current->parent()){
            if($parent->isArray()){
                array_unshift($path,"[{$current->key()}]");
            }
            else{
                array_unshift($path,".{$current->key()}");
            }
            $current = $parent;
        }

        return implode($path);
    }

}