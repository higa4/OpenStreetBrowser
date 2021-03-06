<?
//include "../../render/config_queries.php";
function whats_here_find($param) {
  global $load_xml;
//  global $query;

  $dist_mul=(19-$param[zoom])*(19-$param[zoom]);
  $dist=3*$dist_mul;

  $poly="PolyFromText('POLYGON((".
    ($param[lon]-$dist)." ".($param[lat]-$dist).",".
    ($param[lon]-$dist)." ".($param[lat]+$dist).",".
    ($param[lon]+$dist)." ".($param[lat]+$dist).",".
    ($param[lon]+$dist)." ".($param[lat]-$dist).",".
    ($param[lon]-$dist)." ".($param[lat]-$dist)."))', 900913)";
  $distance="Distance(osm_way, GeometryFromText('POINT($param[lon] $param[lat])', 900913))";

  $qry="select *, astext(ST_Centroid(osm_way)) as \"#geo:center\" from (".
    "select *, $distance-\"#dist_modi\"*$dist_mul as \"#distance\" from (".
    "select osm_id, osm_tags, osm_way, 1 as \"#area\", 4 as \"#dist_modi\" from osm_point where osm_way&&$poly".
    " union all ".
    "select osm_id, osm_tags, osm_way, ST_Length(osm_way) as \"#area\", 1.5 as \"#dist_modi\" from osm_line where osm_way&&$poly".
    " union all ".
    "select osm_id, osm_tags, osm_way, ST_Area(osm_way) as \"#area\", 1 as \"#dist_modi\" from osm_polygon where osm_way&&$poly".
    " union all ".
    "select osm_id, osm_tags, osm_way, ST_Area(osm_way) as \"#area\", 1 as \"#dist_modi\" from osm_rel where osm_way&&$poly".
    ") x1 offset 0) x2 where \"#distance\"<$dist order by \"#distance\" asc, \"#area\" asc";

  $res=sql_query($qry);
  while($elem=pg_fetch_assoc($res)) {
    $osm_tags=parse_hstore($elem['osm_tags']);
    foreach($elem as $k=>$v) {
      if(substr($k, 0, 1)=="#")
        $osm_tags[$k]=$v;
    }

    $ret[]=array("id"=>$elem['osm_id'], "tags"=>new tags($osm_tags));
  }

  return $ret;
}

function ajax_whats_here_find($param, $xml) {
  $ret=whats_here_find($param);

  $result=$xml->createElement("result");
  $list=dom_create_append($result, "list", $xml);

  foreach($ret as $ob) {
    $match=dom_create_append($list, "match", $xml);
    foreach($ob as $k=>$v) {
      if($k=="tags") {
        $v=$v->export_dom($xml);
        foreach($v as $v1)
          $match->appendChild($v1);
      }
      else
        $match->setAttribute($k, $v);
    }
  }

  $xml->appendChild($result);
}



