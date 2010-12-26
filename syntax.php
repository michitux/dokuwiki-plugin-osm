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
    var $defaults = array
        (
            'width' => 450,
            'height' => 320,
            'lat' => -4.25,
            'lon' => 55.833,
            'zoom' => 8,
            'layer' => 'mapnik'
        );
    var $constraints = array
        (
            'width' => array('min' => 100, 'max' => 1000),
            'height' => array('min' => 99, 'max' => 1500),
            'zoom' => array('min' => 0, 'max' => 18),
            'lat' => array('min' => -90, 'max' => 90),
            'lon' => array('min' => -180, 'max' => 180),
        );

    var $urlMappings = array
        (
            'zoom' => 'z',
            'width' => 'w',
            'height' => 'h',
            'lon' => 'long'
        );


    var $layers = array('osmarender', 'mapnik');

    function getType() { return 'substition'; }
    function getPType() { return 'block'; }
    function getSort() { return 900; }

    function connectTo($mode) {
        $this->Lexer->addSpecialPattern('<osm ?[^>\n]*>.*?</osm>', $mode, 'plugin_osm');
    }

    function handle($match, $state, $pos, &$handler) {

        // break matched cdata into its components
        list($str_params, $str_markers) = explode('>', substr($match, 4, -6), 2);

        $param = array();
        preg_match_all('/(\w*)="(.*?)"/us', $str_params, $param, PREG_SET_ORDER);

        $opts = array();

        foreach($param as $kvpair) {
            list($match, $key, $val) = $kvpair;
            $key = strtolower($key);
            switch ($key) {
            case 'layer':
                if (in_array($val, $this->layers))
                    $opts['layer'] = $val;
                break;
            case 'lon':
            case 'lat':
                $val = (float) $val;
                if ($val >= $this->constraints[$key]['min'] && $val <= $this->constraints[$key]['max'])
                    $opts[$key] = $val;
                break;
            case 'width':
            case 'height':
            case 'zoom':
                $val = (int) $val;
                if ($val >= $this->constraints[$key]['min'] && $val <= $this->constraints[$key]['max'])
                    $opts[$key] = $val;
                break;
            }
        }

        $markers = $this->_extract_markers($str_markers);

        return array($opts, $markers);
    }

    function render($mode, &$renderer, $data) {
        if ($mode == 'xhtml') {
            list($options, $markers) = $data;

            $options = array_merge($this->defaults, $options);

            $json = new JSON();

            $renderer->doc .= '<div class="openstreetmap" style="width: '.$options['width'].'px; height: '.$options['height'].'px;">'.DOKU_LF;
            $renderer->doc .= "<a href=\"".$this->getLinkURL($options)."\" title=\"See this map on OpenStreetMap.org\">".DOKU_LF;
            $renderer->doc .= "<img alt=\"OpenStreetMap\" src=\"".$this->getImageURL($options)."\" />".DOKU_LF;
            $renderer->doc .= "</a>".DOKU_LF;
            $renderer->doc .= "<!-- ".$json->encode($markers)." -->";
            $renderer->doc .= "</div>".DOKU_LF;
            return true;
        }

        return false;
    }

    /**
     * extract markers for the osm from the wiki syntax data
     *
     * @param   string    $str_markers   multi-line string of lat,lon,txt triplets
     * @return  array                   array of markers as associative array
     */
    function _extract_markers($str_markers) {

        $point = array();
        preg_match_all('/^(.*?),(.*?),(.*)$/um', $str_markers, $point, PREG_SET_ORDER);

        $overlay = array();
        foreach ($point as $pt) {
            list($match, $lat, $lon, $txt) = $pt;

            $lat = is_numeric($lat) ? (float)$lat : 0;
            $lon = is_numeric($lon) ? (float)$lon : 0;
            $txt = addslashes(str_replace("\n", "", p_render("xhtml", p_get_instructions($txt), $info)));

            $overlay[] = compact('lat', 'lon', 'txt');
        }

        return $overlay;
    }

    /**
     * Generates the image URL for the map using the given mapserver.
     *
     * @param array $options The options to send
     * @return string The URL to the static image of this map.
     */
    function getImageURL($options) {
        // create the query string
        $query_params = array('format=jpeg');
        foreach ($options as $option => $value) {
            $query_params[] = (array_key_exists($option, $this->urlMappings) ? $this->urlMappings[$option] : $option).'='.$value;
        }

        return ml(hsc($this->getConf('map_service_url'))."?".implode('&', $query_params), array('cache' => 'recache'));
    }

    /**
     * Generates the link url for this map.
     *
     * @param array $options The options that shall be included in the url
     * @return string The link url.
     */
    function getLinkURL($options) {
        $link_query = array();

        foreach ($options as $option => $value) {
            $link_query[] = "$option=$value";
        }
        return 'http://www.openstreetmap.org/?'.implode('&amp;', $link_query);
    }
}


// vim:ts=4:sw=4:et:
