var help_toolbox;

function help_init() {
  help_toolbox=new toolbox({
    icon: "plugins/help/icon.png",
    icon_title: "help",
    weight: -1,
  });
  register_toolbox(help_toolbox);

  var text = "<i>Help</i><br/><br/><div>"+t("list_info")+"</div><br/>";
  help_toolbox.content.innerHTML=text;
}

register_hook("init", help_init);
