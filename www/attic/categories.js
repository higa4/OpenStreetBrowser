var list_cache=[];
var categories={};
var importance=[ "global", "international", "national", "regional", "urban", "suburban", "local" ];
var category_tags_default=
  { "name": "", "descprition": "", "lang": "en", "sub_categories": "" };
var category_rule_tags_default=
  { "name": "", "match": "", "description": "", "icon": "", 
    "importance": "urban", "type": "polygon;point" };

function get_category(id) {
  return categories[id];
}

list_cache.clean_up=function() {
  while(this.length>10) {
    this.shift();
  }
}

list_cache.search_element=function(viewbox, category) {
  for(var i=0; i<this.length; i++) {
    if((this[i].viewbox==viewbox)&&
       (this[i].category==category))
      return this[i];
  }

  return null;
}

list_cache.search=function(viewbox, category) {
  var ret;

  if(ret=list_cache.search_element(viewbox, category))
    return ret.text;

  return null;
}

function category(id) {
  // show
  this.show=function(id) {
    if((this.id==id)&&(this.overlay))
      this.overlay.show();
  }

  // hide
  this.hide=function(id) {
    if((this.id==id)&&(this.overlay))
      this.overlay.hide();
  }

  // load_callback
  this.load_callback=function(data) {
    var xml=data.responseXML;
    var list=xml.firstChild;
    
    this.tags.readDOM(list);

    this.version=list.getAttribute("version");

    this.rules=[];
    var cur=list.firstChild;
    while(cur) {
      if(cur.nodeName=="rule") {
        var t=new tags();
	t.readDOM(cur);
	this.rules.push(new category_rule(this, cur.getAttribute("id"), t));
      }
      cur=cur.nextSibling;
    }

    this.loaded=true;

    // register category
    category_list[this.id]=[];
    lang_str["cat:"+this.id]=[ this.tags.get_lang("name") ];

    // register overlay
    if(!(this.overlay=get_overlay(this.id)))
      this.overlay=new overlay(this.id);
    this.overlay.register_category(this);
    this.overlay.set_version(this.version);

    // if open request a reload
    show_list();

    // register hooks
    register_hook("show_category", this.show.bind(this), this);
    register_hook("hide_category", this.hide.bind(this), this);
  }

  // load
  this.load=function(version) {
    var param={ todo: "load" };

    param.id=this.id;

    if(version)
      param.version=version;

    ajax_direct("categories.php", param, this.load_callback.bind(this));
  }

  // constructor
  this.rules=[];
  if(id) {
    this.id=id;
    this.loaded=false;
    this.tags=new tags();
    this.load();
  }
  else {
    this.id="list_"+uniqid();
    this.loaded=true;
    this.tags=new tags(category_tags_default);
  }

  categories[this.id]=this;

  this.count_list=function(viewbox) {
    var cur_cache;
    if(!(cur_cache=list_cache.search_element(viewbox, this.id)))
      return 0;
    return cur_cache.data.length;
  }

  this.is_complete=function(viewbox) {
    var cur_cache;
    if(!(cur_cache=list_cache.search_element(viewbox, this.id)))
      return 0;
    return cur_cache.complete;
  }

  this.push_list=function(dom, viewbox) {
    var ret="";
    var matches=dom.getElementsByTagName("match");
    var last_importance="";

    if(this.version!=dom.getAttribute("version")) {
      this.load(dom.getAttribute("version"));
    }

    var cur_cache;
    if(!(cur_cache=list_cache.search_element(viewbox, this.id))) {
      cur_cache={
	viewbox: viewbox,
	category: this.id,
	data: [],
	complete: false,
	complete_importance: {},
      };
      list_cache.push(cur_cache);
      list_cache.clean_up();
    }

    for(var mi=0; mi<matches.length; mi++) {
      var match=matches[mi];
      var rule=this.get_rule(match.getAttribute("rule_id"));
      if(rule) {
	var match_ob=rule.load_entry(match);

	cur_cache.data.push(match_ob);
	call_hooks("category_load_match", this, match_ob)

	var mimp=match_ob.tags.get("importance");
	if(mimp!=last_importance) {
	  for(var i=0; i<importance.length; i++) {
	    if(mimp==importance[i])
	      break;
	    cur_cache.complete_importance[importance[i]]=true;
	  }
	  last_importance=mimp;
	}
      }
    }

    if(dom.getAttribute("complete")=="true") {
      cur_cache.complete=true;
      for(var i=0; i<importance.length; i++) {
	cur_cache.complete_importance[importance[i]]=true;
      }
    }

    call_hooks("category_loaded_matches", this, viewbox)

    return cur_cache;
  }

  this.write_list=function(viewbox, offset, limit) {
    var ret="";
    if(!offset)
      offset=0;
    if(!limit)
      limit=10;

    var cur_cache;
    if(!(cur_cache=list_cache.search_element(viewbox, this.id))) {
      return null;
    }

    if(cur_cache.data.length==0) {
      ret+=t("nothing found")+"\n";
    }

    var max=offset+limit;
    if(max>cur_cache.data.length)
      max=cur_cache.data.length;

    for(var i=offset; i<max; i++) {
      var match_ob=cur_cache.data[i];
      call_hooks("category_show_match", this, match_ob);
      ret+=match_ob.list_entry();
    }

    call_hooks("category_show_finished", this);

    return ret;
  }

  // editor
  this.editor=function() {
    if(this.win) {
      alert("There's already a window!");
      return;
    }

    if(!this.loaded) {
      alert("Not loaded yet!");
      return;
    }

    this.win=new win("edit_list");

    this.form=document.createElement("div");
    this.win.win.appendChild(this.form);

    var div=document.createElement("div");
    this.tags.editor(div);
    this.form.appendChild(div);

    var sep=document.createElement("hr");
    this.form.appendChild(sep);

    this.div_rule_list=document.createElement("div");
    this.div_rule_list.className="editor_category_rule_list";
    this.form.appendChild(this.div_rule_list);

    for(i=0; i<this.rules.length; i++) {
      var div=document.createElement("div");
      this.div_rule_list.appendChild(div);
      div.className="editor_category_rule";

      this.rules[i].editor(div);
    }

    var input=document.createElement("input");
    input.type="button";
    input.value="New Rule";
    input.onclick=this.new_rule.bind(this);
    this.form.appendChild(input);

    var sep=document.createElement("br");
    this.form.appendChild(sep);

    var input=document.createElement("input");
    input.type="button";
    input.value="Save";
    input.onclick=this.save.bind(this);
    this.form.appendChild(input);

    var input=document.createElement("input");
    input.type="button";
    input.value="Cancel";
    input.onclick=this.cancel.bind(this);
    this.form.appendChild(input);
  }

  // get_rule
  this.get_rule=function(id) {
    for(var i=0; i<this.rules.length; i++) {
      if(this.rules[i].id==id)
	return this.rules[i];
    }

    return null;
  }

  // new_rule
  this.new_rule=function() {
    if(!this.loaded) {
      alert("Not loaded yet!");
      return;
    }

    var el=new category_rule(this);
    this.rules.push(el);

    var div=document.createElement("div");
    this.div_rule_list.appendChild(div);
    div.className="editor_category_rule";

    el.editor(div, true);
  }

  // remove_rule
  this.remove_rule=function(rule) {
    if(!rule) {
      alert("category::remove_rule: no rule supplied");
      return null;
    }

    for(var i=0; i<this.rules.length; i++) {
      if(this.rules[i]==rule) {
        array_remove(this.rules, i);
      }
    }

    if(rule.div)
      rule.div.parentNode.removeChild(rule.div);
  }

  // save
  this.save=function() {
    if(!this.loaded) {
      alert("Not loaded yet!");
      return;
    }

    var ret="";
   
    ret="<?xml version=\"1.0\" encoding=\"UTF-8\"?>\n";

    ret+="<category id=\""+this.id+"\" version=\""+this.version+"\">\n";
    this.tags.editor_update();
    ret+=this.tags.xml("  ");

    ret.list=[];
    for(var i=0; i<this.rules.length; i++) {
      ret+="  <rule id=\""+this.rules[i].id+"\">\n";
      this.rules[i].tags.editor_update();
      ret+=this.rules[i].tags.xml("    ");
      ret+="  </rule>\n";
    }

    ret+="</category>\n";

    ajax_post("categories.php", { todo: 'save', id: this.id }, ret, this.save_callback.bind(this));
  }

  // save_callback
  this.save_callback=function(data) {
    var result=data.responseXML;
    
    var stat=result.getElementsByTagName("status");
    var stat=stat[0];

    switch(stat.getAttribute("status")) {
      case "ok":
	alert("Saved.");
	this.win.close();
	this.win=null;
	break;
      case "merge failed":
        this.resolve_conflict(stat.getAttribute("branch"), stat.getAttribute("version"));
	break;
      case "error":
	alert(t("error")+t("error:"+stat.getAttribute("error")));
        break;
      default:
	alert("Result of save: status "+stat.getAttribute("status"));
    }
  }

  // cancel
  this.cancel=function() {
    this.win.close();
    this.win=null;
  }

  // destroy
  this.destroy=function() {
    // register category
    delete(category_list[this.id]);
    delete(categories[this.id]);
    show_list();

    // register overlay
    if(this.overlay)
      this.overlay.unregister_category(this);

    // unregister hooks
    unregister_object_hooks(this);
  }

}

function edit_list_new_rule(id) {
  categories[id].edit_list_new_rule();
}

function edit_list(id) {
  if(!id) {
    var l=new category();
    l.editor();
  }
  else {
    var l=categories[id];
    l.editor();
  }
}

function edit_list_cancel(id) {
  categories[id].cancel();
}

function load_category(id) {
  if(edit_list_win) {
    edit_list_win.close();
    edit_list_win=null;
  }

  new category(id);
}

function lists_list_callback(data) {
  var list=data.responseXML;
  var obs=list.getElementsByTagName("category");
  var ret="";

  ret+="<p>Choose a category:<ul>\n";
  for(var i=0; i<obs.length; i++) {
    var ob=obs[i];

    ret+="<li><a href='javascript:load_category(\""+ob.getAttribute("id")+"\")'>"+
      ob.textContent+"</a></li>\n";
  }
  ret+="</ul>\n";

  ret+="<p><a href='javascript:edit_list()'>New category</a><br>\n";
  ret+="<p><a href='javascript:window_close(\""+edit_list_win.id+"\")'>Cancel</a><br>\n";
  edit_list_win.content.innerHTML=ret;
}

function list_categories() {
  edit_list_win=new win("edit_list");
  edit_list_win.content.innerHTML="Loading ...";
  ajax_direct("categories.php", { todo: "list" }, lists_list_callback);
}

function categories_show_list(div) {
  var inputs=div.getElementsByTagName("input");

  for(var i=0; i<inputs.length; i++) {
    var l=document.createElement("span");
    l.className="list_tools";
    l.innerHTML="<a href='javascript:edit_list(\""+inputs[i].name+"\")'>edit</a>\n";
    l.innerHTML+="<a href='javascript:destroy_category(\""+inputs[i].name+"\")'>X</a>\n";
    inputs[i].parentNode.parentNode.insertBefore(l, inputs[i].parentNode.parentNode.firstChild);
  }

  div.innerHTML+="<a href='javascript:list_categories()'>"+t("more_categories")+"</a>\n";
}

function destroy_category(id) {
  get_category(id).destroy();
}

register_hook("show_list", categories_show_list);


