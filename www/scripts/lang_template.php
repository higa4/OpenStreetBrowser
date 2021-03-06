<?
Header("Content-type: text/plain; charset=utf-8");
require "../../conf.php";
require "../inc/sql.php";
require "../inc/functions.php";
require "../inc/tags.php";
$ui_lang=$_GET['ui_lang'];
$deprecated=array();

function lang() {
}

function esc($d) {
  if(is_array($d)) {
    return "array( \"".implode("\", \"", $d)."\" )";
  }
  else {
    return "\"$d\"";
  }
}

function template_lang_file($src, $dst) {
  global $count_done;
  global $count_missing;
  global $root_path;
  global $deprecated;

  $lang_str=array();
  if(file_exists("$root_path/$src"))
    include "$root_path/$src";
  $lang_str_src=$lang_str;
  if(!$lang_str_src)
    $lang_str_src=array();

  $lang_str=array();
  if(file_exists("$root_path/$dst"))
    include "$root_path/$dst";
  $lang_str_dst=$lang_str;
  if(!$lang_str_dst)
    $lang_str_dst=array();

  if((!sizeof($lang_str_src))&&(!sizeof($lang_str_dst)))
    return;

  print "==== File: $dst ====\n";
  print "<syntaxhighlight lang=\"php\">\n";

  if(file_exists("$root_path/$src")) {
    $f=fopen("$root_path/$src", "r");
    while($r=fgets($f)) {
      if(eregi("^( *)\\\$lang_str\[['\"]([^\"]*)['\"]\]", $r, $m)) {
	$found=false;
	if($l=$lang_str_dst[$m[2]]) {
	  print "$m[1]\$lang_str[\"$m[2]\"]=";
	  print esc($l);
	  print ";\n";

	  unset($lang_str_dst[$m[2]]);

	  $count_done++;
	  $found=true;
	}
	if(!$found) foreach($deprecated as $file=>$file_deprecated) {
	  if($l=$file_deprecated[$m[2]]) {
	    print "$m[1]\$lang_str[\"$m[2]\"]=";
	    print esc($l);
	    print ";\n";

	    unset($deprecated[$file][$m[2]]);

	    $count_done++;
	    $found=true;
	  }
	}
	if(!$found){
	  print "#$m[1]\$lang_str[\"$m[2]\"]=";
	  print esc($lang_str_src[$m[2]]);
	  print ";\n";

	  $count_missing++;
	}
      }
      else {
	print $r;
      }
    }
    fclose($f);
  }
  
  print "</syntaxhighlight>\n";
  print "\n";

  if(sizeof($lang_str_dst)) {
    $deprecated["File: $dst"]=$lang_str_dst;
  }
}

function print_deprecated() {
  global $deprecated;

  if((sizeof($deprecated)==0)||
     (array_sum(array_map("sizeof", $deprecated))==0))
    return;

  print "==== Deprecated strings ====\n";
  print "// The following \$lang_str were not defined and might be deprecated or wrong:\n";

  foreach($deprecated as $file=>$lang_str_dst) {
    if(sizeof($lang_str_dst)) {
      print "===== $file =====\n";
      foreach($lang_str_dst as $k=>$v) {
	print "\$lang_str[\"$k\"]=".esc($v).";\n";
      }
    }
  }

  print "\n";
}

function print_category_entry_value($value) {
  $v=split_semicolon($value);

  if(sizeof($v)<=1)
    return "\"$value\"";
  if(in_array($v[0], array("M", "F", "N")))
    return "array($v[0], \"".implode("\", \"", array_splice($v, 1))."\")";
  else
    return "array(\"".implode("\", \"", $v)."\")";
}

function print_category_entry($str, $tags, $cat_lang, $comment, $tag) {
  global $ui_lang;
  global $count_done;
  global $count_missing;

  if($cat_lang==$ui_lang) {
    print "\$lang_str[\"$str\"]=".print_category_entry_value($tags[$tag]).";";
    if($tags["$tag"])
      $count_done++;
    else
      $count_missing++;
  }
  elseif($tags["$tag:$ui_lang"]) {
    print "\$lang_str[\"$str\"]=".print_category_entry_value($tags["$tag:$ui_lang"]).";";
    $count_done++;
  }
  else {
    print "#\$lang_str[\"$str\"]=".print_category_entry_value($tags[$tag]).";";
    $count_missing++;
  }

  if($comment)
    print " // {$comment}";
 
  print "\n";
}

function template_lang_category($category, $version) {
  global $db_central;
  global $ui_lang;
  @include "../lang/{$ui_lang}_deprecated.php";

  print "==== Category: $category ====\n";
  print "Version: $version\n";
  print "<syntaxhighlight lang=\"php\">\n";
  print "// Please use the form =array([Gender,] \"Singular\", \"Plural\") where appropriate (see top of page for explanation)\n";

  $res=sql_query("select * from category where category_id='$category' and version='$version'", $db_central);
  $elem=pg_fetch_assoc($res);
  $tags=parse_hstore($elem['tags']);
  $lang=$tags['lang'];
  if(!$lang)
    $lang="en";

  // check if deprecated $lang_str exists
  if(!$tags["name:$ui_lang"]) {
    if($x=$lang_str["cat:".strtr($category, array("_"=>"/"))]) {
      if(is_array($x))
	$x=$x[0];
      $tags["name:$ui_lang"]=$x;
    }
    if($x=$lang_str["list_".strtr($category, array("_"=>"_"))]) {
      if(is_array($x))
	$x=$x[0];
      $tags["name:$ui_lang"]=$x;
    }
//    elseif($x=$lang_str["station_type_".strtr($tags["match"], array("="=>"_"))]) {
//      $tags["name:$ui_lang"]=$x;
//    }
  }
  // end deprecated stuff

  print_category_entry("$category:name", $tags, $lang, "Original Name ($lang): {$tags['name']}", "name");

  if($tags['description']) {
    print_category_entry("$category:description", $tags, $lang, "Default description: \"{$tags['description']}\"", "description");
  }

  $res_rule=sql_query("select * from category_rule where category_id='$category' and version='$version'", $db_central);
  while($elem_rule=pg_fetch_assoc($res_rule)) {
    $tags=parse_hstore($elem_rule['tags']);

    // check if deprecated $lang_str exists
    if(!$tags["name:$ui_lang"]) {
      if($x=$lang_str["list_".strtr($tags["match"], array("="=>"_"))]) {
	if(is_array($x))
	  $x=$x[0];
	$tags["name:$ui_lang"]=$x;
      }
      elseif($x=$lang_str["station_type_".strtr($tags["match"], array("="=>"_"))]) {
	if(is_array($x))
	  $x=$x[0];
	$tags["name:$ui_lang"]=$x;
      }
    }
    // end deprecated stuff

    print_category_entry("$category:{$elem_rule['rule_id']}:name", $tags, $lang, "Match: {$tags['match']}", "name");

    if($tags['description']) {
      print_category_entry("$category:{$elem_rule['rule_id']}:description", $tags, $lang, "Default description: \"{$tags['description']}\"", "description");
    }
  }

  print "</syntaxhighlight>\n";
  print "\n";
}

$count_done=0;
$count_missing=0;

print "Current OSB version: ";
system("git rev-parse HEAD");
print "\n";

template_lang_file("www/lang/en.php", "www/lang/{$ui_lang}.php");
template_lang_file("www/lang/tags_en.php", "www/lang/tags_{$ui_lang}.php");
$d=opendir("$root_path/www/plugins");
while($f=readdir($d))
  template_lang_file("www/plugins/$f/lang_en.php", "www/plugins/$f/lang_{$ui_lang}.php");
closedir($d);

$res=sql_query("select * from category_current", $db_central);
while($elem=pg_fetch_assoc($res)) {
  template_lang_category($elem['category_id'], $elem['version']);
}

print_deprecated();

print "==== Statistics ====\n";
print "* Done: $count_done\n";
print "* Missing: $count_missing\n";
print "* Goal: ".sprintf("%.0f", ($count_done/($count_done+$count_missing)*100))."%\n";

print "\n[[Category:OpenStreetBrowser]]\n";
