/**
 -------------------------------------------------------------------------
 CMC for PHP is a web framework for PHP.                              
 More information can be seen here: <http://cmc.calmarsoft.com/about>
 -------------------------------------------------------------------------
 
 Copyright (c) 2014 by Calmarsoft company <http://calmarsoft.com> (FRANCE). All rights reserved.
 
 This file is part of CMC for PHP.
 
 CMC for PHP is free software: you can redistribute it and/or modify
 it under the terms of the GNU General Public License as published by
 the Free Software Foundation, either version 3 of the License, or
 (at your option) any later version.
 
 CMC for PHP is distributed in the hope that it will be useful,
 but WITHOUT ANY WARRANTY; without even the implied warranty of
 MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 GNU General Public License for more details.
 
 You should have received a copy of the GNU General Public License
 along with CMC for PHP.  If not, see <http://www.gnu.org/licenses/>.
 **/

var cmc = function() {
    var _msie;

    var frame = function(fname) {
        var widget = function() {
            var jsObj, jsType, wname;
            var props_srv = {}, props = {};
            var valid_fcts = [];
            var valid_peers = [];
            var validation = false;
            var valid = true;
            var bAutoFill = false;
            var parentframe = null;
            var urlattr = ['href', 'src', 'action'];
            var matchvar = /[$]{([a-zA-Z_][a-zA-Z0-9%_\-]*)}|[$]([a-zA-Z][a-zA-Z0-9%_]*)/g;

            var stringify = function(item) {
                if (item === null)
                    return '';
                return String(item);
            };

            // using 'ajaxForm' for data upload
            var ajaxForm_cmc = {
                beforeSend: function(xhr, o, opt) {
                    cmc.refpost(xhr);
                    if (typeof this['init'] === 'function')
                        this.init(xhr, o, opt);
                },
                beforeSubmit: function(args, o, opt) {
                    var a, e;
                    var jsData = cmc.postdata(fname, wname, 'upload');
                    a = jQuery.grep(args, function(e) {
                        if (!e.hasOwnProperty('type'))
                            return false;
                        if (e.type !== 'file')
                            return false;
                        return true;
                    });
                    while (args.shift()) {
                    }
                    e = a.shift();
                    while (e) {
                        args.push(e);
                        e = a.shift();
                    }
                    var formdata = JSON.stringify(jsData);
                    args.push({name: "cmcdata", type: "text", value: formdata});
                    if (cmc.msie() !== null && cmc.msie() <= 8)
                        jQuery("input[name='cmcdata']").val(formdata);
                    return true;
                },
                uploadProgress: function(event, position, total, percentComplete) {
                    if (typeof this['upload'] === 'function')
                        this.upload(event, position, total, percentComplete);
                    if (cmc.timer)  //TODO: better solution
                        window.clearTimeout(cmc.timer);
                    cmc.timer = window.setTimeout(function() {
                        cmc.ajaxSetWaitState(true);
                    }, 2000);
                },
                complete: function(xhr, textstatus) {
                    if (cmc.timer)
                        window.clearTimeout(cmc.timer);
                    var data = jQuery.parseJSON(xhr.responseText);
                    cmc.postDone(data, textstatus, xhr);
                    if (typeof this['finish'] === 'function')
                        this.finish(xhr, textstatus, true);

                },
                error: function(data, textstatus, xhr) {
                    if (cmc.timer)
                        window.clearTimeout(cmc.timer);
                    cmc.postError(data, textstatus, xhr);
                    if (typeof this['finish'] === 'function')
                        this.finish(xhr, textstatus, false);
                }
            };
            ////////////
            //
            // widget level class
            return {
                create: function(selector, type, name, parent) {
                    wname = name;
                    jsType = type;
                    jsObj = jQuery(selector);
                    parentframe = parent;

                    if (type === 'upload')
                        type = 'ajaxForm';
                    if (jsObj && typeof jsObj[type] === 'function') {
                        var args = Array.prototype.slice.call(arguments, 4, arguments.length);
                        if (jsType === 'upload') {
                            jsType = ''; // because using it is destructive
                            if (args[0] && typeof args[0] === 'object')
                                args[0] = jQuery.extend(args[0], ajaxForm_cmc);
                            else
                                args[0] = ajaxForm_cmc;

                            if (cmc.msie() !== null && cmc.msie() <= 8)
                            {
                                var form = jsObj.ajaxForm();
                                if (form !== null && form.find("fieldset").size() === 1)
                                {
                                    var fs = form.find("fieldset");
                                    if (fs.find("input[name='cmcdata']").size() === 0)
                                    {
                                        var df = document.createElement('input');
                                        df['name'] = 'cmcdata';
                                        if (df['style'] !== undefined && df['style']['display'] !== undefined)
                                            df['style']['display'] = 'none';
                                        fs[0].appendChild(df);
                                    }
                                }
                            }
                        }
                        jsObj[type].apply(jsObj, args);
                    }
                    if (type === 'input' && jsObj.attr('type') === 'password')
                        bAutoFill = true;

                    this.initprops();
                    return this;
                },
                // setup an event to trigger a POST exchange
                eventpost: function(eventname) {
                    if (jQuery.inArray(eventname, ['click', 'change', 'activate']) > -1) {
                        if (jsType === 'tabs' && eventname === 'activate')
                            eventname = 'tabsactivate';
                        if (jsObj['on']) {
                            jsObj.off(eventname, cmc.eventpost);
                            jsObj.on(eventname, this, cmc.eventpost);
                        } else {
                            jsObj.unbind(eventname, cmc.eventpost);
                            jsObj[eventname](this, cmc.eventpost);
                        }
                    }
                    return this;
                },
                // setup a local event
                event: function(eventname, local) {
                    if (jQuery.inArray(eventname, ['click', 'change']) > -1) {
                        if (!jsObj[eventname])
                            return;
                        if (jsObj['on']) {
                            jsObj.off(eventname, null, cmc.event);
                            jsObj.on(eventname, null, this, cmc.event);
                            if (local) {
                                jsObj.off(eventname, null, local);
                                jsObj.on(eventname, null, this, local);
                            }
                        } else {
                            jsObj.unbind(eventname, cmc.event);
                            jsObj[eventname](this, cmc.event);
                            if (local) {
                                jsObj.unbind(eventname, local);
                                jsObj[eventname](this, local);
                            }
                        }
                    }
                    return this;
                },
                // called each time a validation is needed (keyup, change). Bound using validate function below.
                validate_check: function(event) {
                    var result = true;
                    var i, val_fct;
                    if (event && event.currentTarget !== jsObj[0])
                        return;

                    for (i in valid_fcts) {
                        val_fct = valid_fcts[i];
                        if (!val_fct.fct.apply(val_fct.fct, val_fct.args))
                            result = false;
                    }
                    // changed? => propagate to parent and update the 'invalid' class
                    if (result !== valid || event === null) {
                        valid = result;
                        parentframe.validationChange(event);
                        if (valid)
                            jsObj.removeClass('cmc-invalid');
                        else
                            jsObj.addClass('cmc-invalid');
                    }
                    var p;
                    var new_event;
                    for (p in valid_peers) {
                        var peer = valid_peers[p];
                        if (peer.haspeer(this))
                            continue;
                        if (!new_event)
                            new_event = jQuery.extend({}, event);
                        new_event['currentTarget'] = peer.o()[0];
                        peer.validate_check(new_event);
                    }

                    if (!bAutoFill)
                        parentframe.checkAutoFill(event);
                    parentframe.typingEvent(event);
                    return true;
                },
                checkAutoFill: function(event) {
                    if (bAutoFill) {
                        var new_event = jQuery.extend({}, event);
                        new_event['currentTarget'] = jsObj[0];
                        this.validate_check(new_event);
                    }
                }
                ,
                valid_status: function() {
                    if (validation)
                        return valid;
                    else
                        return true;
                },
                validate_addcb: function(fn) {
                    if (!validation) {  // if first validation function, attach JS change events
                        validation = true;
                        if (jQuery.inArray(jsType, ['input', 'textarea', 'select']) > -1) {
                            if (jsObj['on']) {
                                jsObj.on('change', null, this, fn);
                                jsObj.on('select', null, this, fn);
                                jsObj.on('keyup', null, this, fn);
                            } else {
                                jsObj['change'](this, fn);
                                jsObj['select'](this, fn);
                                jsObj['keyup'](this, fn);
                            }
                        } else if (jsType === 'checkbox') {
                            if (jsObj['on'])
                                jsObj.on('click', null, this, fn);
                            else
                                jsObj['click'](this, fn);
                        }
                    }
                },
                add_validfct: function(fn, args) {
                    valid_fcts.push({fct: fn, args: args});
                },
                add_validpeer: function(peer) {
                    valid_peers[valid_peers.length] = peer;
                },
                haspeer: function(obj) {
                    return (valid_peers.indexOf(obj) !== -1);
                },
                // attach a validation function
                validate: function(fn) {
                    if (!jsObj) // object not on current screen, ignoring!
                        return this;

                    if (typeof fn === 'function') {
                        var args = Array.prototype.slice.call(arguments, 1, arguments.length);
                        args = Array.prototype.concat.call([this], args);

                        this.add_validfct(fn, args);
                        this.validate_addcb(this.validate_check);

                        /*if (fn===cmc.valid.equals) {
                         parentframe.w(args[1]).validate(fn, wname);
                         }*/
                        // update validation status (initial check with only new function)
                        valid = valid && fn.apply(fn, args);

                        if (fn === cmc.valid.equals) {
                            var peer = parentframe.w(args[1]);
                            peer.add_validpeer(this);
                        }

                        if (valid)
                            jsObj.removeClass('cmc-invalid');
                        else
                            jsObj.addClass('cmc-invalid');
                    }
                    return this;
                },
                // pass any method to the inner object
                option: function() {
                    var mth = arguments[0];
                    if (jsObj && typeof jsObj[mth] === 'function') {
                        Array.prototype.shift.call(arguments);
                        jsObj[mth].apply(jsObj, arguments);
                    }
                    return this;
                },
                o: function() {
                    return jsObj;
                },
                // Xpath utility function
                _x: function(STR_XPATH, node) {
                    var xresult;
                    var xnodes = [];
                    var xres;
                    if (document['evaluate']) {
                        xresult = document.evaluate(STR_XPATH, node, null, XPathResult.ANY_TYPE, null);
                        while (xres = xresult.iterateNext())
                            xnodes.push(xres);
                    } else {

                    }

                    return xnodes;
                },
                // gets the node 'value' from given dom item
                nodeVal: function(dom) {
                    var node;
                    if (dom && dom['length']) {
                        if (dom['length'] === 0)
                            return null;
                        if (dom['length'] > 1)
                            return undefined;
                        node = dom[0];
                        if (node['type'] === 'checkbox')
                            return node.checked;
                        else if (node['type'] === 'text')
                            return node.value;
                        else if (node['type'] === 'textarea')
                            return node.value;
                        else if (node['type'] === 'select-one') {
                            return node.value;
                        } else {
                            if (node.nodeType === 1) {
                                if (!node.firstChild)
                                    return undefined;
                                else
                                    return node.firstChild.nodeValue;
                            } else if (node.nodeType === 2 && jQuery.inArray(node.name, urlattr) > -1) {   // attribute
                                return decodeURI(node.nodeValue);
                            } else
                                return node.nodeValue;
                        }
                    }
                    return undefined;
                },
                // sets the node 'value' from givent dom item
                setNodeVal: function(dom, val) {
                    var node;
                    if (dom && dom['length']) {
                        if (dom['length'] === 0)
                            return;
                        if (dom['length'] > 1)
                            return;
                        node = dom[0];
                        if (node['type'] === 'checkbox')
                            node.checked = val;
                        else if (node['type'] === 'select-one')
                            node.value = val;
                        else if (node['type'] === 'textarea')
                            node.value = val;
                        else {
                            if (node.nodeType === 1) {
                                if (!node.firstChild)
                                    node.appendChild(document.createTextNode(val));
                                else
                                    node.firstChild.nodeValue = val;
                            } else if (node.nodeType === 2 && jQuery.inArray(node.name, urlattr) > -1) {   // attribute
                                node.nodeValue = encodeURI(val);
                            } else
                                node.nodeValue = val;
                        }
                    }

                },
                // Composite component: initialize data, keys and mapping
                hdrmap: function(map, mapkeys) {
                    props['hdrmap'] = map;
                    props['hdrmapkeys'] = mapkeys;
                    props['compData'] = [];

                    this.applyCompData();
                    return this;
                },
                // Composite component: update data (from POST)
                updCompData: function(newdata) {
                    var d, i;
                    if (!jsObj[0] || !jsObj[0]['rows'])  //FIXME: find a more generic method
                        return;
                    if (!newdata)
                        newdata = [];
                    if (jsObj[0].rows.length < newdata.length) {
                        d = newdata.length - jsObj[0].rows.length;
                        for (i = 0; i < d; i++) {
                            jsObj.append($(jsObj[0].rows[0]).clone().hide());
                        }
                    } else if (jsObj[0].rows.length > newdata.length) {
                        d = newdata.length;
                        if (d === 0) {
                            $(jsObj[0].rows[0]).hide();
                            d++;
                        }
                        for (i = jsObj[0].rows.length - 1; i >= d; i--) {
                            $(jsObj[0].rows[i]).remove();
                        }
                    }
                    var ilig, colpath, dom, row, rowval;
                    for (ilig = 0; ilig < newdata.length; ilig++) {
                        row = jsObj[0].rows[ilig];
                        rowval = newdata[ilig];
                        for (colpath in props['hdrmap']) {
                            dom = this._x(colpath, row);
                            var key = props['hdrmap'][colpath];
                            var match = 0;
                            // handles variable substitution ( $var or ${var} )
                            var value = key.replace(matchvar,
                                    function(strmatch, item) {
                                        match++;
                                        return rowval[item] === null ? '' : rowval[item];
                                    });
                            if (match === 0) {   // if no var, assumes this is the key name itself
                                value = rowval[key] === null ? '' : rowval[key];
                            }
                            if (value === undefined) {
                                value = dom[0].defaultValue;
                                this.setNodeVal(dom, value);
                                newdata[ilig][key] = this.nodeVal(dom);
                            }
                            else {
                                this.setNodeVal(dom, value);
                            }
                        }
                        $(jsObj[0].rows[ilig]).show();
                    }
                    props['compData'] = newdata;
                },
                applyCompData: function() {
                    var ilig, row;
                    var newCompData, colpath, colname, dom, itemval;
                    if (!jsObj[0] || !jsObj[0]['rows'])  //FIXME: find a more generic method
                        return;
                    newCompData = [];

                    var tab_len = jsObj[0].rows.length;
                    if (tab_len === 1 && $(jsObj[0].rows[0]).css('display') === 'none')
                        tab_len = 0;

                    for (ilig = 0; ilig < tab_len; ilig++) {
                        row = jsObj[0].rows[ilig];
                        for (colpath in props['hdrmap']) {
                            colname = props['hdrmap'][colpath];
                            dom = this._x(colpath, row);
                            itemval = this.nodeVal(dom);
                            if (!newCompData[ilig])
                                newCompData[ilig] = {};
                            newCompData[ilig][colname] = itemval;
                        }
                    }
                    props['compData'] = newCompData;
                },
                // Composite component: prepare POST data from changed items
                compDataChange: function() {
                    var ilig, iligr, row, dom, colname, colpath, itemval, rowdiff, result, tab_len;
                    var keyname, keypart, ikey;
                    iligr = 0;
                    if (!jsObj[0] || !jsObj[0]['rows'])  //FIXME: find a more generic method
                        return;
                    tab_len = jsObj[0].rows.length;
                    if (tab_len === 1 && $(jsObj[0].rows[0]).css('display') === 'none')
                        tab_len = 0;

                    for (ilig = 0; ilig < tab_len; ilig++) {
                        row = jsObj[0].rows[ilig];
                        rowdiff = undefined;
                        for (colpath in props['hdrmap']) {
                            colname = props['hdrmap'][colpath];
                            if (colname.match(matchvar))
                                continue;
                            dom = this._x(colpath, row);
                            itemval = this.nodeVal(dom);
                            if (!props['compData'] || !props['compData'][ilig] ||
                                    (itemval !== props['compData'][ilig][colname]
                                            && itemval !== stringify(props['compData'][ilig][colname]))) {
                                if (!rowdiff)
                                    rowdiff = {};
                                rowdiff[colname] = itemval;
                                props['compData'][ilig][colname] = itemval; // wait refresh from server 
                            }
                        }
                        keypart = undefined;
                        if (rowdiff) {
                            if (!result)
                                result = [];
                            for (ikey in props['hdrmapkeys']) {
                                keyname = props['hdrmapkeys'][ikey];
                                if (!keypart)
                                    keypart = {};
                                keypart[keyname] = props['compData'][ilig][keyname];
                                if (keypart[keyname] === undefined)
                                    keypart[keyname] = '';
                            }
                            if (keypart)    // else this is managed by server
                                rowdiff['_rowkey'] = keypart;
                            result[iligr] = rowdiff;
                        }
                        if (rowdiff || !props['hdrmapkeys'] || props['hdrmapkeys'].length === 0)
                            iligr++;
                    }
                    if (props['compData'])
                        for (ilig = tab_len; ilig < props['compData'].length; ilig++) {
                            if (!result)
                                result = [];
                            result[ilig] = null;
                        }
                    return result;
                },
                name: function() {
                    return wname;
                },
                fname: function() {
                    return fname;
                },
                f: function() {
                    return parentframe;
                },
                show: function() {
                    jsObj.show();
                    return this;
                },
                hide: function() {
                    jsObj.hide();
                    return this;
                },
                enable: function() {
                    if (jsType === 'button')
                        jsObj.button('enable');
                    else
                        jsObj.removeAttr('disabled');
                    return this;
                },
                disable: function() {
                    if (jsType === 'button')
                        jsObj.button('disable');
                    else
                        jsObj.attr('disabled', true);
                    return this;
                },
                changepropval: function(propname, newval)
                {
                    props[propname] = newval;
                },
                /* initialize the properties we want client back to server */
                initprops: function() {
                    if (!jsObj)
                        return;
                    if (jsType === "label") {
                        props['caption'] = props_srv['caption'] = jsObj.text();
                        props['html'] = props_srv['html'] = jsObj.html();
                    } else if (jsType === "tabs") {
                        props['value'] = jsObj.tabs('option', 'active');
                    } else if (jsType === "input" || jsType === "textarea") {
                        props['value'] = jsObj.val();
                        if (jsObj.attr('autocomplete') !== 'on')
                            props_srv['value'] = props['value'];
                        this.validate_check(null);
                    }
                },
                updateprops: function() {
                    switch (jsType) {
                        case 'textarea':
                        case 'checkbox':
                            props['value'] = this.nodeVal(jsObj);
                            break;
                        case 'select':
                        case 'input':
                            props['value'] = jsObj.val();
                            break;
                        case 'tabs':
                            props['value'] = jsObj.tabs('option', 'active');
                            break;
                        case 'compositelist':
                            break;
                    }
                },
                /* here are ajax updates from server */
                onpropchange: function(propname) {
                    var value = props[propname];
                    if (jsType === 'label') {
                        if (propname === 'caption')
                            jsObj.text(value);
                        else if (propname === 'html')
                            jsObj.html(value);
                    } else if (jsType === "tabs") {
                        jsObj.tabs('option', 'active', value);
                    } else if (jsType === 'input' || jsType === 'textarea' || jsType === 'select') {
                        if (propname === 'value') {
                            if ((jsObj[0].type !== 'file' || value === '') &&
                                    value !== jsObj.val())
                                jsObj.val(value);
                        }
                    }
                    // following is R/O
                    if (propname === 'enabled') {
                        if (value)
                            this.enable();
                        else
                            this.disable();
                    } else if (propname === 'visible') {
                        if (value)
                            this.show();
                        else
                            this.hide();
                    }
                },
                length: function() {
                    if (jsType === 'input' || jsType === 'textarea' || jsType === 'select')
                        return jsObj.val() && jsObj.val().length;
                    return null;
                },
                value: function() {
                    if (jsType === 'input' || jsType === 'textarea' || jsType === 'select')
                        return jsObj.val();
                    if (jsType === 'checkbox')
                        return this.nodeVal(jsObj);
                    return null;
                },
                getdata: function()
                {
                    return props;
                },
                // widget-level post data
                post: function(widgetname, eventname) {
                    var result, propname;
                    this.updateprops();
                    for (propname in props) {
                        if (propname === 'compData') {
                            var compData = this.compDataChange(props[propname]);
                            if (compData) {
                                if (!result)
                                    result = {};
                                result[propname] = compData;
                            }
                        } else if (jQuery.inArray(propname, ['hdrmap', 'hdrmapkeys']) > -1) {
                        } else if (props_srv[propname] !== props[propname]) {
                            if (!result)
                                result = {};
                            /* if (jsType==='input' && propname==='value' && jsObj[0]['attributes'] && jsObj[0].attributes['type'].value==='password')
                             result[propname] = '829aebe70129b78c90f57c5921d18ffaec89287c';
                             else*/
                            result[propname] = props[propname];
                            props_srv[propname] = null;
                        }
                    }
                    return result;
                },
                // post is done: update properties and triggers events
                post_done: function(new_data) {
                    var propname;
                    for (propname in new_data) {
                        if (propname === 'compData') {
                            this.updCompData(new_data['compData']);
                        } else if (jQuery.inArray(propname, ['hdrmap', 'hdrmapkeys']) > -1) {
                        } else {
                            props_srv[propname] = props[propname];
                            if (new_data[propname] !== props[propname]) {
                                props_srv[propname] = new_data[propname];
                                this.changepropval(propname, new_data[propname]);
                                if (propname === 'caption') {
                                    delete props_srv['html'];
                                    delete props['html'];
                                } else if (propname === 'html') {
                                    delete props_srv['caption'];
                                    delete props['caption'];
                                }
                            }
                        }
                    }
                    if (props['compData'] && !new_data['compData']) {
                        this.applyCompData();
                    }
                },
                applyprops: function() {
                    var propname;
                    for (propname in props) {
                        /*if (propname === 'compData') {
                         }*/
                        if (jQuery.inArray(propname, ['hdrmap', 'hdrmapkeys']) > -1) {
                        } else {
                            this.onpropchange(propname);
                            props_srv[propname] = props[propname];
                        }
                    }
                }
            };
        };

        var name = fname;
        var widgets = {};
        var valid_listeners = [], type_listeners = [];
        var valid_state = undefined;
        var is_busy = false;
        //////////////////////
        // 
        // frame level class
        return {
            // single widget access
            w: function(name) {
                if (!widgets[name])
                    widgets[name] = widget();
                return widgets[name];
            },
            // getters
            name: function() {
                return name;
            },
            valid: function() {
                return valid_state;
            },
            busy: function() {
                return is_busy;
            },
            clearBusy: function() {
                is_busy = false;
            },
            setBusy: function() {
                is_busy = true;
            },
            // frame-level post data gathering
            post: function(widgetname, eventname) {
                var w, v;
                var r;
                if (is_busy)
                    return false;
                is_busy = true;
                for (w in widgets) {
                    v = widgets[w].post(widgetname, eventname);
                    if (v) {
                        if (!r)
                            r = {};
                        r[w] = v;
                    }
                }
                return r;
            },
            // post anwser processing
            post_done: function(new_data) {
                var w;
                is_busy = false;
                for (w in widgets) {
                    if (new_data[w])
                        widgets[w].post_done(new_data[w]);
                }
            },
            applyprops: function() {
                var w;
                for (w in widgets)
                    widgets[w].applyprops();
            },
            addValidationListener: function(fn, entropy) {
                valid_listeners.push([fn, entropy]);
                valid_state = null;
                this.validationChange({});
            },
            addTypingListener: function(fn, entropy) {
                type_listeners.push([fn, entropy]);
            },
            runListener: function(listener, args) {
                var fn = listener[0];
                if (listener[1] === undefined)
                    fn.apply(fn, args);
                else {
                    // entropy process using timers
                    var that = this;
                    if (listener[2])
                        window.clearTimeout(listener[2]);
                    listener[2] = window.setTimeout(function() {
                        listener[2] = undefined;
                        fn.apply(fn, args);
                    }, listener[1]);
                }
            },
            validationChange: function(event) {
                var w, il, valid = true;
                for (w in widgets) {
                    valid = valid && widgets[w].valid_status();
                }
                if (valid_state !== valid) {
                    valid_state = valid;
                    if (event)
                        for (il in valid_listeners) {
                            this.runListener(valid_listeners[il], [valid_state, event, this]);
                        }
                }
            },
            checkAutoFill: function(event) {
                var w;
                for (w in widgets)
                    widgets[w].checkAutoFill(event);
            },
            typingEvent: function(event) {
                var il;
                for (il in type_listeners) {
                    this.runListener(type_listeners[il], [event, this]);
                }
            }
        };
    };

    var frames = {};
    var ajaxData;
    var ajaxR = [];
    var ajaxWaiting = false;
    var ajaxBlocking = true;

    var postdonelisteners = [];
    ///////////////////////
    //
    // global 'cmc' 
    return {
        valid: {
            nonEmpty: function(wdg) {
                cmc.log('cmc.valid.nonEmpty', wdg.name());
                return (wdg.length() > 0);
            },
            minChars: function(wdg, min) {
                cmc.log('cmc.valid.minChars', wdg.name());
                return (wdg.length() >= min);
            },
            numChars: function(wdg, min, max) {
                cmc.log('cmc.valid.numChars', wdg.name());
                return (wdg.length() >= min && wdg.length() <= max);
            },
            regex: function(wdg, expr) {
                cmc.log('cmc.valid.regex', wdg.name() + (typeof expr));
                return (expr.test(wdg.value()));
            },
            length: function(wdg, len) {
                cmc.log('cmc.valid.length', wdg.name());
                return (wdg.length() === len);
            },
            equals: function(wdg, wname) {
                cmc.log('cmc.valid.equals', wdg.name());
                return (wdg.value() === wdg.f().w(wname).value());
            },
            True: function(wdg, wname) {
                cmc.log('cmc.valid.true', wdg.name());
                return (wdg.value() === true);
            },
            email: function(wdg) {
                cmc.log('cmc.valid.email', wdg.name());
                var regex = /^([a-zA-Z0-9_.+-])+\@(([a-zA-Z0-9-])+\.)+([a-zA-Z0-9]{2,4})+$/;
                return regex.test(wdg.value());
            },
            date: function(wdg) {   // jj/mm/aaaa date check
                cmc.log('cmc.valid.date', wdg.name());
                var s = wdg.value();
                if (typeof (s) === 'string') {
                    var sa = s.split('/');
                    if (sa && sa.length === 3) {
                        var d = new Date(+sa[2], sa[1] - 1, +sa[0]);
                        if (d && !isNaN(d.getTime()) && d.getDate() === +sa[0] &&
                                d.getMonth() === (+sa[1] - 1)) {
                            return true;
                        }
                    }
                }
                return false;
            },
            hostname: function(wdg) {
                cmc.log('hostname', wdg.name());
                var regex = /^([a-zA-Z0-9]|[a-zA-Z0-9][a-zA-Z0-9\-]{0,61}[a-zA-Z0-9])(\.([a-zA-Z0-9]|[a-zA-Z0-9][a-zA-Z0-9\-]{0,61}[a-zA-Z0-9]))*$/;
                return regex.test(wdg.value());
            },
            ipv4: function(wdg) {
                cmc.log('ipv4', wdg.name());
                var regex = /^(([01]?[0-9]?[0-9]|2([0-4][0-9]|5[0-5]))\.){3}([01]?[0-9]?[0-9]|2([0-4][0-9]|5[0-5]))$/;
                return regex.test(wdg.value());
            },
            ipv6: function(wdg) {
                cmc.log('ipv6', wdg.name());
                var regex = /(([0-9a-fA-F]{1,4}:){7,7}[0-9a-fA-F]{1,4}|([0-9a-fA-F]{1,4}:){1,7}:|([0-9a-fA-F]{1,4}:){1,6}:[0-9a-fA-F]{1,4}|([0-9a-fA-F]{1,4}:){1,5}(:[0-9a-fA-F]{1,4}){1,2}|([0-9a-fA-F]{1,4}:){1,4}(:[0-9a-fA-F]{1,4}){1,3}|([0-9a-fA-F]{1,4}:){1,3}(:[0-9a-fA-F]{1,4}){1,4}|([0-9a-fA-F]{1,4}:){1,2}(:[0-9a-fA-F]{1,4}){1,5}|[0-9a-fA-F]{1,4}:((:[0-9a-fA-F]{1,4}){1,6})|:((:[0-9a-fA-F]{1,4}){1,7}|:)|fe80:(:[0-9a-fA-F]{0,4}){0,4}%[0-9a-zA-Z]{1,}|::(ffff(:0{1,4}){0,1}:){0,1}((25[0-5]|(2[0-4]|1{0,1}[0-9]){0,1}[0-9]).){3,3}(25[0-5]|(2[0-4]|1{0,1}[0-9]){0,1}[0-9])|([0-9a-fA-F]{1,4}:){1,4}:((25[0-5]|(2[0-4]|1{0,1}[0-9]){0,1}[0-9]).){3,3}(25[0-5]|(2[0-4]|1{0,1}[0-9]){0,1}[0-9]))/;
                return regex.test(wdg.value());
            },
            iptarget: function(wdg) {
                cmc.log('iptarget', wdg.name());
                return (cmc.valid.hostname(wdg) || cmc.valid.ipv4(wdg) || cmc.valid.ipv6(wdg));
            }
        },
        runListener: function(listener, args) {
            var fn = listener[0];
            if (listener[1] === undefined)
                fn.apply(fn, args);
            else {
                // entropy process using timers
                var that = this;
                if (listener[2])
                    window.clearTimeout(listener[2]);
                listener[2] = window.setTimeout(function() {
                    listener[2] = undefined;
                    fn.apply(fn, args);
                }, listener[1]);
            }
        },
        // accessors
        f: function(name) {
            if (!frames[name])
                frames[name] = frame(name);
            return frames[name];
        },
        n: function(frame, name, selector, type, args) {
            return this.f(frame).w(name).create(selector, type, name, this.f(frame), args);
        },
        waitUI: [
            {delay: 200, timer: -1, fn: function() {
                    $('body').addClass('cmc-ajaxload');
                    $('*').css('cursor', 'progress');
                }
            },
            {delay: 500, timer: -1, fn: function() {
                    $('body').addClass('cmc-ajaxloading', 300);
                }
            }
        ]
        ,
        waitStopUI: function() {
            $('*').css('cursor', 'default');
            $('body').removeClass('cmc-ajaxload cmc-ajaxloading');
        },
        ajaxSetWaitState: function(wait) {
            if (wait && !ajaxWaiting && ajaxBlocking) {
                var i;
                for (i in this.waitUI) {
                    this.waitUI[i].timer = setTimeout(this.waitUI[i].fn, this.waitUI[i].delay);
                }
                ajaxWaiting = true;
            }
            if (!wait && ajaxWaiting) {
                var i;
                ajaxWaiting = false;
                for (i in this.waitUI) {
                    clearTimeout(this.waitUI[i].timer);
                }
                cmc.waitStopUI();
            }
        },
        framespostdata: function(widgetname, eventname) {
            var v, pdata = {};
            for (f in frames) {
                v = frames[f].post(widgetname, eventname);
                if (v)  // if some data for the frame
                    pdata[f] = v;
            }
            return pdata;
        },
        postdata: function(framename, widgetname, eventname) {
            var pdata = {}, postevent = {};

            if (!frames[framename])
                return false;
            if (frames[framename].busy())
                return false;

            pdata = this.framespostdata(widgetname, eventname);

            postevent.data = pdata;
            postevent.event = {frame: framename, name: eventname, widget: widgetname};
            return postevent;
        },
        //event made for post towards the server
        post: function(framename, widgetname, eventname, options) {
            ajaxData = this.postdata(framename, widgetname, eventname);
            this.dopost(ajaxData, options);
        },
        postpend: function(framename, pendata) {
            ajaxData = this.postdata(framename, null, 'process');
            ajaxData.event.pendata = pendata;
            this.dopost(ajaxData);
        },
        refpost: function(xhr) {
            ajaxR[ajaxR.length] = xhr;
        },
        dopost: function(ajaxData, options) {
            if (!ajaxData)
                return false;

            this.ajaxSetWaitState(true);
            var url = $(location).attr('href');
            this.refpost(jQuery.ajax(jQuery.extend({
                url: url, type: 'POST',
                contentType: 'application/json; charset=UTF-8',
                data: JSON.stringify(ajaxData),
                dataType: 'json',
                success: this.postDone,
                error: this.postError
            }, options)));
        },
        eventpost: function(event) {
            event.preventDefault();
            if (event.data && event.data.name() && event.data.fname()) {
                cmc.post(event.data.fname(), event.data.name(), event.type);
            }
            return false;
        },
        event: function(event, fn) {
            event.preventDefault();
        },
        postCleared: function(jqXHR) {
            var f;
            var i = jQuery.inArray(jqXHR, ajaxR);
            if (i !== -1)
                ajaxR = Array.prototype.splice.call(ajaxR, i + 1, 1);

            for (f in frames)
                frames[f].clearBusy();
        },
        // post result handling
        postDone: function(data, textStatus, jqXHR) {
            // debug result
            //jQuery(item.debug).append(JSON.stringify(data)+'<br>');
            try {
                var f;
                var finish = true;
                // cancelled
                if (jQuery.inArray(jqXHR, ajaxR) === -1)
                    return;

                if (data.data)
                    for (f in frames) {
                        if (data.data[f])
                            frames[f].post_done(data.data[f]);  // validate feedback
                    }
                for (f in frames)
                    frames[f].applyprops();                     // apply internal property values            

                ajaxBlocking = true;
                if (data.process) {
                    for (f in frames) {
                        if (data.process[f]) {
                            if (data.process[f]['pending']) {
                                ajaxBlocking = false;   // in pending no block...
                                cmc.ajaxSetWaitState(false);
                                cmc.postpend(f, data.process[f]['pendingdata']);
                                finish = false;
                            }
                            if (data.process[f]['cancel']) {
                                ajaxR = [];
                            }
                        }
                    }
                }
            }
            finally {
                var l;
                for (l in postdonelisteners)
                    cmc.runListener(postdonelisteners[l], [jqXHR, data]);
                if (finish)
                    cmc.ajaxSetWaitState(false);
                cmc.postCleared(jqXHR);
                if (data.redirect)
                    window.location.replace(data.redirect);
                else if (data.navigate)
                    window.location.href = data.navigate;
            }
        },
        postError: function(jqXHR, textStatus, errorText) {
            if (jQuery.inArray(jqXHR, ajaxR) === -1)
                return;
            cmc.ajaxSetWaitState(false);
            cmc.postCleared(jqXHR);
            var regJS = new RegExp('^[\n ]*{');
            // we have an html error answer    
            if (jqXHR.status === 500 && jqXHR.responseText && !regJS.test(jqXHR.responseText)) {
                document.all[0].innerHTML = jqXHR.responseText;
            }
        },
        addPostDoneListener: function(fn) {
            postdonelisteners.push([fn]);
        },
        addValidationListener: function(frame, fn, delay) {
            if (frames[frame]) {
                frames[frame].addValidationListener(fn, delay);
            }
        },
        addTypingListener: function(frame, fn, delay) {
            if (frames[frame]) {
                frames[frame].addTypingListener(fn, delay);
            }
        },
        msie: function()
        {
            if (_msie === undefined)
            {
                var match = /.*MSIE (\d*)..*/.exec(navigator.userAgent);
                if (match !== null && match[1] !== undefined)
                    _msie = match[1];
                else
                    _msie = null;
            }
            return _msie;
        },
        log: function(fctname, str) {
//            console.log(fctname, str);
        }
    };
}();
