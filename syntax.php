<?php
/**
 * Plugin OpenStreetMap: Allow Display of a OpenStreetMap in a wiki page.
 * 
 * @license    GPL 2 (http://www.gnu.org/licenses/gpl.html)
 * @author     Michael Hamann <michael@content-space.de>
 * @author     Christopher Smith <chris@jalakai.co.uk>
 */

if(!defined('DOKU_INC')) define('DOKU_INC', realpath(dirname(__FILE__).'/../../').'/');
if(!defined('DOKU_PLUGIN')) define('DOKU_PLUGIN', DOKU_INC.'lib/plugins/');
require_once(DOKU_PLUGIN.'syntax.php');

/**
 * All DokuWiki plugins to extend the parser/rendering mechanism
 * need to inherit from this class
 */
class syntax_plugin_osm extends DokuWiki_Syntax_Plugin {
  function getInfo(){
    return array(
      'author' => 'Michael Hamann, Christopher Smith',
      'email'  => 'michael@content-space.de',
      'date'   => '2008-11-10',
      'name'   => 'OpenStreetMap Plugin',
      'desc'   => 'Add maps to your wiki
      Syntax: <osm params>markers</osm>',
      'url'    => 'http://www.dokuwiki.org/plugin:osm',
    );
  }

  function getType() { return 'substition'; }
  function getPType() { return 'block'; }
  function getSort() { return 900; } 

  function connectTo($mode) { 
    $this->Lexer->addSpecialPattern('<osm ?[^>\n]*>.*?</osm>', $mode, 'plugin_osm'); 
  }

  function handle($match, $state, $pos, &$handler) {

    // break matched cdata into its components
    list($str_params, $str_markers) = explode('>', substr($match, 4, -6), 2);

    $map = new OSM_Map();

    $param = array();
    preg_match_all('/(\w*)="(.*?)"/us', $str_params, $param, PREG_SET_ORDER);

    foreach($param as $kvpair) {
      list($match, $key, $val) = $kvpair;
      $key = strtolower($key);
      $setter = 'set'.ucfirst($key);
      if (method_exists($map, $setter)) $map->$setter(strtolower($val));        
    }

    $map->setMarkers($this->_extract_markers($str_markers));

    return array($map);
  }

  function render($mode, &$renderer, $data) {
    if ($mode == 'xhtml') {
      list($map) = $data;

      $renderer->doc .= '<div class="openstreetmap" style="'.$map->getSizeCSS().'">'.DOKU_LF;
      $renderer->doc .= "<a href=\"".$map->getLinkURL()."\" title=\"See this map on OpenStreetMap.org\">".DOKU_LF;
      $renderer->doc .= "<img alt=\"OpenStreetMap\" src=\"".$map->getImageURL($this->getConf('map_service_url'))."\" />".DOKU_LF;
      $renderer->doc .= "</a>".DOKU_LF;
      $renderer->doc .= "<!-- ".$map->toJSON()." -->";
      $renderer->doc .= "</div>".DOKU_LF;

    }

    return false;
  } 

  /**
   * extract markers for the osm from the wiki syntax data
   *
   * @param   string    $str_markers   multi-line string of lat,lon,text triplets
   * @return  array                   array of OSM_Markers
   */
  function _extract_markers($str_markers) {

    $point = array();
    preg_match_all('/^(.*?),(.*?),(.*)$/um', $str_markers, $point, PREG_SET_ORDER);

    $overlay = array();
    foreach ($point as $pt) {
      list($match, $lat, $lon, $text) = $pt;

      $lat = is_numeric($lat) ? $lat : 0;
      $lon = is_numeric($lon) ? $lon : 0;
      $text = addslashes(str_replace("\n", "", p_render("xhtml", p_get_instructions($text), $info)));

      $overlay[] = new OSM_Marker($lat, $lon, $text);
    }

    return $overlay;
  }

}

/**
 * An OpenStreetMap.
 */
class OSM_Map {
  var $options = array('width', 'height', 'lat', 'lon', 'zoom', 'layer');
  var $mappings = array(
    'zoom' => 'z',
    'width' => 'w',
    'height' => 'h',
    'lon' => 'long'
  );
  var $layers = array('osmarender', 'mapnik');
  var $width = 450;
  var $height = 320;
  var $lat  = -4.25;
  var $lon = 55.833;
  var $zoom = 8;
  var $layer = 'mapnik';
  var $markers = array();

  /**
   * Set the width of the map.
   *
   * @param string|int $width The width.
   */
  function setWidth($width) {
    $width = (int) $width;
    if ($width < 1000 && $width > 100) {
      $this->width = $width;
    }
  }

  /**
   * Set the height of the map.
   *
   * @param string|int $height The height.
   */
  function setHeight($height) {
    $height = (int) $height;
    if ($height < 1500 && $height > 99) {
      $this->height = $height;
    }
  }

  /**
   * Set the zoom level of the map.
   *
   * @param string|int $zoom The zoom level.
   */
  function setZoom($zoom) {
    $zoom = (int) $zoom;
    if ($zoom <= 18 && $zoom >= 0) {
      $this->zoom = $zoom;
    }
  }

  /**
   * Set the latitude of the center of the map.
   *
   * @param string|float $lat The latitude to set.
   */
  function setLat($lat) {
    $lat = (float) $lat;
    if ($lat <= 90 && $lat >= -90) {
      $this->lat = $lat;
    }
  }

  /**
   * Set the longitude of the center of the map.
   *
   * @param string|float $lon The longitude to set.
   */
  function setLon($lon) {
    $lon = (float) $lon;
    if ($lon <= 180 && $lon >= -180) {
      $this->lon = $lon;
    }
  }

  /**
   * Set the default layer of the map.
   *
   * @param string The default layer.
   */
  function setLayer($layer) {
    if (in_array($layer, $this->layers)) {
      $this->layer = $layer;
    }
  }

  /**
   * Generates the image URL for the map using the given mapserver.
   *
   * @param string $mapServiceURL The mapping service url.
   * @return string The URL to the static image of this map.
   */
  function getImageURL($mapServiceURL) {
    // create the query string
    $query_params = array('format=jpeg');
    foreach ($this->options as $option) {
      $query_params[] = (array_key_exists($option, $this->mappings) ? $this->mappings[$option] : $option).'='.$this->$option;
    }

    return ml(hsc($mapServiceURL)."?".implode('&', $query_params), array('cache' => 'recache'));
  }

  /**
   * Generates the link url for this map.
   *
   * @return string The link url.
   */
  function getLinkURL() {
    $link_query = array();

    foreach ($this->options as $option) {
      $link_query[] = "$option={$this->$option}";
    }
    return 'http://www.openstreetmap.org/?'.implode('&amp;', $link_query);
  }

  /**
   * Generate CSS code for the size of the image.
   *
   * @return string The css for the size of the image.
   */
  function getSizeCSS() {
    // determine width and height (inline styles) for the map image
    return 'width: '.$this->width.'px; height: '.$this->height.'px;';
  }

  /**
   * Sets the markers.
   *
   * @param markers The new markers.
   */
  function setMarkers($markers) {
    $this->markers = $markers;
  }

  /**
   * Generates a JSON representation of the map.
   *
   * @return string The JSON representation of the map.
   */
  function toJSON() {
    $json = '';
    foreach ($this->markers as $marker) {
      $json .= ',';
      $json .= $marker->toJSON();
    }

    return '['.substr($json, 1).']';
  }
}

/**
 * Represents a marker on a map with a description text.
 */
class OSM_Marker {
  var $lat;
  var $lon;
  var $text;

  /**
   * A marker on the OSM.
   *
   * @param int $lat The latitude.
   * @param int $lon The longitude.
   * @param string $text The text of the bubble.
   */
  function OSM_Marker($lat, $lon, $text) {
    $this->lat = $lat;
    $this->lon = $lon;
    $this->text = $text;
  }

  /**
   * Generate a JSON representation of the marker.
   *
   * @return string The JSON representation of the marker.
   */
  function toJSON() {
    return "{lat:$this->lat,lon:$this->lon,txt:'$this->text'}";
  }
}
