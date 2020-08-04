<?php
/**
 * Keywords Plugin
 *
 * Specifies keywords list for the page
 *
 * @license GPL 2 (http://www.gnu.org/licenses/gpl.html)
 * @author  Ilya Lebedev <ilya@lebedev.net>
 */

// must be run within Dokuwiki
if(!defined('DOKU_INC')) die();

if(!defined('DOKU_PLUGIN')) define('DOKU_PLUGIN',DOKU_INC.'lib/plugins/');
require_once(DOKU_PLUGIN.'syntax.php');

/**
 * All DokuWiki plugins to extend the parser/rendering mechanism
 * need to inherit from this class
 */
class syntax_plugin_keywords_keywords extends DokuWiki_Syntax_Plugin {

  function getType(){ return 'substition'; }
  function getPType(){ return 'block'; }
  function getSort(){ return 110; }

  /**
   * Connect pattern to lexer
   */
  function connectTo($mode){
      if ($mode == 'base'){
          $this->Lexer->addSpecialPattern('{{keywords>.+?}}',$mode,'plugin_keywords_keywords');
      }
  }
  /**
   * Handle the match
   */
  function handle($match, $state, $pos, Doku_Handler $handler){
      return explode(" ",preg_replace("/{{keywords>(.*?)}}/","\\1",$match));
  }  
 
  /**
   *  Render output
   */
  function render($mode, Doku_Renderer $renderer, $data) {
      switch ($mode) {
          case 'metadata' :
              /*
              *  mark metadata with found value
              */
              $renderer->meta['keywords'] = ",".join(",",$data);
              return true;
              break;
    }
    return false;
  }
}
