<?
class tags {
  private $data;
  public $compiled_tags=array();

  function __construct($parse_text_k=null, $parse_text_v=null) {
    if(is_array($parse_text_k)) {
      $this->data=$parse_text_k;
    }
    elseif($parse_text_k==null) {
      $this->data=array();
    }
    else {
      $ks=parse_array($parse_text_k);
      $vs=parse_array($parse_text_v);

      $this->data=array();
      for($i=0; $i<sizeof($ks);$i++) {
	$this->data[$ks[$i]]=$vs[$i];
      }
    }
  }

  function get($k) {
    if(isset($this->data[$k]))
      return $this->data[$k];

    return null;
  }

  function get_multi($k) {
    if(isset($this->data[$k]))
      return split_semicolon($this->data[$k]);

    return array();
  }

  function get_lang($k, $l=null) {
    global $data_lang;
    if($l===null)
      $l=$data_lang;

    if(isset($this->data["$k:$l"]))
      return $this->data["$k:$l"];

    if(isset($this->data[$k]))
      return $this->data[$k];

    return null;
  }

  function get_lang_multi($k, $l=null) {
    global $data_lang;
    if($l===null)
      $l=$data_lang;

    if(isset($this->data["$k:$l"]))
      return split_semicolon($this->data["$k:$l"]);

    if(isset($this->data[$k]))
      return split_semicolon($this->data[$k]);

    return array();
  }

  function get_available_languages($key) {
    $list=array();

    foreach($this->data as $k=>$v) {
      if(ereg("^$key:([^:]*)$", $k, $m)) {
	$list[$m[1]]=$v;
      }
    }

    return $list;
  }

  function set($k, $v) {
    $this->data[$k]=$v;
  }

  function erase($k) {
    unset($this->data[$k]);
  }

  function data() {
    return $this->data;
  }

  function set_data($_data) {
    $this->data=$_data;
  }

  function get_xml($obj, $root) {
    foreach($this->data as $key=>$value) {
      $subnode=$root->createElement("tag");
      $subnode->setAttribute("k", $key);
      $subnode->setAttribute("v", $value);
      $obj->appendChild($subnode);
    }
  }

  function compile_text($text) {
    $match=0;

    while(ereg("^(.*)(%[^%]+%)(.*)$", $text, $m)) {
      $tag=substr($m[2], 1, -1);
      if($rep=$this->get_lang($tag))
	$match++;
      $text=$m[1].$rep.$m[3];
      $this->compiled_tags[]=$tag;
    }

    if(!$match)
      return;

    while(ereg("^(.*)(#[^#]+#)(.*)$", $text, $m)) {
      $text=$m[1].lang(substr($m[2], 1, -1)).$m[3];
    }

    return $text;
  }

  function readDOM($dom) {
    $cur=$dom->firstChild;

    while($cur) {
      if($cur->nodeName=="tag") {
	$this->set($cur->getAttribute("k"), $cur->getAttribute("v"));
      }
      $cur=$cur->nextSibling;
    }
  }

  function writeDOM($parent, $dom) {
    foreach($this->data as $key=>$value) {
      $tag=$dom->createElement("tag");
      $tag->setAttribute("k", $key);
      $tag->setAttribute("v", $value);
      $parent->appendChild($tag);
    }
  }

  // match_desc:
  // ( "or" => arr(arr("is", "key", "value")))

  // valid operators:
  // or  ... any of the following elements is true
  // and ... all of the following elements is true
  // not ... inverse element [1]
  // is  ... tag with key [1] is one of the following elements
  // is not ... tag with key [1] is none of the following elements
  // exist  ... there's a tag with key [1]
  // exist not    ... there's no tag with key [1]
  // >, <, >=, <= ... key [1] matches value [2]
  function match($match_desc) {
    switch($match_desc[0]) {
      case "or":
	for($i=1; $i<sizeof($match_desc); $i++)
	  if($this->match($match_desc[$i]))
	    return true;
        return false;
      case "and":
	for($i=1; $i<sizeof($match_desc); $i++)
	  if(!$this->match($match_desc[$i]))
	    return false;
	return true;
      case "~fuzzy is":
      case "~is":
	$values=$this->get_multi($match_desc[1]);
	for($i=2; $i<sizeof($match_desc); $i++)
	  for($j=0; $j<sizeof($values); $j++)
	    if($values[$j]==$match_desc[$i])
	      return true;
        return false;
      case "fuzzy is":
      case "is":
	$value=$this->get($match_desc[1]);
	for($i=2; $i<sizeof($match_desc); $i++)
	  if($value==$match_desc[$i])
	    return true;
        return false;
      case "~is not":
	$values=$this->get_multi($match_desc[1]);
	for($i=2; $i<sizeof($match_desc); $i++)
	  for($j=0; $j<sizeof($values); $j++)
	    if($values[$j]!=$match_desc[$i])
	      return false;
        return true;
      case "is not":
	$value=$this->get($match_desc[1]);
	for($i=2; $i<sizeof($match_desc); $i++)
	  if($value!=$match_desc[$i])
	    return false;
        return true;
      case "~exist":
      case "exist":
	if($this->get($match_desc[1]))
	  return true;
        return false;
      case "~exist not":
      case "exist not":
	if($this->get($match_desc[1]))
	  return false;
        return true;
      case "~>":
      case "~<":
      case "~>=":
      case "~<=":
	$values=$this->get_multi($match_desc[1]);
	for($j=0; $j<sizeof($values); $j++) {

	  $comp1=parse_number($values[$j]);
	  $comp2=parse_number($match_desc[2]);

	  switch(substr($match_desc[0], 1)) {
	    case ">":
	      if($comp1>$comp2)
		return true;
	      break;
	    case "<":
	      if($comp1<$comp2)
		return true;
	      break;
	    case ">=":
	      if($comp1>=$comp2)
		return true;
	      break;
	    case "<=":
	      if($comp1<=$comp2)
		return true;
	      break;
	  }
	}
	return false;
      case ">":
      case "<":
      case ">=":
      case "<=":
	$value=$this->get($match_desc[1]);

	$comp1=parse_number($value);
	$comp2=parse_number($match_desc[2]);

	switch($match_desc[0]) {
	  case ">":
	    if($comp1>$comp2)
	      return true;
	    break;
	  case "<":
	    if($comp1<$comp2)
	      return true;
	    break;
	  case ">=":
	    if($comp1>=$comp2)
	      return true;
	    break;
	  case "<=":
	    if($comp1<=$comp2)
	      return true;
	    break;
	}

	return false;
      case "not":
        return !$this->match($match_desc[1]);
      case "true":
	return true;
      case "false":
	return false;
      default:
        print "Invalid match desc '$match_desc[0]'\n";
    }

    return false;
  }

  function parse($str, $lang="") {
    global $data_lang;
    if($l===null)
      $l=$data_lang;

    $str=split_semicolon($str);
    foreach($str as $def) {
      $match_all=true;
      $ret="";
      while($def!="") {
	if(preg_match("/^\[([A-Za-z0-9_:]+)\]/", $def, $m)) {
          if(!($value=$this->get("$m[1]:$lang")))
	    if(!($value=$this->get("$m[1]")))
	      $match_all=false;

	  $def=substr($def, strlen($m[0]));
	  $ret.=$value;
	}
	else {
	  $ret.=substr($def, 0, 1);
	  $def=substr($def, 1);
	}
      }

      if($match_all)
	return $ret;
    }

    return null;
  }

  function write_xml($indent="") {
    $ret="";

    foreach($this->data as $key=>$value) {
      $ret.="$indent<tag k=\"$key\" v=\"$value\" />\n";
    }

    return $ret;
  }

  function export_dom($document) {
    $ret=array();

    foreach($this->data as $key=>$value) {
      $tag=$document->createElement("tag");
      $tag->setAttribute("k", $key);
      $tag->setAttribute("v", $value);

      $ret[]=$tag;
    }

    return $ret;
  }

  function export_array() {
    return $this->data;
  }
}

function parse_array($text, $prefix="") {
  if((substr($text, 0, 1)!="{")||(substr($text, -1)!="}"))
    return array();

  $parts=explode(",", substr($text, 1, -1));
  $ret=array();

  $i=0;
  do {
    if(substr($parts[$i], 0, 1)=="\"") {
      if(substr($parts[$i], -1)=="\"")
	$t1=substr($parts[$i], 1, -1);
      else {
	$t1=substr($parts[$i], 1);
	do {
	  $i++;
	  $t1.=",".$parts[$i];
	} while(substr($parts[$i], -1)!="\"");
	$t1=substr($t1, 0, -1);
      }

      $ret[]=stripslashes("$prefix$t1");
    }
    else {
      if($parts[$i]=="NULL")
        $parts[$i]="";
      $ret[]=stripslashes("$prefix$parts[$i]");
    }
    $i++;
  }
  while($i<sizeof($parts));

  return $ret;
}

function parse_tags($text) {
  $arr=parse_array($text);
  $ret=array();

  for($i=0; $i<sizeof($arr); $i+=2) {
    $ret[$arr[$i]]=$arr[$i+1];
  }
  
  return $ret;
}

function parse_tags_old($text) {
  $text=stripslashes($text);
  $tags=array();
  $mode=0;
  $tag_key="";
  $tag_value="";
  for($i=0; $i<strlen($text)-1; $i++) {
    $c=substr($text, $i, 1);
    if($mode==0) {
      if($c=="{") 
	$mode=1;
    }
    elseif($mode==1) {
      if($c==",")
	$mode=2;
      else
	$tag_key.=$c;
    }
    elseif($mode==2) {
      if(($c=="\"")&&($tag_value==""))
	$mode=3;
      elseif($c==",") {
        $tags[$tag_key]=$tag_value;
	$tag_key="";
	$tag_value="";
	$mode=1;
      }
      else
	$tag_value.=$c;
    }
    elseif($mode==3) {
      if($c=="\"")
	$mode=4;
      else
	$tag_value.=$c;
    }
    elseif($mode==4) {
      $mode=1;
      $tags[$tag_key]=$tag_value;
      $tag_key="";
      $tag_value="";
    }
  }

  function readDOM($dom) {
    $cur=$dom.firstChild;

    while($cur) {
      if($cur->nodeName=="tag") {
	$this->set($cur.getAttribute("k"), $cur.getAttribute("v"));
      }
      $cur=$cur.nextSibling;
    }
  }

  if($tag_key)
    $tags[$tag_key]=$tag_value;

  return $tags;
}

function match_simplify($match, $method=0) {
  switch($match[0]) {
    case "or":
      for($i=1; $i<sizeof($match); $i++) {
	// or aufloesen
	$match[$i]=match_simplify($match[$i], $method);
	if($match[$i][0]=="or") {
	  array_shift($match[$i]);
	  $match=array_merge($match, $match[$i]);
	  unset($match[$i]);
	  $match=array_values($match);
	  $i--;
	}
      }

      for($i=1; $i<sizeof($match); $i++) {
	for($j=1; $j<$i; $j++) {
	  if(($match[$i][0]=="is")&&($match[$j][0]=="is")&&
	     ($match[$i][1]==$match[$j][1])) {
	    unset($match[$j][0]);
	    unset($match[$j][1]);
	    $match[$i]=array_merge($match[$i], $match[$j]);
	    unset($match[$j]);
	    $match=array_values($match);
	    $j--; $i--;
	  }

	  elseif(($match[$i][0]=="~is")&&($match[$j][0]=="~is")&&
	     ($match[$i][1]==$match[$j][1])) {
	    unset($match[$j][0]);
	    unset($match[$j][1]);
	    $match[$i]=array_merge($match[$i], $match[$j]);
	    unset($match[$j]);
	    $match=array_values($match);
	    $j--; $i--;
	  }

	  elseif(($match[$i][0]=="and")&&($match[$j][0]=="and")) {
	    $eq=array(); $not_eq=array();
	    for($ii=1; $ii<sizeof($match[$i]); $ii++) {
	      $found_eq=false;
	      for($ji=1; $ji<sizeof($match[$j]); $ji++) {
		if(!sizeof(array_diff($match[$i][$ii], $match[$j][$ji]))) {
		  $found_eq=true;
		  $eq[]=$match[$i][$ii];
		  unset($match[$j][$ji]);
		  $match[$j]=array_values($match[$j]);
		}
		elseif(((($match[$i][$ii][0]==">")&&($match[$j][$ji][0]=="<="))||
		        (($match[$i][$ii][0]==">=")&&($match[$j][$ji][0]=="<"))||
		        (($match[$i][$ii][0]==">=")&&($match[$j][$ji][0]=="<=")))&&
		       ($match[$i][$ii][1]==$match[$j][$ji][1])&&
		       ($match[$i][$ii][2]<=$match[$j][$ji][2])) {
		  $found_eq=true;
		  unset($match[$j][$ji]);
		  $match[$j]=array_values($match[$j]);
		}
		elseif((($match[$i][$ii][0]=="exist")&&
		        (in_array($match[$j][$ji][0], array(">", ">=", "<=", "<", "is"))))&&
		       ($match[$i][$ii][1]<=$match[$j][$ji][1])) {
		  $found_eq=true;
		  $eq[]=$match[$i][$ii];
		  unset($match[$j][$ji]);
		  $match[$j]=array_values($match[$j]);
		}
	      }

	      if(!$found_eq) {
		$not_eq[]=$match[$i][$ii];
	      }
	    }

	    if(sizeof($eq)) {
	      if((!sizeof($not_eq))||(!sizeof($match[$j]))) {
		$match[$i]=$eq;
		unset($match[$j]);
		$match=array_values($match);
		$i--;
	      }
	      else {
                $not_eq=array(array_merge(array("or"), $not_eq, array($match[$j])));
		$match[$i]=array_merge(array("and"), $eq, $not_eq);
		$il=sizeof($match[$i])-1;
		$match[$i][$il]=match_simplify($match[$i][$il], $method);
		unset($match[$j]);
		$match=array_values($match);
		$i--;
	      }
	    }
	  }
	}
      }

      if(($match[0]=="or")&&(sizeof($match)==2)) {
	$match=$match[1];
      }

      break;
    case "and":
      for($i=1; $i<sizeof($match); $i++) {
	// or aufloesen
	$match[$i]=match_simplify($match[$i], $method);
	if($match[$i][0]=="and") {
	  array_shift($match[$i]);
	  $match=array_merge($match, $match[$i]);
	  unset($match[$i]);
	  $match=array_values($match);
	  $i--;
	}
      }

      if(($match[0]=="and")&&(sizeof($match)==2)) {
	$match=$match[1];
      }

      break;
  }

  return $match;
}


