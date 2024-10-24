<?php 
namespace SimpleData;

use SimpleData\SimpleArray;
use SimpleData\SimpleObject;
use SimpleData\SimpleData;


class SimpleValue{
    const TYPE = 'VALUE';

    /**
     * The current item value
     *
     * @var mixed
     */
    protected $value;
    /**
     * The node parent
     *
     * @var SimpleArray|SimpleObject|null
     */
    protected $parent = null;

    /**
     * Current key
     *
     * @var string
     */
    protected $key;
    
    /**
     * Initialize
     *
     * @param mixed $item
     * @param SimpleArray|SimpleObject|null $parent
     * @param string $key
     */
    public function __construct(&$item,&$parent = null,string $key = null){
        $this->value = &$item;
        $this->key  = $key;
        $this->parent = &$parent;
    }

    /**
     * Returns current item value
     *
     * @return mixed
     */
    public function value(){
        return $this->value;
    }

    /**
     * Alias of value
     *
     * @return mixed
     */
    public function raw(){
        return $this->value;
    }

    /**
     * Returns value key
     *
     * @return string|int|float
     */
    public function key(){
        return $this->key;
    }

    /**
     * Returns the parent object
     *
     * @return SimpleArray|SimpleObject|null
     */
    public function parent(){
        return $this->parent;
    }

    /**
     * Returns the value type
     *
     * @return void
     */
    public function type(){
        return gettype($this->value);
    }

    /**
     * Returns the data path, starting from 
     *
     * @param string $separator
     * @return string
     */
    public function path(string $separator = null){
        $separator  = $separator ?? SimpleData::$PATH_SEPARATOR;
        $path       = ($this->key() !== null)?[$this->key()]:[];
        $current    = $this;
        while($parent = $current->parent()){
            if($parent->key() !== null){
                array_unshift($path,$parent->key());
            }
            $current = $parent;
        }

        return implode($separator,$path);
    }

    /**
     * Tells if is a traversable SimpleData or a value
     *
     * @return boolean
     */
    public function iterable(){
        return false;
    }

    /**
     * Set the current value.
     *
     * @param mixed $value
     * @return self
     */
    public function set($value){
        if(
            is_a($value,SimpleObject::class) ||
            is_a($value,SimpleArray::class) ||
            is_a($value,static::class)
            ){
                if($this->parent()){
                    $this->parent()->set($this->key(),$value->raw());
                }
        }
        else{
            if($this->parent()){
                $this->parent()->set($this->key(),$value);
            }
        }

        $this->value = $value;
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
     * check if current instance is a value
     *
     * @return boolean
     */
    public function isValue()
    {
        return true;
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


    /**
     * check if given value matches the passed value. Wildcard * is allowed
     *
     * @param mixed $value
     * @param boolean $insensitive
     * @return boolean
     */
    public function match($value,bool $insensitive = false)
    {
        $value  = json_encode($value);

        $blocks = explode(SimpleData::$SEARCH_WILDCARD,$value);
        $blocks = array_map(function($block){
            return preg_quote($block,'#');
        },$blocks);

        $value = implode('.*',$blocks);
        $pregModifiers = ($insensitive)?'Usmi':'Usm';
        // dump(["#{$value}#{$pregModifiers}",json_encode($this->raw())]);
        return preg_match("#{$value}#{$pregModifiers}",json_encode($this->raw()));
    }

}
?>