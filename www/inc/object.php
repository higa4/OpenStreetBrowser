<?
// global vars
$objects=array();
$object_types=array();
$output_xml=array();
$object_elements=array("node"=>"node", "rel"=>"relation", "way"=>"way", "coll"=>"coll");
$object_element_ids=array("node"=>1, "way"=>2, "rel"=>3, "coll"=>4);
$object_element_shorts=array("n"=>"node", "r"=>"rel", "w"=>"way", "c"=>"coll");
$object_element_data_type=array("N"=>"node", "R"=>"rel", "W"=>"way");
$tag_preloaded=array();
$priority_chapter=array(
  "general_info"=>-5,
  "wikipedia"=>-1,
  "routing"=>1,
  "internal"=>5
);


function tag_preload($elem) {
  global $tag_preloaded;

  $tag_preloaded[$elem['element']][$elem['id']][$elem['k']]=$elem['v'];
}

class object {
  public $id;
  public $data;
  public $tags;
  public $place;
  public $element;
  public $only_id;
  public $place_type;
  public $place_type_id;

  function __construct($data) {
    global $object_element_ids;
    $this->data=$data;
    $this->tags=$data['tags'];
    $this->element=$data['element'];
    if($data['subid'])
      $this->id="{$data['element']}_{$data['id']}_{$data['subid']}";
    else
      $this->id="{$data['element']}_{$data['id']}";
    $this->only_id=$data['id'];
    if($data['subid']) {
      $this->place_type="gen_node";
      $this->place_type_id="1";
    }
    else {
      $this->place_type=$data['element'];
      $this->place_type_id=$object_element_ids[$data['element']];
    }
  }

  function long_name($lang="", $name_def=0) {
    if(!$name_def)
      $name_def="[ref] - [name];[name];[ref];[operator]";

    return $this->tags->parse($name_def, $lang);
  }

  function list_description($list) {
    $ret=array();
    call_hooks("list_description", &$ret, $this, $list);

    return $ret;
  }

  function info($param) {
    global $object_elements;
    global $priority_chapter;

    if(!$this->read_data())
      return;

    $param["info_noshow"]=explode(",", $param["info_noshow"]);

    $ret="<div class='object'>\n";
    $name_lang=$this->tags->get_lang("name");
    $name     =$this->tags->get("name");

    if($name_lang!=$name)
      $ret.="<h1>$name_lang ($name)</h1>\n";
    else
      $ret.="<h1>$name_lang</h1>\n";

    $ret.="<div class='obj_actions'>\n";
    $ret.="<a class='zoom' href='#' onClick='redraw()'>".lang("info_back")."</a>\n";
    $ret.="<a class='zoom' href='javascript:zoom_to_feature()'>".lang("info_zoom")."</a>\n";
    $ret.="</div>\n";

    $data=array();

    call_hooks("info", &$data, $this, $param);

    $chapter=array();

    foreach($data as $d) {
      $chapter[$d[0]].=$d[1];
    }

    foreach($chapter as $title=>$content) {
      $chapter_list[$priority_chapter[$title]][]=$title;
    }

    ksort($chapter_list);

    $ret.="<form id='info' action='javascript:info_change()'>\n";
    foreach($chapter_list as $title_list) {
      foreach($title_list as $title) {
	$content=$chapter[$title];
	if($content) {
//	  $ret.="<div class=\"object_chapter\" id=\"object_$title\">\n";
	  if(in_array($title, $param['info_noshow']))
	    $ret.=infobox_closed($title);
	  else
	    $ret.=infobox($title, $content);
//	  $ret.="<h2>".lang("head_$title")."</h2>\n";
//	  $ret.=$content;
//	  $ret.="</div>\n";
	}
      }
    }
    $ret.="</form>\n";

    $ret.="</div>\n";
    return $ret;
  }

  function read_data() {
    // load missing data
    return 1;
  }

  function place() {
    if($this->place)
      return $this->place;

    $x="place_{$this->place_type}";
    $this->place=new $x($this->data);

    return $this->place;
  }

  function get_xml($parent, $root, $inc_children=0, $bounds=0) {
    global $output_xml;
    global $object_elements;
    if(in_array($this->id, $output_xml))
      return;

    $this->read_data();

    $obj=$root->createElement($object_elements[$this->data['element']]);
    $parent->appendChild($obj);
    if($this->data['subid'])
      $obj->setAttribute("id", $this->data['id']."_".$this->data['subid']);
    else
      $obj->setAttribute("id", $this->data['id']);
    $output_xml[]=$this->id;

    $this->place()->get_xml($obj, $root, $inc_children, $bounds);
    $this->tags->get_xml($obj, $root);

  }

  function export_dom($document) {
    $match=$document->createElement("match");
    $match->setAttribute("id", $this->id);

    $tags=$this->tags->export_dom($document);
    for($i=0; $i<sizeof($tags); $i++) {
      $match->appendChild($tags[$i]);
    }

    return $match;
  }

  function export_array() {
    $ret=array();

    $ret['osm_id']=$this->id;
    $ret['osm_tags']=$this->tags->export_array();

    return $ret;
  }

  /**
   * returns list of members, e.g. array("way_123"=>"stop", ...)
   * @return array key: member_id, value: member_role
   */
  function members() {
    global $object_element_data_type;
    $ret=null;

    if(isset($this->members))
      return $this->members;

    switch($this->element) {
      case "rel":
	 $id=substr($this->id, 4);
	 $ret=array();

         $res=sql_query("select * from relation_members where relation_id='$id'");
	 while($elem=pg_fetch_assoc($res)) {
	   $elem_id=$object_element_data_type[$elem['member_type']]."_".
	            $elem['member_id'];
           $ret[$elem_id]=$elem['member_role'];
	 }
    }

    $this->members=$ret;

    return $ret;
  }

  /**
   * returns list of relations this object is member of, e.g.
   * array("rel_123"=>"stop", ...)
   * @return array key: relation_id, value: member_role
   */
  function member_of() {
    global $object_element_data_type;
    $ret=null;

    if(isset($this->member_of))
      return $this->member_of;

    $id=explode("_", $this->id);
    $data_type=array_search($id[0], $object_element_data_type);
    $ret=array();

    $res=sql_query("select * from relation_members where member_id='$id[1]' and member_type='$data_type'");
    while($elem=pg_fetch_assoc($res)) {
      $elem_id="rel_".$elem['relation_id'];
      $ret[$elem_id]=$elem['member_role'];
    }

    $this->member_of=$ret;

    return $ret;
  }
}

// elem can by either a string or an array. if id is a string it has to be
// the identifier for a object. see below for details.
//
// if elem is provided as array it should contain the following fields. If
// any of those are omitted, they will be loaded as soon as they are being
// accessed:
// element                    ("node", "way", "rel", "coll")
// id                         integer
//                              element and id can also be combined as element_id
//                              in the value of id
// lon, lat (for nodes)       float
// nodes (for ways)           array(node_id, node_id, ...) 
//                              e.g. array(node_1, 35, ...)
// members (for rels)         array(node_id=>role, way_id=>role, ...
//                              e.g.: array(node_1=>, n35=>stop, ...)

// tags (if not provided, it will be loaded by db) hash-array of tags

// id should have the following form:
// node|way|rel|coll_id ... e.g. node_35, rel_123
// short forms:
// [nwrc]id ... e.g. n35, rel135
function load_object($elem=0, $tags=null) {
  global $objects;
  global $object_types;
  global $tag_preloaded;
  global $object_element_shorts;
  $object_elements=array("n"=>"node", "w"=>"way", "r"=>"rel", "c"=>"coll");

  if(is_string($elem)) {
    $id=$elem;
    unset($elem);
  }
  else {
    if(isset($elem['element']))
      $id="{$elem['element']}_{$elem['id']}";
    elseif(isset($elem['osm_type']))
      $id="{$elem['osm_type']}_{$elem['osm_id']}";
    elseif(isset($elem['osm_id']))
      $id="{$elem['osm_id']}";
    else
      $id=$elem['id'];

    if(isset($elem['element'])) switch($elem['element']) {
      case "node":
        if((!$elem['lon'])||(!$elem['lat'])||(!$elem['way']))
	  unset($elem);
	break;
      case "way":
        if((!$elem['nodes'])||(!$elem['way']))
	  unset($elem);
	break;
      case "rel":
        if((!$elem['member_ids'])||(!$elem['member_roles']))
	  unset($elem);
	break;
      case "coll":
        if((!$elem['member_ids'])||(!$elem['member_roles']))
	  unset($elem);
	break;
    }
  }

  if(ereg("^([nwrc])([0-9]*)$", $id, $m)) {
    $id=$object_element_shorts[$m[1]]."_".$m[2];
  }

  // if we've already loaded this object, we just return it
  if(isset($objects[$id]))
    return $objects[$id];

  // let's see if we have a valid object
  if(ereg("^(node|way|rel|coll)_([0-9]+)(_.*)?$", $id, $m)) {
    $object_place_type=$m[1];
    $object_id=$m[2];
    $object_subid=substr($m[3], 1);
  }
  // if it's not a single id, we can receive data from tags if we supplied them
  elseif($tags) {
  }
  else {
    // id can't be identified
    return null;
  }

  // load data if necessary
  $add_tags=array();
  if(!isset($elem)) {
    $qry="select astext(way) as \"way\", astext(way) as \"#geo\", astext(ST_Centroid(way)) as \"#geo:center\" from (select load_geo('$id') as way) x";
/*    switch($object_place_type) {
      case "node":
        $qry="select astext(osm_way) as way from osm_nodes where osm_id='$id'";
	break;
      case "way":
        $qry="select nodes, astext(CASE WHEN l.way is not null THEN l.way WHEN p.way is not null THEN p.way WHEN r.way is not null THEN r.way END) as way from planet_osm_ways left join planet_osm_line l on id=l.osm_id left join planet_osm_polygon p on id=p.osm_id left join relation_members r1 on r1.member_id=id and r1.member_role='outer' left join planet_osm_polygon r on r.osm_id=-r1.relation_id where id='$object_id'";
	break;
      case "rel":
	$qry="select (select to_textarray((CASE WHEN member_type='N' THEN 'n' WHEN member_type='W' THEN 'w' WHEN member_type='R' THEN 'r' ELSE 'c' END) || member_id) from relation_members where relation_id=id) as member_ids, (select to_textarray(member_role) from relation_members where relation_id=id) as member_roles from planet_osm_rels where id='$object_id'";
	break;
      case "coll":
	$qry="select (select to_textarray((CASE WHEN member_type='N' THEN 'n' WHEN member_type='W' THEN 'w' WHEN member_type='R' THEN 'r' ELSE 'c' END) || member_id) from coll_members where coll_id=id) as member_ids, (select to_textarray(member_role) from coll_members where coll_id=id) as member_roles from planet_osm_colls where id='$object_id'";
	break;
    } */

    $res=sql_query($qry);
    $elem=pg_fetch_assoc($res);

    foreach($elem as $k=>$v) {
      if(substr($k, 0, 1)=="#")
        $add_tags[$k]=$v;
    }
  }

  if(!$elem)
    return null;

  // load tags
  if($tags)
    // already loaded
    $elem['tags']=new tags($tags);
  elseif($tags=$tag_preloaded[$elem['element']][$elem['id']]) {
    unset($tag_preloaded[$elem['element']][$elem['id']]);
    $elem['tags']=new tags($tags);
  }
  else {
    switch($object_place_type) {
      case "node":
        $qry="select k, v from node_tags where node_id='$object_id'";
	break;
      case "way":
        $qry="select k, v from way_tags where way_id='$object_id'";
	break;
      case "rel":
        $qry="select k, v from relation_tags where relation_id='$object_id'";
	break;
      case "coll":
        $qry="select k, v from coll_tags where coll_id='$object_id'";
	break;
    }

    $rest=sql_query($qry);
    $tags=array();
    while($elemt=pg_fetch_assoc($rest)) {
      $tags[$elemt['k']]=$elemt['v'];
    }

    $tags=array_merge($tags, $add_tags);

    $elem['tags']=new tags($tags);
  }

  // now that we have tags we can look what kind of object we are going to load
  $class="object";
  foreach($object_types as $key=>$values) {
    $obj_val=$elem['tags']->get($key);
    foreach($values as $type=>$c) {
      if($obj_val==$type)
	$class=$c;
    }
  }

  $elem['element']=$object_place_type;
  $elem['id']=$object_id;
  $elem['subid']=$object_subid;
  $o=new $class($elem);
  $objects[$id]=$o;

  return $o;
}

function register_object_type($key, $type, $class) {
  global $object_types;

  $object_types[$key][$type]=$class;
}

// using load_objects is faster then loading each object by its own
function load_objects($list) {
  global $object_element_shorts;
  $list_by_type=array();
  $ret=array();

  if(!$list)
    return $ret;

  foreach($list as $id) {
    if(is_array($id)) {
      $list_by_type[$id['element']][]=$id['id'];
    }
    else if(ereg("^(node|way|rel|coll)_([0-9]+)$", $id, $m)) {
      $list_by_type[$m[1]][]=$m[2];
    }
    else if(ereg("^([nwrc])([0-9]+)$", $id, $m)) {
      $list_by_type[$object_element_shorts[$m[1]]][]=$m[2];
    }
  }

  if($list_by_type['node']) {
    $qry="select 'node' as element, node_id as id, k, v from node_tags where node_id in (".implode(",", $list_by_type['node']).")";
    $res=sql_query($qry);
    while($elem=pg_fetch_assoc($res)) {
      tag_preload($elem);
    }

    $qry="select 'node' as element, id, lon, lat, astext(way) as way from planet_osm_nodes left join planet_osm_point on id=osm_id where id in (".implode(",", $list_by_type['node']).")";
    $res=sql_query($qry);
    while($elem=pg_fetch_assoc($res)) {
      $ret[]=load_object($elem);
    }
  }

  if($list_by_type['way']) {
    $qry="select 'way' as element, way_id as id, k, v from way_tags where way_id in (".implode(",", $list_by_type['way']).")";
    $res=sql_query($qry);
    while($elem=pg_fetch_assoc($res)) {
      tag_preload($elem);
    }

    $qry="select 'way' as element, id, nodes, astext(CASE WHEN l.way is not null THEN l.way WHEN p.way is not null THEN p.way WHEN r.way is not null THEN r.way END) as way from planet_osm_ways left join planet_osm_line l on id=l.osm_id left join planet_osm_polygon p on id=p.osm_id left join relation_members r1 on r1.member_id=id and r1.member_role='outer' left join planet_osm_polygon r on r.osm_id=-r1.relation_id where id in (".implode(",", $list_by_type['way']).")";
    $res=sql_query($qry);
    while($elem=pg_fetch_assoc($res)) {
      $ret[]=load_object($elem);
    }
  }

  if($list_by_type['rel']) {
    $qry="select 'rel' as element, relation_id as id, k, v from relation_tags where relation_id in (".implode(",", $list_by_type['rel']).")";
    $res=sql_query($qry);
    while($elem=pg_fetch_assoc($res)) {
      tag_preload($elem);
    }

    $qry="select 'rel' as element, id, (select to_textarray((CASE WHEN member_type='N' THEN 'n' WHEN member_type='W' THEN 'w' WHEN member_type='R' THEN 'r' ELSE 'c' END) || member_id) from relation_members where relation_id=id) as member_ids, (select to_textarray(member_role) from relation_members where relation_id=id) as member_roles from planet_osm_rels where id in (".implode(",", $list_by_type['rel']).")";
    $res=sql_query($qry);
    while($elem=pg_fetch_assoc($res)) {
      $ret[]=load_object($elem);
    }
  }

  if($list_by_type['coll']) {
    $qry="select 'coll' as element, coll_id as id, k, v from coll_tags where coll_id in (".implode(",", $list_by_type['coll']).")";
    $res=sql_query($qry);
    while($elem=pg_fetch_assoc($res)) {
      tag_preload($elem);
    }

    $qry="select 'coll' as element, id, (select to_textarray((CASE WHEN member_type='N' THEN 'n' WHEN member_type='W' THEN 'w' WHEN member_type='R' THEN 'r' ELSE 'c' END) || member_id) from coll_members where coll_id=id) as member_ids, (select to_textarray(member_role) from coll_members where coll_id=id) as member_roles from planet_osm_colls where id in (".implode(",", $list_by_type['coll']).")";
    $res=sql_query($qry);
    while($elem=pg_fetch_assoc($res)) {
      $ret[]=load_object($elem);
    }
  }

  return $ret;
}

function ajax_object_load_more_tags($param) {
  $ob=load_object($param['id']);
  $tags=explode(",", $param['tags']);
  $ret=array();

  foreach($tags as $t) {
    if($v=$ob->tags->get($t)) {
      $ret[$t]=$v;
    }
  }

  return $ret;
}

function ajax_load_object($param, $xml) {
  $ob=load_object($param['ob']);

  $result=dom_create_append($xml, "result", $xml);
  $node=$ob->export_dom($xml);

  if($node)
    $result->appendChild($node);
}
