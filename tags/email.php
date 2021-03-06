<?php

namespace Kirby\CMS\Kirbytext\Tag;

use Kirby\CMS\Kirbytext\Tag;
use Kirby\Toolkit\HTML;

// direct access protection
if(!defined('KIRBY')) die('Direct access is not allowed');

/**
 * Renders an email tag 
 * ie. (email: bastian@getkirby.com)
 * 
 * @package   Kirby CMS
 * @author    Bastian Allgeier <bastian@getkirby.com>
 * @link      http://getkirby.com
 * @copyright Bastian Allgeier
 * @license   http://getkirby.com/license
 */
class Email extends Tag {

  // a list of allowed attributes for this tag
  protected $attr = array(
    'class', 
    'title', 
    'rel'
  );

  /**
   * Returns the generated html for this tag
   * 
   * @return string
   */
  public function html() {
 
    return html::email($this->value(), html($this->attr('text')), array(
      'class' => $this->attr('class'),
      'title' => $this->attr('title'),      
      'rel'   => $this->attr('rel'),      
    ));
 
  }

}