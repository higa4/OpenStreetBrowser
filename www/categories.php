<?
$design_hidden=true;
include "../conf.php";
include "inc/hooks.php";
include "inc/lock.php";
include "inc/tags.php";
include "inc/sql.php";
include "inc/debug.php";
include "inc/category.php";
include "inc/categories.php";
include "inc/process_category.php";
include "inc/functions.php";
include "inc/css.php";
include "inc/plugins.php";
include "../src/wiki_stuff.php";
include "inc/user.php";
include "inc/git_obj.php";
plugins_init();
sql_query("SET enable_seqscan='off'");

user_check_auth();

$output=fopen("/tmp/git.log", "a");
function ob_receive($text) {
  global $output;

  fwrite($output, $text);
}

ob_start(ob_receive);

$data_lang="en";
if($_GET[lang])
  $data_lang=$_GET[lang];

$id=$_GET[id];
switch($_GET[todo]) {
  case "save":
    if(!$current_user->authenticated) {
      Header("Content-Type: text/xml; charset=UTF-8");
      ob_end_clean();

      print "<?xml version='1.0' encoding='UTF-8' ?".">\n";
      print "<result>\n";
      print "  <status status='error' error='not_logged_in' />\n";
      print "</result>\n";
      return;
    }

    $status=category_save($id, file_get_contents("php://input"), $_GET);

    Header("Content-Type: text/xml; charset=UTF-8");
    ob_end_clean();

    print "<?xml version='1.0' encoding='UTF-8' ?".">\n";
    print "<result>\n";

    if($status[status]!==true) {
      print "  <status ";
      foreach($status as $ek=>$ev) {
	print " $ek='$ev'";
      }
      print " />\n";
    }
    else {
      print "  <status version='$status[version]' status='ok' />\n";
    }
    print "  <id>$status[id]</id>\n";
    print "</result>\n";

    break;
  case "list":
    $list=category_list($lang);

    Header("Content-Type: text/xml; charset=UTF-8");
    ob_end_clean();

    print "<?xml version='1.0' encoding='UTF-8' ?".">\n";
    print "<result>\n";
    foreach($list as $k=>$v) {
      if(!$v->get("hide")) { // put better filters here
	print "  <category id='$k'>".strtr($v->get_lang("name"), $make_valid)."</category>\n";
      }
    }
    print "</result>\n";

    break;
  case "load":
    $content=category_load($id, $_GET);

    Header("Content-Type: text/xml; charset=UTF-8");
    ob_end_clean();

    print $content;

    break;
  case "delete":
    $status=category_delete($id);

    Header("Content-Type: text/xml; charset=UTF-8");
    ob_end_clean();

    print "<?xml version='1.0' encoding='UTF-8' ?".">\n";
    print "<result>\n";

    if($status[status]!==true) {
      print "  <status ";
      foreach($status as $ek=>$ev) {
	print " $ek='$ev'";
      }
      print " />\n";
    }
    else {
      print "  <status version='$status[version]' status='ok' />\n";
    }
    print "  <id>$status[id]</id>\n";
    print "</result>\n";

    break;
  case "restore":
    $status=category_restore($id, $_GET);

    Header("Content-Type: text/xml; charset=UTF-8");
    ob_end_clean();

    print "<?xml version='1.0' encoding='UTF-8' ?".">\n";
    print "<result>\n";

    if($status[status]!==true) {
      print "  <status ";
      foreach($status as $ek=>$ev) {
	print " $ek='$ev'";
      }
      print " />\n";
    }
    else {
      print "  <status version='$status[version]' status='ok' />\n";
    }
    print "  <id>$status[id]</id>\n";
    print "</result>\n";

    break;
  default:
    ob_end_clean();
    print "No valid 'todo'\n";
}
