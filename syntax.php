<?php
/**
 * Plugin Google Maps: Allow Display of a OpenStreetMap in a wiki page.
 * 
 * @license    GPL 2 (http://www.gnu.org/licenses/gpl.html)
 * @author     Michael Hamann <michael@content-space.de>
 * @author     Christopher Smith <chris@jalakai.co.uk>
 */

if(!defined('DOKU_INC')) define('DOKU_INC',realpath(dirname(__FILE__).'/../../').'/');
if(!defined('DOKU_PLUGIN')) define('DOKU_PLUGIN',DOKU_INC.'lib/plugins/');
require_once(DOKU_PLUGIN.'syntax.php');

/**
 * All DokuWiki plugins to extend the parser/rendering mechanism
 * need to inherit from this class
 */
class syntax_plugin_osm extends DokuWiki_Syntax_Plugin {

  var $options = array(
    'width' => '450',
    'height' => '320',
    'lat'  => -4.25,
    'lon' => 55.833,
    'zoom' => 8,
    'format' => 'jpeg',
    'layer' => 'mapnik',
  );

  var $mappings = array(
    'zoom' => 'z',
    'width' => 'w',
    'height' => 'h',
    'lon' => 'long'
  );

  function getInfo(){
    return array(
      'author' => 'Michael Hamann, Christopher Smith',
      'email'  => 'michael@content-space.de',
      'date'   => '2008-11-10',
      'name'   => 'OpenStreetMap Plugin',
      'desc'   => 'Add maps to your wiki
      Syntax: <osm params>overlaypoints</osm>',
      'url'    => 'http://www.dokuwiki.org/plugin:osm',
    );
  }

  function getType() { return 'substition'; }
  function getPType() { return 'block'; }
  function getSort() { return 900; } 

  function connectTo($mode) { 
    $this->Lexer->addSpecialPattern('<osm ?[^>\n]*>.*?</osm>',$mode,'plugin_osm'); 
  }

  function handle($match, $state, $pos, &$handler){

    // break matched cdata into its components
    list($str_params,$str_points) = explode('>',substr($match,4,-6),2);

    $options = $this->_extract_params($str_params);
    $overlay = $this->_extract_points($str_points);

    // determine width and height (inline styles) for the map image
    if ($options['width'] || $options['height']) {
      $style = $options['width'] ? 'width: '.$options['width']."px;" : "";
      $style .= $options['height'] ? 'height: '.$options['height']."px;" : "";
      $style = "style='$style'";
    } else {
      $style = '';
    }      

    // create the query string
    $query_params = array();
    foreach ($options as $key => $val) {
      $val = is_numeric($val) ? $val : hsc($val);
      $query_params[$key] = $val;
      $query_params[] = (array_key_exists($key, $this->mappings) ? $this->mappings[$key] : $key).'='.$val;
    }

    $query_string = implode('&', $query_params);

    // create a javascript serialisation of the point data
    $points = '';
    if (!empty($overlay)) {
      foreach ($overlay as $data) {
        list($lat,$lon,$text) = $data;
        $points .= ",{lat:$lat,lon:$lon,txt:'$text'}";
      }
      $points = "[ ".substr($points,1)." ]";
    }

    return array($style, $options, $query_string, $points);
  }

  function render($mode, &$renderer, $data) {
    if ($mode == 'xhtml') {
      list($style, $options, $query_string, $points) = $data;

      $renderer->doc .= '<div class="openstreetmap" '.$style.'>'.DOKU_LF;
      $link_query = array();
      foreach ($options as $key=>$val) {
        if ($key != 'format')
          $link_query[] = "$key=$val";
      }
      $renderer->doc .= "<a href=\"http://www.openstreetmap.org/?".implode('&amp;',$link_query)."\" title=\"See this map on OpenStreetMap.org\">".DOKU_LF;
      $renderer->doc .= "<img alt=\"OpenStreetMap\" src=\"".ml(hsc($this->getConf('map_service_url'))."?".$query_string, array('cache' => 'recache'))."\" />".DOKU_LF;
      $renderer->doc .= "</a>".DOKU_LF;
      $renderer->doc .= "<!-- ".$points." -->";
      $renderer->doc .= "</div>".DOKU_LF;

      }

      return false;
    } 

    /**
     * extract parameters for the openstreetmap from the parameter string
     *
     * @param   string    $str_params   string of key="value" pairs
     * @return  array                   associative array of parameters key=>value
     */
    function _extract_params($str_params) {

      $param = array();
      preg_match_all('/(\w*)="(.*?)"/us',$str_params,$param,PREG_SET_ORDER);

      // parse match for instructions, break into key value pairs      
      $options = $this->options;
      foreach($param as $kvpair) {
        list($match,$key,$val) = $kvpair;
        $key = strtolower($key);
        if (isset($options[$key])) $options[$key] = strtolower($val);        
      }

      if ($options['zoom']) {
        $options['zoom'] = (int)$options['zoom'];
        if ($options['zoom'] > 18 || $options['zoom'] < 0) {
          $options['zoom'] = $this->options['zoom'];
        }
      }

      if ($options['width']) {
        $options['width'] = (int)$options['width'];
        if ($options['width'] > 1000 || $options['width'] < 100) {
          $options['width'] = $this->options['width'];
        }
      }

      if ($options['height']) {
        $options['height'] = (int)$options['height'];
        if ($options['height'] > 1000 || $options['height'] < 100) {
          $options['height'] = $this->options['height'];
        }
      }

      if ($options['lat']) {
        $options['lat'] = (float)$options['lat'];
        if ($options['lat'] > 90 || $options['lat'] < -90) {
          $options['lat'] = $this->options['lat'];
        }
      }

      if ($options['lon']) {
        $options['lon'] = (float)$options['lon'];
        if ($options['lon'] > 180 || $options['lon'] < -180) {
          $options['lon'] = $this->options['lon'];
        }
      }

      return $options;
    }

    /**
     * extract overlay points for the googlemap from the wiki syntax data
     *
     * @param   string    $str_points   multi-line string of lat,lon,text triplets
     * @return  array                   multi-dimensional array of lat,lon,text triplets
     */
    function _extract_points($str_points) {

      $point = array();
      preg_match_all('/^(.*?),(.*?),(.*)$/um',$str_points,$point,PREG_SET_ORDER);

      $overlay = array();
      foreach ($point as $pt) {
        list($match,$lat,$lon,$text) = $pt;

        $lat = is_numeric($lat) ? $lat : 0;
        $lon = is_numeric($lon) ? $lon : 0;
        $text = addslashes(str_replace("\n","",p_render("xhtml",p_get_instructions($text),$info)));

        $overlay[] = array($lat,$lon,$text);
      }

      return $overlay;
    }

}


