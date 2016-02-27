<?php
/**
 * OpenStreetMap Action Plugin:   Register OpenLayers-Javascript
 * 
 * @author     Michael Hamann <michael@content-space.de>
 */

if(!defined('DOKU_INC')) die();
if(!defined('DOKU_PLUGIN')) define('DOKU_PLUGIN',DOKU_INC.'lib/plugins/');
require_once(DOKU_PLUGIN.'action.php');

class action_plugin_osm extends DokuWiki_Action_Plugin {
  /**
   * Register its handlers with the dokuwiki's event controller
   */
  function register(Doku_Event_Handler $controller) {
    $controller->register_hook('TPL_METAHEADER_OUTPUT', 'BEFORE',  $this, '_hookjs');
  }

  /**
   * Hook js script into page headers.
   */
  function _hookjs(&$event, $param) {
    $event->data["script"][] = array ("type" => "text/javascript",
      "charset" => "utf-8",
      "_data" => "",
      "src" => 'http://openlayers.org/api/OpenLayers.js'
    );
    $event->data["script"][] = array ("type" => "text/javascript",
      "charset" => "utf-8",
      "_data" => "",
      "src" => ml('http://openstreetmap.org/openlayers/OpenStreetMap.js', array('cache' => 'recache'), true, '&')
    );
  }
}
