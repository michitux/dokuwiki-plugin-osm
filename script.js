addInitEvent(function () {
  this.init_osm = function (div) {
    var params = this.getParams(div);
    var points = this.readPoints(div);
    while (div.firstChild) {
      div.removeChild(div.firstChild);
    }
    var map = new OpenLayers.Map (div.id, {
        controls:[
          new OpenLayers.Control.Navigation(),
          new OpenLayers.Control.PanZoomBar(),
          new OpenLayers.Control.LayerSwitcher(),
          new OpenLayers.Control.Attribution()],
        maxExtent: new OpenLayers.Bounds(-20037508.34,-20037508.34,20037508.34,20037508.34),
        maxResolution: 156543.0399,
        numZoomLevels: 19,
        units: 'm',
        projection: new OpenLayers.Projection("EPSG:900913"),
        displayProjection: new OpenLayers.Projection("EPSG:4326")
    } );

    var layers = new Object();
    layers['osmarender'] = new OpenLayers.Layer.OSM.Osmarender("Osmarender");
    layers['maplint'] = new OpenLayers.Layer.OSM.Maplint("Maplint");
    layers['mapnik'] = new OpenLayers.Layer.OSM.Mapnik("Mapnik");

    if (!layers[params['layer']]) {
      params['layer'] = 'mapnik';
    }
    map.addLayer(layers[params['layer']]);

    for (var layer in layers) {
      if (layer != params['layer']) {
        map.addLayer(layers[layer]);
      }
    }

    var layerMarkers = new OpenLayers.Layer.Markers("Markers");
    map.addLayer(layerMarkers);

    var lonLat = new OpenLayers.LonLat(params['lon'], params['lat']).transform(new OpenLayers.Projection("EPSG:4326"), map.getProjectionObject());

    map.setCenter(lonLat, params['zoom']);

    if (isArray(points)) {
      var pLonLat;
      var size = new OpenLayers.Size(21,25);
      var offset = new OpenLayers.Pixel(-(size.w/2), -size.h);
      var icon;
      for(var p = 0; p < points.length; p++) {
        var point = points[p];
        var pLonLat = new OpenLayers.LonLat(point.lon, point.lat).transform(new OpenLayers.Projection("EPSG:4326"), map.getProjectionObject());
        icon = new OpenLayers.Icon('http://www.openstreetmap.org/openlayers/img/marker.png',size,offset);
        layerMarkers.addMarker(new OpenLayers.Marker(pLonLat,icon));
      }
    }
  };
  this.getParams = function (div) {
    var url = div.getElementsByTagName('a')[0].href;
    url = url.split('?')[1];
    var params = url.split('&');
    var param;
    for (var j = 0; j < params.length; j++) {
      param = params[j].split('=');
      delete params[j];
      if (param[0] != 'layer') {
        param[1] = parseFloat(param[1]);
      }
      params[param[0]] = param[1];
    }
    return params;
  };
  this.readPoints = function (div) {
    var comment = div.firstChild;
    while (comment.nodeType != 8) {
      comment = comment.nextSibling;
    }
    return eval(comment.data);
  };
  var osm_divs = getElementsByClass('openstreetmap', document, 'div');
  for (var i=0; i < osm_divs.length; i++) {
    var osm_div = osm_divs[i];
    osm_div.id = 'osm_'+i;
    this.init_osm(osm_div);
  }
});
