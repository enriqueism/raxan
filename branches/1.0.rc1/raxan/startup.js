/**
 * Raxan (Rich Ajax Application) Startup script - 1.0 beta 1
 * Copyright (c) 2008-2009 Raymond Irving (http://raxanpdi.com)
 *
 * Dual licensed under the MIT and LGPL licenses.
 * See the LICENSE.txt file
 *
 */



html = raxan = Raxan = {    // Raxan html class object
    version: '1.0',         //@todo: update version number
    revision: '1.0.0.b3',
    path:'',
    pluginpath:'',
    csspath:'',
    expando:'',
    arraycol: {},
    inc: {},
    rich: {}, //rich plugin namespace
    isReady: false, isLoad: false,


    // initialize system
    init: function() {
        var i,js,st,m,src,code;
        var pth,tag,tags = document.getElementsByTagName('SCRIPT');
        this.cnt = 0; // set counter
        // get library path
        tag = tags[tags.length-1];
        src = tag.src + '';
        this.msie = navigator.userAgent.indexOf('MSIE')>=0;
        if (tag && (st=src.indexOf('startup.js'))>=0) {
            // setup paths
            pth = this.path = src.substr(0,st);
            this.csspath = pth + 'styles/';
            this.pluginpath = pth + 'plugins/';
            code = this.msie ? tag.text : tag.innerHTML;
            if (code && code.indexOf("\n")<0) { // check if script block is multi-line
                m = (new RegExp("\/(.*)\/")).exec(code);
                if (m) { // setup main js file
                    if (m[1] && m[1].substr(m[1].length-2)=='/-') {    // support for path/-
                        js = this.filename().split(/\./);
                        js[js.length-1] = 'js';
                        js = m[1].replace(/\/-/,'/') + js.join('.');
                    }
                    else if (m[1] && m[1]!='-') {   //support for multiple comma saparated files
                        js = m[1].split(/,/);
                    }
                    else if(m[0] == '/-/'){
                        js = this.filename().split(/\./);
                        js[js.length-1] = 'js';
                        js = js.join('.');
                    }
                    this.mainScript = typeof js != 'string' ? js[0] : js;
                }
            }
        }
        if (js) this.include(js,true);
        else if (src && code) eval(code);
        // invoke pre init functions
        if (self.RaxanPreInit)
            for(i in RaxanPreInit)
                if (typeof RaxanPreInit[i]=='function') RaxanPreInit[i]();
    },

    // initialize even handling after main scrips have been loaded
    initEvents: function() {
        if (this.pcbk) return this;
        this.pcbk = true;
        if (document.all) this.insertScript(this.path+'callback.js','text/javascript');
        else this.insertScript('Raxan.callback()','text/javascript',true);
        return this;
    },

    // handle event callback
    callback: function(id){
        if (id) { // handle include callbacks
            this.inc[this.inc[id]]();
            return;
        }
        var me = this;
        var load = function(e){ var i,l,a=me.collection('load'); l=a.length; for(i=0; i<l; i++) a[i](e); delete a; me.isLoad = true};
        var unload = function(e){ var i,l,a=me.collection('unload'); l=a.length; for(i=0; i<l; i++) a[i](e); delete a };
        var ready = function(e){
            var i,l,a = me.collection('ready');
            l=a.length; for(i=0; i<l; i++) a[i](e); delete a
            a = me.collection('binds');
            l=a.length; for(i=0; i<l; i++) me.handleEvent(a[i][0],a[i][1],a[i][2]); delete a;
            me.isReady = true;
        }
        this.handlePageEvents(ready,load,unload);
    },

    // handle event binding
    handleEvent: function(css,evt,fn) {
        if (window.jQuery) jQuery(css).bind(evt,fn);
    },
    // handle page events ready, load, unload
    handlePageEvents: function(rdy,ld,uld) {
        var j,w = window;
        if (w.jQuery) {
            j=w.jQuery; j(rdy); j(w).load(ld).unload(uld);
        } else {
            function e(n,f){
                if (w.addEventListener) w.addEventListener( n, f, false );
                else if (w.attachEvent) w.attachEvent( 'on'+n, f );
            }
            e('load',rdy); e('load',ld); e('unload',uld); e = null;
        }
    },

    // returns url parameters
    urlparams: function(){
        var a,o,n,nv;
        if (this._urlparams) return this._urlparams;
        else {
            a = (location+'').split(/\?/);
            o = {_url:a[0],_query:a[1]};
            nv =  a[1] ? a[1].split(/\&/) : null;
            if (nv) for(n in nv){
                a = nv[n].split(/\=/);
                o[a[0]]= a[1] ? unescape(a[1]) : '';
            }
            this._urlparams = o;
            return o;
        }
    },

    // returns html file name
    filename: function() {
        var f = ((location+'').split(/\?/))[0].split(/\//);
        f = f[f.length-1];
        return f;
    },

    // returns array from the collection object
    collection: function(name) {
        var c = this.arraycol;
        return  !c[name] ? c[name] = [] : c[name];
    },

    // register ready event. This is event normally triggered before onload
    ready: function(fn) {
        var a = this.collection('ready');
        if (this.isReady) setTimeout(function(){fn(jQuery)},100);
        else a[a.length] = fn;
        return this.initEvents();
    },

    // register page load event
    load: function(fn){
        var a = this.collection('load');
        if (this.isLoad) fn(jQuery);
        else a[a.length] = fn;
        return this.initEvents();
     },

    // register page unload event
    unload: function(fn){
        var a = this.collection('unload');
        a[a.length] = fn;
        return this.initEvents();
    },

    /**
     * Bind a function to an event
     */
    bind: function(css,evt,fn){
        var a = this.collection('binds');
        if (this.isReady && self.jQuery) jQuery(css).bind(evt,fn);
        else a[a.length] = [css,evt,fn];
        return this.initEvents();
    },

    /**
     * Post data to the server.
     * form - form element used when uploading upload files.
     */
    post: function (url,data,form,target){
        var i,f,div,str = '';
        var b = document.getElementsByTagName("body");
        if (!b) return this; else b = b[0];
        if (form) f = form;
        else  {
            f = document.createElement('form');
            b.appendChild(f);
        }
        f.action = url;
        f.setAttribute('method','post');
        if (target) f.setAttribute('target',target);
        if (data) for (i in data) {
            if (!f.elements[i])
                str += '<input name="'+i+'" type="hidden" />';
        }
        if (str) {
            div = document.createElement('div');
            div.innerHTML = str;
            f.appendChild(div);
        }
        if (f && data) for (i in data) {
            if (f.elements[i].type=='hidden')
                f.elements[i].value = data[i];
        }
        f.submit();
        f.removeChild(div); // remove elements after submiting form
        return this;
    },

    /**
     * Dynamically includes a CSS file
     */
    css:function(src,ext){
        var f,k = 'css'+src;
        if (src && !this.inc[k]) {   // check if already included
            f = !ext ? this.csspath + src + '.css' : src; // check if script is external
            this.insertScript(f,'text/stylesheet');
            if (src.toLowerCase()=='master' && document.all) // apply IE css master fixes
                document.write('<!--[if IE]><link rel="stylesheet" href="'+(this.csspath + src)+'.ie.css" type="text/css" media="screen, projection"><![endif]-->');
            this.inc[k] = true;
        }
        return this;
    },

    /**
     * Dynamically includes a Javascript file
     */
    include: function(src,extrn,fn) {
        var i,l,n,id,url;
        if (typeof src == 'string') src = [src];
        l = src.length;
        for (i=0; i<l; i++) {
            n = src[i] + '';
            if (n && !this.inc[n]) {   // check if already included
                url = !extrn ? this.pluginpath + n + '.js' : n;  // check if script is external
                this.inc[n] = fn ? fn : true;
                id = this.insertScript(url,'text/javascript');
            }
        }

        // trigger callback function
        if (typeof fn == 'function') {
            if (!id) fn();
            else {
                this.inc[id] = n;
                if (document.all) this.insertScript(this.path+'callback.js?'+id,'text/javascript');
                else this.insertScript('Raxan.callback("'+id+'")','text/javascript',true);
            }
        }

        return this;
    },


    /**
     * Insert Script Tag into document
     */
    insertScript:  function(src,type,embedded) {
        var doc = document;
        var elm,id = 'xr'+ this.cnt++;
        var tag,headTag = doc.getElementsByTagName("head")[0];

        type = (type) ? type : 'text/javascript';

        if (headTag && doc.body) {
            // document loaded - append scripts/css
            if (type=='text/stylesheet') {
                elm = doc.createElement("link");
                elm.setAttribute("rel", 'stylesheet');
                elm.setAttribute("href", src);
            }
            else {
                elm = doc.createElement("script");
                elm.setAttribute('type',type);
                if(!embedded) elm.setAttribute("src", src);
                else {
                    if (doc.all) elm.innerHTML = src;
                    elm.appendChild(doc.createTextNode(src));
                }
            }
            elm.setAttribute("id", id);
            headTag.appendChild(elm);
            //headTag.removeChild(elm); // don't remove <script> tag - fixes issue with IE when using inside the <body tag>
        }
        else {
            // document not loaded - write scripts
            if(type=='text/stylesheet') { // css
                if (!embedded) tag = '<link id="'+ id +'" rel="stylesheet" href="'+ src +'" />';
                else tag = '<style id="'+ id +'" type="text/stylesheet">'+ src +'</style>';
            }
            else { // javascript
                if (!embedded) tag = '<script id="'+ id +'" type="'+ type +'" src="'+ src +'"><\/script>';
                else tag = '<script id="'+ id +'" type="'+ type +'">'+ src + '<\/script>';
            }
            document.write(tag);
        }

        return id;
    },

    // log to firebug console or window status
    log: function(txt) {
        if (window.console) console.log(txt);
        else alert(txt);
    },

    // For internal use only - Update Client Element
    iUpdateClient: function(selectors,source,sourceDelim) {
        var $ = jQuery; source = source.split(sourceDelim);
        $(selectors).each(function(i) {
            Raxan.iUpdateElement(this,$(source[i]).get(0));
        });
    },
    // @todo: this method needs to be optimized and modified to support ui widgets
    iUpdateElement: function(srcElm,targetElm) {
        var expando,cb = [], src = srcElm, tar = targetElm;

        // get jQuery expando - the hard way :(
        if (this.expando) expando = this.expando;
        else {
            var a = $('<div />').data('test',1).get(0);
            for (i in a) if (i.indexOf('jQuery')==0) expando = this.expando = i;
        }

        function cloneEvents(elm,mode,path,index) {
            var i, e, l, data,events, type, handler, ix = 1;
            if ( elm.nodeType == 3 || elm.nodeType == 8 ) return;
            if (!index) index = '';
            path = path ? path + '/' : '';
            path+= elm['id'] ? elm['id'] : elm.nodeName.toLowerCase() + index;
            if (mode=='copy') {
              e = elm[expando] ? elm[expando] : jQuery.data(elm);
              if (jQuery.cache[e]) cb[path] = jQuery.cache[e];
            }
            else if (mode=='paste' && cb[path]) {
                // clone events and data
                data = cb[path];
                events = data['events'];
                delete data['handle']; delete data['events'];
                for (type in data) jQuery.data(elm,type,data[type]);
                for ( type in events ) {
                    for ( handler in events[ type ] ) {
                        jQuery.event.add( elm, type, events[ type ][ handler ], events[ type ][ handler ].data );
                    }
                }
            }
            l = elm.childNodes.length;
            if(l) for(i=0; i<l; i++) {
                e = elm.childNodes[i];
                if ( e.nodeType != 3 && e.nodeType != 8 ) {
                    cloneEvents(e,mode,path,ix++);
                }
            }
        }
        cloneEvents(src,'copy');
        cloneEvents(tar,'paste');
        var s  = src.style, t = tar.style;  // retain elmement position
        if(s.position && !t.position) t.position = s.position
        if(s.left && !t.left) t.left = s.left
        if(s.top && !t.top) t.top = s.top
        $(srcElm).replaceWith(targetElm);   // replace element
    }

}

/* PDI Transporter Functions */
$bind = Raxan.bindRemote = function(css,evt,val,serialize,ptarget,script,options) {
    var $ = jQuery;

    // custom delegate function
    if(!$.fn.rxlive) {
        $.fn.rxlive = function(css,event,fn) {
            var e, s = (css!==true) ? css: '',p = this.selector + ' ';
            if (!s.indexOf(',')) s = p + s;
            else s = p+(s.split(/,/)).join(','+p);
            e = jQuery(document); e.selector = s; e.live(event,fn);
            return this;
        }
    }

    evt = $.trim(evt);
    var type = evt.substr(0,1)=='#' ? evt.substr(1) : evt;
    var delay,delegate,disable,toggle,icache,before,after,sba,repeat=1,o = options;
    if (o===true) delegate = true; // last param can be true (for delegates) or an array of options
    else if (o) {
        delegate = (o['dt']) ? o['dt'] : false;
        delay = o['dl'] ? o['dl'] : 0; disable = o['ad'] ? o['ad'] : '';
        toggle = o['at'] ? o['at'] : ''; icache = o['ic'] ? o['ic'] : '';
        repeat = o['rpt'] ? o['rpt'] : repeat; sba = o['sba'] ? o['sba'] : ''
    }
    var cb = function(e,data){
        var preventPostback = false;
        var preventDefault = (e.type=='click'||e.type=='submit') ? true : false;
        var me = this, t = ptarget ? ptarget : this.getAttribute('id')+'' ;
        e.currentTarget = this; // needed for jQuery.live() 1.3.2 ?
        if (delegate && !ptarget) t = css + (delegate!==true ? ' '+ delegate : ''); // append delegate css to target
        if (script) {
            before = script['before'] ? script['before'] : script;
            after = script['after'] ? script['after'] : '';
        }

        if (before) eval(before);
        if (!preventPostback) {
            var opt = {
                event: e, data: data,
                sba : sba,  // switchboard action
                callback: function(result,status){
                    if (disable) $(disable).attr('disabled','');
                    if (toggle) $(toggle).hide();
                    if (after) eval(after);
                }
            },
            fn = function() {
                if (icache && (me.type=='text'||me.tagName=='textarea')) {  // input cache
                    var old = $(me).data('clxOldValue'), nw = me.value;
                    if (nw && (nw+'').length < icache) return;
                    else if (old!=nw) $(me).data('clxOldValue',nw);
                    else return;
                }
                // auto-diable element
                if (disable) {
                    var d = $(disable = (disable==1) ? me : disable);
                    if (d.attr('disabled')=='disabled') return ;
                    d.attr('disabled','disabled');
                }
                // auto-toggle element
                if (toggle) $(toggle = (toggle==1) ? me : toggle).show();
                preventDefault = Raxan.triggerRemote(t,evt,val,serialize,opt)===false ? true : preventDefault;
            }
            if (!delay) fn();
            else {
                clearTimeout($(me).data('clxTimeout')||0)
                $(me).data('clxTimeout',setTimeout(fn,delay));
            }
        }
        if (preventDefault) e.preventDefault();
    }

    if (isNaN(type)) {
        if (!delegate) $(css).bind(type,cb);
        else $(css).rxlive(delegate,type,cb);
    }
    else {  // timeout
        var cnt = 1,tmr = 0,ms = parseInt(type);
        if (ms<1000) ms = 1000;
        tmr = window.setInterval(function() {
            if (repeat!==true && repeat>=1 && cnt>repeat) clearTimeout(tmr);
            else {
                var elm,e = $.Event(type);
                e.result = undefined;
                e.currentTarget = e.target = elm = $(css).get(0);
                cb.call(elm,e,null); cnt++;
            }
        },ms);
    }
}

$trigger = Raxan.triggerRemote = function(target,type,val,serialize,opt){
    opt = opt || {};
    var e = opt.event, callback = opt.callback, sba = opt.sba;
    var i, a, s, telm, tname, isupload, form, post = {}, tmp, url, isAjax=false;
    if(!type) type = 'click';  // defaults to click
    if (type.substr(0,1)=='#') { isAjax  = true; type=type.substr(1) }
    tmp = target.split(/@/); // support for target@url
    target = tmp[0]; url = tmp[1] ? tmp[1] : _PDI_URL;
    if (!url) url = self.location.href;
    if (sba) { // setup switchboard action
        url = url.replace(/sba=[^&]*/,'').replace(/[\?&]$/,'');
        url+= (url.indexOf('?')==-1 ? '?' : '&')+'sba=' + sba;
    }

    // get event current target element
    if (e && (e.currentTarget||e.target)) {
        telm = e.currentTarget || e.target;
        tname = (telm.nodeName+'').toLowerCase();
        isupload = (tname=='form' && (/multipart\/form-data/i).test(telm.encoding)); // check form encoding
        if (isupload) form = telm;
    }

    // get default value from currentTarget or target element
    if (telm && (val===''||val===null)) {
        var n,nn,targets = e ? [e.currentTarget,e.target] : [];
        for(n in targets) {
            n = targets[n]; if (!n) continue;
            nn = (n.nodeName+'').toLowerCase(); //  get node name
            if ((/v:/i).test(n.className)) {
                s = n.className.match(/v:(\w+)/)[1]; // extract value from class name using format v:value
            }
            else if (nn=='a'||nn=='area') {
                // extract value from anchor hash
                s=((n.href||n.getAttribute('href'))+'').split(/\#/)[1];
            }
            else if (nn=='input'||nn=='select'||nn=='textarea') {
                // extract value from element
                s = $(n).serializeArray()[0];
                s = s ? s['value'] : null;
            }
            if (s) break;
        }
        val = (s) ?  s : val;
    }
    // if target is form then serialize the form
    if (!serialize && tname=='form' && !isupload) serialize = telm;
    else if (!serialize && (tname=='input'||tname=='button') &&  // if target is submit button then serialize form
        (/submit|image/i).test(telm.type) && telm.form) {
        isupload = (/multipart\/form-data/i).test(telm.form.encoding); // check form encoding
        if (isupload) form = telm.form;
        else serialize = telm.form;
    }
    // serialize selector values
    if (serialize) {
        s = $(serialize).serializeArray();
        for (i in s) {
            if (!post[s[i].name]) post[s[i].name] = s[i].value;
            else {
                // build array - fix for php
                a = post[s[i].name];
                if (typeof a != 'object') a = [a];
                a[a.length] = s[i].value
                post[s[i].name] = a;
            }
        }
    }

    // get token
    var token, en, st, c = document.cookie;
    st = c.indexOf('_ptok=');
    if (st>=0) {
        en = c.indexOf(';', st);
        token = en > 0 ? c.substr(st+6,en-(st+6)) : c.substr(st+6);
    }

    // prepare post data
    post['_e[type]']=type;
    post['_e[value]']=val; post['_e[target]']=target;
    post['_e[tok]'] = token;    // set postback token

    if (e) {
        var o = $(e.target).offset();
        if (e.which) post['_e[which]'] = e.which; if (e.button)post['_e[button]'] = e.button;
        if (e.ctrlKey) post['_e[ctrlKey]'] = e.ctrlKey; if (e.metaKey) post['_e[metaKey]'] = e.metaKey;
        if (e.pageX) post['_e[pageX]'] = e.pageX; if (e.pageY) post['_e[pageY]'] = e.pageY;
        if (o.left) post['_e[targetX]'] = o.left; if (o.top) post['_e[targetY]'] = o.top;
    }
    if (opt.data) { // check for extra ui objects
       var ui =  opt.data;
       if (ui.helper) post['_e[uiHelper]'] = ui.helper.attr('id');
       if (ui.sender) post['_e[uiSender]'] = ui.sender.attr('id');
       if (ui.draggable) post['_e[uiDraggable]'] = ui.draggable.attr('id');
    }

    // post data to server
    if (!isAjax) Raxan.post(url, post, (isupload ? form : null));
    else {
        post['_ajax_call_'] = 'on';  // let server know this is ajax
        $.ajax({
            cache: false,
            url: url, type: 'post',
            data : post, dataType: 'json',
            success: function(data) {
                var _ctarget_= e ? e.currentTarget : null, _target_= e ? e.target : null; // refrenced as this and target
                if(!data) return;
                if (data['_actions']) eval(data['_actions']);
                if (callback) callback(data['_result'],true); // pass ajax results to callback function
            },
            error: function(s) {
                var rt;
                if (callback) rt = callback(s.responseText,false); // pass results to callback function
                if (rt!==false) Raxan.log("Error while connecting to Server:\n\n" + s.responseText);
            },
            dataFilter: function(data) {
                // support for native JSON parser - http://ping.fm/UFKii
                if (typeof (JSON) !== 'undefined' &&
                    typeof (JSON.parse) === 'function') data = JSON.parse(data);
                return data;
            },
            xhr: function(){    // XHR for postbacks and file uploads
                var fn = function(){};
                return !isupload ? $.ajaxSettings.xhr(): {
                    status:404, readyState: 0,
                    getResponseHeader: fn, setRequestHeader: fn,
                    open:function(type,url){
                        var id =  $.data(this);
                        var frame = '<iframe name="rx01Ajax'+id+'" src="about:blank" width="1" height="1" '+
                                    'style="position:absolute;left:-1000px;visibility:hidden"/>'
                        var me = this;
                        me.url = url; me.readyState = 1;
                        me.frm = $(frame).load(function(){
                            var f = me.frm;
                            var d = f.contentDocument||f.contentWindow.document;
                            if (d.location=='about:blank') return; // opera needs this?
                            me.responseText = $('textarea',d).val()|| $('body',d).html();
                            me.readyState = 4; me.status = 200;
                            $(f).unbind(); // unbind event to prevent looping in IE
                            d.open(); d.close(); // close document to prevent busy cursor in FF
                            me.abort()
                        }).get(0);
                        document.body.appendChild(this.frm);
                    },
                    send:function(){
                        var target = 'rx01Ajax'+$.data(this);
                        post['_ajax_call_'] = 'iframe';
                        Raxan.post(this.url, post, form,target);
                    },
                    abort:function(){
                        if (this.frm) {
                            document.body.removeChild(this.frm);
                            this.frm = null;
                        }
                    }
                };
            }
        });
    }
}


Raxan.init();


