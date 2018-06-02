<?php
/**
 *  Base include file for Mimini.
 * @package    Mimini
 * @subpackage Browser
 * @version    $Id: selector.php 1786 2008-04-26 17:32:20Z pp11 $
 */


class MiminiSelector{

    function __construct($selector){

    }
    /**
     * @param $widget MiminiTag
     * @return boolean
     */
    function isMatch($widget){ return false;}
    function hash(){
        return 'miminiSelector';
    }
}
class MiminiByAttributes extends MiminiSelector{
    protected $attributes=array();

    /**
     * @param array|object $attr
     */
    function __construct($attr){
        if(is_object($attr)){
            $attr=get_object_vars($attr);
        }
        $this->attributes=$attr;
    }
    function isMatch($tag){
        $matched=true;
        $attributes=$this->attributes;
        if(empty($attributes)){
            return false;
        }
        if(isset($attributes['id_or_name'])){
            $matched=$tag->getAttribute('id')==$attributes['id_or_name'] || $tag->getAttribute('name')==$attributes['id_or_name'];
            $attributes=[];
        }
        if(isset($attributes['label'])){
            $matched=$matched&&method_exists($tag, 'isLabel')&&$tag->isLabel($attributes['label']);
            unset($attributes['label']);
        }
        foreach($attributes as $k=>$v){
            if($tag->getAttribute($k)!==$v){
                $matched=false;
            }
        }
        return $matched;
    }
    function hash(){
        return md5($this->attributes);
    }

}
/**
 *    Used to extract form elements for testing against.
 *    Searches by name attribute.
 * @package Mimini
 * @subpackage Browser
 */
class MiminiByName extends MiminiByAttributes{
    function __construct($name){
        parent::__construct(array('name'=>$name));
    }
}

/**
 *    Used to extract form elements for testing against.
 *    Searches by visible label or alt text.
 * @package Mimini
 * @subpackage Browser
 */
class MiminiByLabel extends MiminiByAttributes
{
    /**
     * MiminiByLabel constructor.
     * @param string $label
     */
    function __construct($label){
        parent::__construct(array('label'=>$label));
    }
}

/**
 *    Used to extract form elements for testing against.
 *    Searches dy id attribute.
 * @package Mimini
 * @subpackage Browser
 */
class MiminiById extends MiminiByAttributes{

    function __construct($id){
        parent::__construct(array('id'=>$id));
    }
}

class MiminiByClass extends MiminiByAttributes{
    function __construct($class){
        parent::__construct(array('class'=>$class));
    }
}


class MiminiSelectorFactory{
    private static $caches;
    /**
     * @param $selector
     * @return MiminiSelector
     */
    static function getSelector($selector){
        if($selector instanceof MiminiSelector){
            return $selector;
        }
        if(is_array($selector)||is_object($selector)){
            $hash=md5(json_encode($selector));
            if(!isset(self::$caches[$hash])){
                self::$caches[$hash]=new MiminiByAttributes($selector);
            }
            return self::$caches[$hash];
        }
        if(is_string($selector)) {
            $hash=$selector;
            if(!isset(self::$caches[$hash])){
                $selectors=self::parse_selector($selector);
                self::$caches[$hash]=new MiminiByAttributes($selectors);
            }
            return self::$caches[$hash];
        }
        return new MiminiSelector($selector);
    }

    private static function parse_selector($selector_string) {
        $firstChar=$selector_string[0];
        if(!in_array($firstChar,array('.','+','#','[','@'))){
            return array('label'=>$selector_string);
        }
        // pattern of CSS selectors, modified from mootools
        // Paperg: Add the colon to the attrbute, so that it properly finds <tag attr:ibute="something" > like google does.
        // Note: if you try to look at this attribute, yo MUST use getAttribute since $dom->x:y will fail the php syntax check.
// Notice the \[ starting the attbute?  and the @? following?  This implies that an attribute can begin with an @ sign that is not captured.
// This implies that an html attribute specifier may start with an @ sign that is NOT captured by the expression.
// farther study is required to determine of this should be documented or removed.
//		$pattern = "/([\w-:\*]*)(?:\#([\w-]+)|\.([\w-]+))?(?:\[@?(!?[\w-]+)(?:([!*^$]?=)[\"']?(.*?)[\"']?)?\])?([\/, ]+)/is";
        $pattern = "/([\w-:\*]*)(?:\#([\w-]+)|\.([\w-]+))?(?:\[@?(!?[\w-:]+)(?:([!*^$]?=)[\"']?(.*?)[\"']?)?\])?([\/, ]+)/is";
        preg_match_all($pattern, trim($selector_string).' ', $matches, PREG_SET_ORDER);
        $selectors = array();
        //print_r($matches);

        foreach ($matches as $m) {
            $m[0] = trim($m[0]);
            if ($m[0]==='' || $m[0]==='/' || $m[0]==='//') continue;
            // for browser generated xpath
            if ($m[1]==='tbody') continue;

            list($tag, $key, $val) = array($m[1], null, null);
            if (!empty($m[2])) {$key='id'; $val=$m[2];}
            if (!empty($m[3])) {$key='class'; $val=$m[3];}
            if (!empty($m[4])) {$key=$m[4];}
            if (!empty($m[6])) {$val=$m[6];}

            // convert to lowercase
            //$tag=strtolower($tag); $key=strtolower($key);
            //elements that do NOT have the specified attribute


            if($key){
                $selectors[$key]=$val;
            }
            if($tag){
                $selectors['name']=$tag;
            }

        }
        return $selectors;
    }
}