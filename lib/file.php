<?php 

namespace Kirby\CMS;

use Kirby\Toolkit\Asset;
use Kirby\Toolkit\C;
use Kirby\Toolkit\F;
use Kirby\CMS\File\Content;

// direct access protection
if(!defined('KIRBY')) die('Direct access is not allowed');

/**
 * File
 * 
 * The File object is used for all files
 * in any subfolder of the content directory. 
 * It's the base file class, which can be converted
 * to ImageFile or ContentFile classes if appropriate
 * 
 * @package   Kirby CMS
 * @author    Bastian Allgeier <bastian@getkirby.com>
 * @link      http://getkirby.com
 * @copyright Bastian Allgeier
 * @license   http://getkirby.com/license
 */
class File extends Asset {

  // the parent Files object
  protected $parent = null;
  
  // unique id string
  protected $id = null;

  // the uri (url relative to the content directory)
  protected $uri = null;
    
  // the next file
  protected $next = null;

  // the previous file
  protected $prev = null;

  // cache for all attached meta info objects
  protected $metas = null;

  // cache for the attached meta info object
  protected $meta = null;
  
  // cache for the default meta file 
  protected $defaultMeta = null;

  /**
   * Constructor
   * 
   * @param string $root The full root/path of the file
   * @param object $parent The parent Files object
   */
  public function __construct($root, Files $parent = null) {
    $this->root   = realpath($root);
    $this->id     = md5($root);
    $this->parent = $parent;
  } 

  /**
   * Resets all cached attributes which could make trouble
   */
  public function reset() {
    $this->metas = null;
    $this->meta  = null;
    $this->parent->reset();
  }

  /**
   * Setter and getter for the parent Files object
   * Pass a Files object to use this as setter
   * Without a passed argument this will return the parent object
   * 
   * @param object $parent The parent Files object 
   * @return object Files
   */
  public function parent(Files $parent = null) {
    if(!is_null($parent)) return $this->parent = $parent;
    return $this->parent;
  }

  /**
   * Returns the parent Page object
   * 
   * @return object Page
   */
  public function page() {
    return $this->parent()->page();
  }

  /**
   * Returns the unique id for this file
   * 
   * @return string
   */
  public function id() {
    return $this->id;
  }

  /**
   * Returns the URI for the file. 
   * The URI is the URL to its location within the content folder
   * without the base url of the site. 
   * i.e. content/somefolder/somesubfolder/somefile.jpg
   * 
   * @return string
   */
  public function uri() {
    return $this->page()->diruri() . '/' . $this->filename();
  }

  /**
   * Returns the full URL to the file
   * i.e. http://yourdomain.com/content/somefolder/somesubfolder/somefile.jpg
   *
   * @return string
   */
  public function url() {
    if(!is_null($this->url)) return $this->url;
    return $this->url = site::instance()->url() . '/' . $this->uri();
  }

  /**
   * Returns the file type i.e. image
   * Is also being used as setter
   * 
   * Available file types by default are:
   * image, video, document, sound, content, meta, other
   * See the kirby/defaults.php for config options to 
   * refine type categorization
   *
   * @param string $type 
   * @return string
   */
  public function type($type = null) {

    // setter    
    if(!is_null($type)) return $this->type = $type;

    // get the cached type if available
    if(!is_null($this->type)) return $this->type;

    // check for content files
    if($this->extension() == c::get('content.file.extension', 'txt')) {
      return $this->type = 'content';
    }

    return parent::type();    

  }

  // Traversing

  /**
   * Returns all siblings of this file 
   * in a Files collection
   * 
   * @return object Files   
   */
  public function siblings() {
    return $this->parent()->not($this->filename());
  }

  /**
   * Returns the previous file
   * 
   * @return object File   
   */
  public function prev() {

    if(!is_null($this->prev)) return $this->prev;

    $index  = $this->parent()->indexOf($this);    
    $values = array_values($this->parent()->toArray());
    
    return $this->prev = a::get($values, $index-1);    

  }

  /**
   * Checks if there is a previous file
   * 
   * @return boolean
   */
  public function hasPrev() {
    return ($this->prev()) ? true : false;
  }

  /**
   * Returns the next file
   * 
   * @return object File   
   */
  public function next() {

    if(!is_null($this->next)) return $this->next;

    $index  = $this->parent()->indexOf($this);    
    $values = array_values($this->parent()->toArray());
    
    return $this->next = a::get($values, $index+1);    
  
  }

  /**
   * Checks if there is a next file
   * 
   * @return boolean
   */
  public function hasNext() {
    return ($this->next()) ? true : false;
  }

  /**
   * Returns a md5 hash of this file's root
   * 
   * @return string
   */
  public function hash() {
    return md5($this->root);
  }

  // Meta information

  /**
   * Returns all available meta files for this file
   * 
   * @return object Files
   */
  public function metas() {

    if(!is_null($this->metas)) return $this->metas;

    $metas = clone $this->page()->metas();
    $preg  = '!^' . preg_quote($this->filename()) . '!i';

    foreach($metas->toArray() as $key => $meta) {
      if(!preg_match($preg, $meta->name())) $metas->remove($key);
    }

    return $this->metas = $metas;

  }

  /**
   * Returns the meta info object
   * which will be used to fetch custom variables for the file
   * 
   * @return object Content
   */
  public function meta($lang = null) {

    // multi-language handling
    if(site::$multilang) {

      // initiate the cache if not done yet
      if(is_null($this->meta) || !is_array($this->meta)) $this->meta = array();

      // get the current applicable language code
      $lang = (is_null($lang)) ? c::get('lang.current') : $lang;

      // in cache? 
      if(isset($this->meta[$lang])) return $this->meta[$lang];

      // find the matching content file, store and return it
      $meta = $this->metas()->filterBy('languageCode', $lang)->first();

      // fall back to the default language
      if(!$meta) $meta = $this->defaultMeta();
    
      // store and return the meta
      return $this->meta[$lang] = $meta;

    }

    // single language handling
    if(!is_null($this->meta)) return $this->meta;
    return $this->meta = $this->metas()->first();

  }

  /**
   * Checks if a meta file is availabel for this file
   * 
   * @return boolean
   */
  public function hasMeta($lang = null) {
    return ($this->meta($lang)) ? true : false;
  }

  /**
   * Returns the default meta info object 
   * for multi-language support
   * 
   * @return object Content
   */
  public function defaultMeta() {
    if(!is_null($this->defaultMeta)) return $this->defaultMeta;
    return $this->defaultMeta = $this->metas()->filterBy('languageCode', c::get('lang.default'))->first();
  }

  // magic getters

  /**
   * Enables getter function calls for custom fields
   * i.e. $file->title()
   * 
   * @param string $key this is auto-filled by PHP with the called method name
   * @return mixed
   */
  public function __call($key, $arguments = null) {    
    return ($this->meta()) ? $this->meta()->$key() : null;
  }

  /**
   * Enables pseudo attributes for custom fields
   * i.e. $file->title
   * 
   * @param string $key this is auto-filled by PHP with the called attribute name
   * @return mixed
   */
  public function __get($key) {
    return ($this->meta()) ? $this->meta()->$key() : null;
  }

  /**
   * Setter for overwriting data
   * 
   * @param mixed $key
   * @param mixed $value
   */
  public function set($key, $value = '') {

    // check for meta data for this file
    $meta = $this->meta();
  
    // create the meta content file if it doesn't exist yet
    if(!$meta) $meta = content::create($this);

    if(is_array($key)) {
      foreach($key as $k => $v) $meta->set($k, $v);
      return true;
    } else {
      $meta->set($key, $value);
    }

  }

  /**
   * Magic setter
   * 
   * @param mixed $key
   * @param mixed $value
   */  
  public function __set($key, $value) {
    $this->set($key, $value);
  }

  /**
   * Saves the file's metadata
   * 
   * @return boolean
   */
  public function save() {
    return $this->meta()->save();
  }

  /**
   * Renames the filename and takes care of 
   * renaming all meta files as well.
   * 
   * @param string $name The filename without extension
   * @return object
   */
  public function rename($name) {

    // base root
    $root = $this->dir() . DS . $name;

    // add the language code if applicable
    if(site::$multilang and is_a($this, 'Kirby\\CMS\\File\\Content')) {
      $root .= '.' . $this->languageCode();      
    }

    // add the extension
    $root .= '.' . $this->extension();

    // check if the new file already exists
    if(file_exists($root)) raise('The file already exists', 'file-exists');

    // try to move all meta files
    foreach($this->metas() as $meta) {
      $meta->rename($name . '.' . $this->extension());
    }

    // try to move the thumbnail
    if($this->type() == 'image' and $this->hasThumb()) {
      $this->thumb()->rename($name . '.thumb');      
    }

    // try to move the file itself
    if(!f::move($this->root(), $root)) raise('The file could not be renamed', 'move-failed');

    // reset the essentials 
    $this->root = $root;
    $this->reset();

    return $this;

  }

  /** 
   * Deletes the file
   * 
   * @return boolean
   */
  public function delete() {
    
    // don't delete text files
    if($this->type() == 'content') {
      raise('Content files cannot be deleted', 'unauthorized');    
    }

    // delete all meta files if they exist
    foreach($this->metas() as $meta) {
      if(!\Kirby\Toolkit\f::remove($meta->root())) {
        raise('Not all meta files could be deleted', 'meta-delete-failed');
      }
    }

    // try to delete the file
    if(!\Kirby\Toolkit\f::remove($this->root())) {
      raise('The file could not be deleted', 'delete-failed');
    }

    return true;

  }

  /**
   * Returns a full link to this file
   * Perfect for debugging in connection with echo
   * 
   * @return string
   */
  public function __toString() {
    return '<a href="' . $this->url() . '">' . $this->url() . '</a>';  
  }

  /**
   * Returns a more readable dump array for the dump() helper
   * 
   * @return array
   */
  public function __toDump() {

    return array(
      'id'     => $this->id(),
      'url'    => $this->url(),
      'uri'    => $this->uri(),
      'type'   => $this->type(),
      'mime'   => $this->mime(),
      'size'   => $this->niceSize(),
      'page'   => $this->page()->diruri(), 
      'fields' => $this->meta() ? $this->meta()->fields() : array(),
    );

  }

}