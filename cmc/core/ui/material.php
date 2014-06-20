<?php

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
 * */

namespace cmc\core\ui;

use cmc\config;
use cmc\core\ISerializable,
    cmc\core\IClonable,
    cmc\core\request;

libxml_use_internal_errors(true);

/**
 * Used to build the actual view by loading html parts from files or database
 *
 * @copyright     Copyright (c) Calmarsoft company (FRANCE) (http://calmarsoft.com)
 * @link          http://cmc.calmarsoft.com CMC Project
 * @license       http://www.gnu.org/licenses/ GNU General Public License version 3
 * @version       0.9
 */
class material implements IClonable, ISerializable {

    const delayJSObj='d';
            
    /** used options for the DOM functions 
     * @internal */
    const DOMOptions = \LIBXML_COMPACT;

    /**
     *  the UTF8 BOM, needed for implementation
     *  @internal 
     */
    const utf8bom = "\357\273\277";

    private $_document;         // the whole DOM in which the frame is
    private $_txtdoc;           // HTML form
    private $_docxpath;
    private $_scriptElem, $_scriptElem_ser;
    private $_mtime;
    private $_physpath;
    private $_bDynamic = false;
    private $_jsItems;
    private $_position_dyn = null;
    private $_customscriptcode = '', $_customscriptcode_bottom = '';
    private $_customscriptcode_dyn = '', $_customscriptcode_bottom_dyn = '';

    /**
     * 
     * find the location of the material, from the generic path
     * load priority:<br>
     * 1/ localized (using $lang)<br>
     * 2/ embedded (if client locale is equal to $impl_lang)<br>
     * 3/ default     
     * 
     * @param string the language or empty string
     * @param string the material path
     * @param string the 'implemented' language: the language of the default materials
     * @return boolean|string the effective absolute path of the material
     */
    public static function seekMaterialFile($lang, $path, $impl_lang = '') {

        $matpath = config::MAT_path;
        $viewExt = config::VIEW_EXT;

        if (!config::MAT_valid($path))
            return false;

        // 1/ localized material
        $loc_path = realpath($matpath . $lang . '/' . $path . $viewExt);
        // 2/ embedded
        if (!file_exists($loc_path) && $lang != $impl_lang)
            $loc_path = realpath($matpath . $path . $viewExt);
        if (!file_exists($loc_path))
            return false;

        return $loc_path;
    }

    /**
     * This function is used to parse an HTML sample and generate a DOM object linked to provided document, if specified
     * @param string input html sample (doesn't need to have the <html> tag). UTF-8 encoding expected.
     * @param DOMDocument|null target document, or null
     * @return boolean|array the new DOM node
     */
    public static function getCloneFromSource($html, $tgtdoc) {
        if ($html === '') {
            return false;
        }
        // turnaround the unwanted extra '<p>' if text without body
        if (!preg_match('|<body>|', $html)) //TODO: find better regex
            $html = '<body>' . $html;

        $doc = new \DOMDocument('1.0', 'utf-8');
        if ($doc->loadHTML(self::utf8bom . $html)) {
            $elem = $doc->documentElement;
            if ($elem && $elem->tagName === 'html' && $elem->hasChildNodes())
                $elem = $elem->childNodes->item(0);
            if ($elem && $elem->tagName === 'body' && $elem->hasChildNodes())
                $nodelist = $elem->childNodes;
            else
                return false;
            $nodes = array();
            foreach ($nodelist as $elem) {
                if ($tgtdoc)
                    $node = $tgtdoc->importNode($elem, true);
                else
                    $node = $elem->cloneNode(true);
                array_push($nodes, $node);
            }
            return $nodes;
        }
        return false;
    }

    /**
     * returns the list of Nodes for given classname
     * @param string classname
     * @return DOMNodeList
     */
    public function findClassNodes($class) {
        $this->invalidate();
        if (!isset($this->_docxpath))
            $this->_docxpath = new \DOMXpath($this->_document);

        $qry = '//*[contains(concat(\' \', @class, \' \'), \' ' . $class . ' \')]';

        return $this->_docxpath->query($qry, $this->_document);
    }

    /**
     * returns the list of Nodes which have the given attribute name
     * @param string attribute name
     * @return DOMNodeList
     */
    public function findAttributeNodes($attr) {
        $this->invalidate();
        if (!isset($this->_docxpath))
            $this->_docxpath = new \DOMXpath($this->_document);

        $qry = '//*[@' . $attr . ']';

        return $this->_docxpath->query($qry, $this->_document);
    }

    /**
     * returns the list of Nodes which have an attribute with specific value
     * @param string attribute name
     * @param string attribute value
     * @return DOMNodeList
     */
    public function findAttributeValueNodes($attr, $value) {
        $this->invalidate();
        if (!isset($this->_docxpath))
            $this->_docxpath = new \DOMXpath($this->_document);

        $qry = '//*[@' . $attr . '=\'' . $value . '\']';

        return $this->_docxpath->query($qry, $this->_document);
    }

    /**
     * returns a node for the given Xpath value
     * @param DOMNode|null the parent from which begin the search
     * @param string the xpath value
     * @return DOMNode|boolean
     */
    public function findXpathNode($parentNode, $xpath) {
        //if ($_SERVER['REQUEST_METHOD']!='POST') {echo "findxpathnode $xpath";var_dump($this);}
        if ($parentNode == null)
            $parentNode = $this->_document;
        $nodes = $this->findXpathNodes($parentNode, $xpath);
        if ($nodes && $nodes->length == 1)
            return $nodes->item(0);
        return false;
    }

    /**
     * returns the nodes for the given Xpath value
     * @param DOMNode|null the parent from which begin the search
     * @param string the xpath value
     * @return DOMNodeList|boolean
     */
    public function findXpathNodes($parentNode, $xpath) {
        $this->invalidate();

        if (!isset($this->_docxpath))
            $this->_docxpath = new \DOMXpath($this->_document);
        /* echo "DDA:";var_dump($this->_docxpath);var_dump($this->_document);
          "P:";var_dump($parentNode->ownerDocument);xdebug_print_function_stack();
          echo "$xpath F"; */
        $nodes = $this->_docxpath->query($xpath, $parentNode);
        return $nodes;
    }

    /**
     * returns false or the first node with the cmc-parent attribute
     * @return DOMNode|boolean
     */
    public function findCMCParentNode() {
        $this->invalidate();
        $nodes = $this->findAttributeNodes(config::VW_APARENT);
        if ($nodes && $nodes->length > 0) {
            assert($nodes->length == 1);
            return $nodes->item(0);
        }
        return false;
    }

    /**
     * finds an attribute in a node, and removes it
     * @param DOMNode the input node
     * @param string the attribute name
     * @return NodeAttr the found attribute
     */
    public function peekAttribute($node, $attrName) {
        $this->invalidate();
        $value = $node->getAttribute($attrName);
        $node->removeAttribute($attrName);
        return $value;
    }

    /**
     * updates the given node with a new value, from an external document
     * @param DOMNode $position
     * @param DOMNode $externalNode
     */
    public function replace($position, $externalNode, $bMergeAttr = false) {
        $this->invalidate();
        $nodeCopy = $this->_document->importNode($externalNode, true);
        if ($bMergeAttr) {
            foreach ($position->attributes as $attr) {
                $nodeCopy->setAttributeNode($attr);
            }
        }
        $position->parentNode->replaceChild($nodeCopy, $position);
    }

    /**
     * 
     * @internal
     *  moves all script for given xpath to html/body/div[id=destid] */
    private function moveScript($xpath, $destid) {
        $code = '';
        $scripts = $this->_docxpath->query($xpath, $this->_document);
        $body = $this->_docxpath->query('/html/body', $this->_document);
        if ($scripts && $body && $body->length > 0) {
            $latescript = $this->_document->createElement('div');
            $latescript->setAttribute('id', $destid);
            $body->item(0)->appendChild($latescript);
            // iterate all <script> of text/javascript or unspecified, except with config::MAT_scriptignclass class
            foreach ($scripts as $script) {
                $scriptclass = $script->getAttribute('class');
                if ($destid == config::MAT_scriptend || !preg_match('/^.*\b' . config::MAT_scriptclass . '\b.*$/', $scriptclass)) {
                    if (!preg_match('/^.*\b' . config::MAT_scriptignclass . '\b.*$/', $scriptclass)) {
                        if (!$script->hasAttribute('type') ||
                                $script->getAttribute('type') == 'text/javascript') {
                            if (!$this->_jsItems)
                                $this->_jsItems = array();
                            $info = array();
                            if ($script->hasAttribute('src')) {
                                $info['src'] = $script->getAttribute('src');
                            } else if ($script->firstChild) {
                                $info['code'] = $script->firstChild->data;
                            }
                            if ($script->hasAttribute('data-id'))
                                $info['id'] = $script->getAttribute('data-id');
                            if ($script->hasAttribute('data-need'))
                                $info['need'] = $script->getAttribute('data-need');

                            array_push($this->_jsItems, $info);
                            $script->parentNode->removeChild($script);
                        }
                    } else {
                        $scriptclass = trim(preg_replace('/^(.*)\b' . config::MAT_scriptignclass . '\b(.*)$/', '\1 \2', $scriptclass));

                        if ($scriptclass == '')
                            $script->removeAttribute('class');
                        else
                            $script->setAttribute('class', $scriptclass);
                    }

                    unset($script);
                }
            }

            if ($code != '') {
                if ($destid != config::MAT_scriptbegin) {
                    $n = $this->_document->createElement('script');
                    $n->appendChild($this->_document->createTextNode($code));
                    $n->setAttribute('type', 'text/javascript');

                    $latescript->appendChild($n);
                }
            }
            //var_dump('put se1');$e = new \Exception();echo '<pre>'.$e->getTraceAsString()."\n".$n->C14N().'</pre>';
        }
    }

    /**
     * @internal
     */
    public function moveScriptCodes() {
        $this->moveScript('/html/head/script', config::MAT_scriptbegin);
        $this->moveScript('//*/script[@class=\'' . config::MAT_scriptclass . '\']', config::MAT_scriptend);
    }

    /**
     * tells that this material is 'dynamic', which means that content is not identical for a given query path
     */
    public function setDynamic() {
        $this->_bDynamic = true;
    }

    /**
     * used by widgets: adds some initialization script code
     * @param string $scriptCode
     */
    public function addScriptCode($scriptCode) {
        if (!isset($scriptCode) || strlen($scriptCode) == 0)
            return;
        //echo "<pre>$scriptCode</pre>";//xdebug_print_function_stack();
        if ($this->_bDynamic)
            $this->_customscriptcode_dyn .= $scriptCode;
        else
            $this->_customscriptcode .= $scriptCode;
    }

    /**
     * used by widgets: adds some late initialization script code
     * @param string $scriptCode
     */
    public function addBottomScriptCode($scriptcode) {
        if ($this->_bDynamic)
            $this->_customscriptcode_bottom_dyn .= $scriptcode;
        else
            $this->_customscriptcode_bottom .= $scriptcode;
    }

    private function fixUrlJs($req, $viewname, $js, &$jsid=false, &$jsneed=false) {
        $httpi = preg_match('!^//.*!', $js);
        $http = preg_match('!^http(s)|()://.*!', $js);
        if ($httpi || $http) {
            if ($jsid!==false)
            {
                if (preg_match('!^((http(s|)://)|(//))ajax.googleapis.com/ajax/libs/jquery/[0-9]+.[0-9]+.[0-9]+/jquery.(min.|)js$!', $js)) {
                    $jsid = 'jquery';
                }
                if (preg_match('!^((http(s|)://)|(//))code.jquery.com/ui/[0-9]+.[0-9]+.[0-9]+/jquery-ui.(min.|)js$!', $js)) {
                    $jsid = 'jquery-ui';
                    $jsneed = 'jquery';
                }
            }
            if (\cmc\config::jQueryFixVersion) {
                $jq = array();
                // 1.11.0 for IE < 9, 2.1.0
                // url form: https://ajax.googleapis.com/ajax/libs/jquery/1.11.0/jquery.js
                if (preg_match('!^((?:http(?:(?:s)|(?:))://)|(?://))(ajax.googleapis.com/ajax/libs/jquery/)([0-9]+.[0-9]+.[0-9]+)/jquery.(?:(?:(min).)|(?:))js$!', $js, $jq)) {
                    // 1: prefix (https://, http://, //)
                    // 2: ajax.googleapis.com/ajax/libs/jquery/
                    // 3: version
                    // 4: min or absent
                    $min = '';
                    $pre = $jq[1];
                    if ($pre !== '//' && \cmc\config::FixHtml_httplinks)
                        $pre = '//';
                    if (count($jq) == 5)
                        $min = $jq[4] . '.';
                    if (\cmc\config::jQueryAutoMinify)
                        $min='min.';
                    $mozv = request::getClientMozVersion();
                    if ($mozv && $mozv <= 4.0 && (\cmc\config::jQueryAutoLatest || substr($jq[3], 0, 2) == '2.')) {
                        $js = $pre . $jq[2] .  \cmc\config::jQueryLegacyLatest . '/jquery.' . $min . 'js';
                    } else if ($mozv && $mozv >= 5.0 && (\cmc\config::jQueryAutoLatest || substr($jq[3], 0, 2) == '1.')) {
                        $js = $pre . $jq[2] . \cmc\config::jQueryLatest . '/jquery.' . $min . 'js';
                    } else {
                        $js = $pre . $jq[2] . $jq[3] . '/jquery.' . $min . 'js';
                    }
                    return $js;
                }
            }
        }
        else
        {
            if ($jsid!==false)
                // a local script is defaulted to jquery-ui dependent
                $jsneed = 'jquery-ui';
        }
        $jsf = $req->relocate_path($js, $viewname);
        if ($jsf)
            return $jsf;
        return $js;
    }

    private function prepDelayedJSInfo($view) {
        $app = $view->getApp();
        $sess = $app->getSession();
        $req = $sess->getRequest();

        $result = '';
        $idx = 0;
        $curidx = 0;
        $namedidx = array(); //$depidx=array();
        foreach ($this->_jsItems as $jsItem) {
            $js = '';$need = null; $jsid = null;
            if (array_key_exists('src', $jsItem)) {
                $js = '{s:\'' . $this->fixUrlJs($req, $view->getName(), $jsItem['src'], $jsid, $need) . '\'';
            } else {
                $js = '{f: '.self::delayJSObj.'.init' . $idx;
            }
 
            if (array_key_exists('need', $jsItem))
                    $need = $jsItem['need'];
            if (array_key_exists('id', $jsItem))
                    $jsid = $jsItem['id'];
            
            if ($need && array_key_exists($need, $namedidx))
                $curidx = $namedidx[$need]+1;
            if ($jsid)
                $namedidx[$jsid] = $curidx;
            //$depidx[$idx] = $curidx;

            if ($result != '')
                $result.=',';
            $result .= $js . ', d:' . $curidx . '}';
            $idx ++;
        }
        return $result;
    }

    public function prepDelayedJSCode($view) {
        $jsDelayInfo = $this->prepDelayedJSInfo($view);

        $jsDelayCode = <<<EOT
(function(){var d={};function a(){var c=[$jsDelayInfo];function f(g,i){var h=document.createElement(i);h.src=c[g].s;document.body.appendChild(h);return h}function e(h){if(c[h]["f"]){c[h].l=true;c[h].f.apply();return}var j=f(h,"script");var i=false;if(j.async!==undefined){j.async="true"}j.type="text/javascript";var g=function(){var k=0;if(i&&j.readyState==="loading"){return}c[h].l=true;for(b in c){if(c[b].d<=c[h].d&&c[b]["l"]!==true){return}}for(b in c){if(c[b].d==c[h].d+1){e(b);k++}}if(k==0){d.init()}};if(j.addEventListener){j.addEventListener("load",g,true)}else{if(j.readyState){j.onreadystatechange=g;i=true}}}var b;for(b in c){if(c[b]["d"]===0){e(b)}else{if(c[b]["s"]){f(b,"img").height=0}}}}if(window.addEventListener){window.addEventListener("load",a,false)}else{if(window.attachEvent){window.attachEvent("onload",a)}else{window.onload=a}}
EOT
        ;
/*
    var c = {};
    //function log(x) {
    //    console.log(x);
    //}
    function b() {
        var h = [{s: '//ajax.googleapis.com/ajax/libs/jquery/1.11.1/jquery.min.js', d: 0},
            {s: '/calmar3/js/javascript-xpath.min.js', d: 0},
            {s: '//code.jquery.com/ui/1.10.3/jquery-ui.js', d: 1},
            {s: '/calmar3/js/cmc.js', d: 0},
            {s: '/calmar3/js/app.js', d: 2},
            {f: c.init5, d: 0}];

        function obj(k, n) {
            var d = document.createElement(n);
            d.src = h[k].s;
            document.body.appendChild(d);
            return d;
        }
        
        function g(k) {
            if (h[k]['f']) {
                h[k].l = true;
                //log(k+'/'+h[k].d+': function');
                h[k].f.apply();
                return;
            }
            
            var d = obj(k, 'script');            
            var ie=false;
            if (d['async'] !== undefined)
                d.async = 'true';
            d.type = 'text/javascript';
            var cb = function() {
                var cnt=0;
                //log(k+'/'+h[k].d+': '+h[k].s);
                if (ie && d.readyState!=='l')
                    return;
                h[k].l = true;
                
                
                for (i in h)
                       if (h[i].d <= h[k].d && h[i]['l'] !== true)
                           return;
                   
                for (i in h) {
                       if (h[i].d == h[k].d+1 ) {
                            g(i);
                            cnt++;
                        }
                }
                
                if (cnt==0)
                    c.init();
            }
            if (d.addEventListener) {
                d.addEventListener('load', cb, true);
            }
            else if (d.readyState) {
                d.onreadystatechange = cb;
                ie = true;
            }
        }

        var i;
        for (i in h) {
            if (h[i]['d']===0)
                g(i);
            else
                if (h[i]['s'])
                    obj(i, 'img').height = 0;
        }
    }
    if (window.addEventListener) {
        window.addEventListener('load', b, false)
    } else {
        if (window.attachEvent) {
            window.attachEvent('onload', b)
        } else
            window.onload = b;
    }
 
 * degenerated part:
    cmcpage.init5 = function() {
        var _gaq = _gaq || [];
        _gaq.push(["_setAccount", "UA-47124146-1"]);
        _gaq.push(["_trackPageview"]);
        (function() {
            var c = document.createElement("script");
            c.type = "text/javascript";
            c.async = true;
            c.src = ("https:" == document.location.protocol ? "https://" : "http://") + "stats.g.doubleclick.net/dc.js";
            var d = document.getElementsByTagName("script")[0];
            d.parentNode.insertBefore(c, d)
        })();
    };
    cmcpage.init = function() {
        $(function() {
            cmc.n('model', 'menu', '#menu', 'menu').option("menu", "option", "position", {my: "left top", at: "left+3 bottom+3"});
            cmc.n('lang', 'lbl_lang', '#lbl_lang', 'label');
            cmc.n('lang', 'lang_fr', '#lang_fr', 'link');
            cmc.n('lang', 'lang_en', '#lang_en', 'link');
            cmc.n('lang', 'lang_de', '#lang_de', 'link');
        });
    }
})();
 */        
//(function(){var e={};function d(b){console.log(b)}function a(){var c=[$jsDelayInfo];function j(g,i){var h=document.createElement(i);h.src=c[g].s;document.body.appendChild(h);return h}function f(h){if(c[h]["f"]){c[h].l=true;d(h+"/"+c[h].d+": function");c[h].f.apply();return}var l=j(h,"script");var i=false;if(l.async!==undefined){l.async="true"}l.type="text/javascript";var g=function(){var k=0;d(h+"/"+c[h].d+": "+c[h].s);if(i&&l.readyState!=="l"){return}c[h].l=true;for(b in c){if(c[b].d<=c[h].d&&c[b]["l"]!==true){return}}for(b in c){if(c[b].d==c[h].d+1){f(b);k++}}if(k==0){e.init()}};if(l.addEventListener){l.addEventListener("load",g,true)}else{if(l.readyState){l.onreadystatechange=g;i=true}}}var b;for(b in c){if(c[b]["d"]===0){f(b)}else{if(c[b]["s"]){j(b,"img").height=0}}}}if(window.addEventListener){window.addEventListener("load",a,false)}else{if(window.attachEvent){window.attachEvent("onload",a)}else{window.onload=a}}
        
        $idx = 0;
        foreach ($this->_jsItems as $jsItem) {
            if (array_key_exists('code', $jsItem)) {
                $jsDelayCode.= self::delayJSObj . '.init' . $idx . '=function(){' . $jsItem['code'] . '};';
            }
            $idx++;
        }

        return $jsDelayCode;
    }

    private function getScriptPosition() {
        $position = $this->_docxpath->query('/html/body/div[@id=\'' . config::MAT_scriptbegin . '\']', $this->_document);
        
        if (!$position || $position->length <= 0)
            $position = $this->_docxpath->query('/html/head', $this->_document);
          
        return $position;
    }

    /**
     * commits the script code insertion
     * @see cmc\ui\view::framesDefInit()
     * @see cmc\ui\dynview::viewUpdate()
     */
    public function endStaticScriptCode($view) {
        /* if (!\cmc\qry()->isPost()) {
          echo 'ess';
          var_dump($this->_customscriptcode, $this->_customscriptcode_bottom,
          $this->_customscriptcode_dyn , $this->_customscriptcode_bottom_dyn, $this->_dftMainCode);
          } */
        $jsOriginalCode = '';
        $position = null;

        if ($this->_bDynamic && $this->_jsItems) {
            if (config::Javascript_DelayLoad)
                $jsOriginalCode = $this->prepDelayedJSCode($view);
            else {
                $position_root = $this->getScriptPosition();
                if ($position_root->length>0) {
                    $position = $position_root->item(0);
                    $len = $position->childNodes->length;
                    if ($len>0) {
                        $position->removeChild($position->childNodes->item($len-1));
                    }
                }

                $app = $view->getApp();
                $sess = $app->getSession();
                $req = $sess->getRequest();                
                foreach ($this->_jsItems as $jsItem) {
                    if (array_key_exists('code', $jsItem)) {                           
                        $jsOriginalCode.=$jsItem['code'];
                    }
                    if (array_key_exists('src', $jsItem)) {
                            $el = $this->_document->createElement('script');
                            $el->setAttribute('type', 'text/javascript');
                            $el->setAttribute('src', $this->fixUrlJs($req, $view->getName(), $jsItem['src']));
                            $position->appendChild($el);                        
                    }
                }
            }
        }

        if ($position == null && $this->_customscriptcode == '' && $this->_customscriptcode_bottom == '' &&
                $this->_customscriptcode_dyn == '' && $this->_customscriptcode_bottom_dyn == '' &&
                $jsOriginalCode == '')
            return;

        if ($this->_scriptElem_ser)
            $this->_scriptElem = $this->findXpathNode(null, $this->_scriptElem_ser);
        $this->_scriptElem_ser = null;

        if (!$this->_scriptElem) {
            if (!$position) {
                $position_root = $this->getScriptPosition();
                if ($position_root && $position_root->length > 0)
                    $position = $position_root->item(0);
            }

            if ($position) {
                $this->_scriptElem = $this->_document->createElement('script');
                $this->_scriptElem->setAttribute('type', 'text/javascript');
                $this->_scriptElem->setAttribute('id', config::MAT_scriptcodeid);
                //var_dump('put se2');$e = new \Exception();echo '<pre>'.$e->getTraceAsString().'</pre>';
                $position->appendChild($this->_scriptElem);
            }
        }

        if ($this->_scriptElem) {
            $in_code = $this->_customscriptcode . $this->_customscriptcode_dyn .
                        $this->_customscriptcode_bottom . $this->_customscriptcode_bottom_dyn;
            if ($jsOriginalCode!== '' || $in_code!== '') {
                $l = '';
                if ($this->_position_dyn !== null) {
                    $l = \substr($this->_scriptElem->nodeValue, 0, $this->_position_dyn);
                }
                if (config::Javascript_DelayLoad && $l==='' && $jsOriginalCode!=='') {
                        $val = $jsOriginalCode .
                            self::delayJSObj.'.init=function(){ $(function() {' . $in_code  .
                            '});} })();';
                } else {
                        $val = $l . '$(function() {' . $jsOriginalCode. $in_code .
                            '});';
                }
                $this->_scriptElem->nodeValue = $val;
                if (!$this->_position_dyn && $jsOriginalCode!=='')
                    $this->_position_dyn = strlen($val);
            }
            $this->_txtdoc = null;
        }
    }

    /**
     * gets direct access to the DOM document
     * @return DOMDocument
     */
    public function document() {
        $this->invalidate();
        return $this->_document;
    }

    /**
     * get the actual HTML code
     * @return string|null
     */
    public function getHTML() {
        if ($this->_txtdoc == null && $this->_document) {
            $this->_document->preserveWhiteSpace = !config::RenderHtml_Trim;
            $this->_document->formatOutput = config::RenderHtml_Format;
            $this->_txtdoc = $this->_document->saveHTML();
        }
        if ($this->_txtdoc)
            return $this->_txtdoc;
        else
            return null;
    }

    /**
     * invalidates the cached html code
     */
    public function invalidate() {
        if (!$this->_document) {
            xdebug_print_function_stack();
        }
        $this->_txtdoc = null;
    }

    /**
     * checks if html text can be extracted from this object
     * @return boolean
     */
    public function ValidForSave() {
        return ($this->_txtdoc != null || $this->_document != null);
    }

    /**
     * cleanup recursive subroutine
     * @param DOMNode $node
     * @param integer level
     */
    private function _cleanupDOM($node, $l) {
        $to_remove = array();
        foreach ($node->childNodes as $child) {
            if ($child->nodeType == \XML_COMMENT_NODE) {
                if ($child->nodeValue[0] != '[')
                    array_push($to_remove, $child);
            }
            else if ($child->nodeType == \XML_ELEMENT_NODE)
                $this->_cleanupDOM($child, $l + 1);
        }
        foreach ($to_remove as $child)
            $node->removeChild($child);
    }

    /**
     * cleanups the document by removing comment tags
     */
    public function cleanupDOM() {
        $this->_cleanupDOM($this->_document, 1);
        $h = $this->_docxpath->query('/html/head');
        if ($h && $h->length > 0) {
            $ban = $this->_document->createElement('meta');
            $ban->setAttribute('name', 'Generator');
            $ban->setAttribute('content', 'CMC CalmarSoft components v0.9 - http://www.calmarsoft.com');
            $m = $this->_docxpath->query('/html/head/link');
            if ($m && $m->length > 0)
                $h->item(0)->insertBefore($ban, $m->item(0));
            else
                $h->item(0)->appendChild($ban);
        }
    }

    /**
     * loads the material from file path, and creates a material object if success
     * @param
     * @param string the language or empty string
     * @param string the material path
     * @param string the 'implemented' language: the language of the default materials	 
     */
    public static function load($lang, $path, $impl_lang = '') {
        $docread = false;
        //if ($_SERVER['REQUEST_METHOD']!='POST') echo "load $path<br>";
        // test in file mode
        $physpath = self::seekMaterialFile($lang, $path, $impl_lang);
        if ($physpath) {
            $document = new \DOMDocument('1.0', 'utf-8');

            if (config::checkUtf8BOM) {
                $handle = fopen($physpath, "r");
                if (!$handle)
                    return false;
                $head = fread($handle, 3);

                if ($head === self::utf8bom) {
                    $docread = $document->loadHTMLFile($physpath, self::DOMOptions);
                } else {
                    $data = self::utf8bom . $head . fread($handle, filesize($physpath) - 3);
                    $docread = $document->loadHTML($data);
                    unset($data);
                }
                fclose($handle);
            } else
                $docread = $document->loadHTMLFile($physpath, self::DOMOptions);

            if ($docread) {
                $errs = false;
                if (config::cmcStrictHTML) {
                    $errs = libxml_get_last_error();
                    libxml_clear_errors();
                }
                if (!$errs) {
                    $new_material = new material($document, $physpath);
                    $mdt = filemtime($physpath);
                    $new_material->setmtime($mdt);
                    //if ($_SERVER['REQUEST_METHOD']!='POST') {echo "load $physpath";var_dump($new_material);}
                    return $new_material;
                }
            }

            unset($document);
        }
        return false;
    }

    public function __construct($doc, $physpath) {
        $this->_document = $doc;
        $this->_physpath = $physpath;
        $this->_txtdoc = null;
        $this->invalidate();
    }

    /**
     * sets the modification time
     */
    private function setmtime($time) {
        $this->_mtime = $time;
    }

    /**
     * gets the modification time
     */
    public function getmtime() {
        return $this->_mtime;
    }

    /**
     * gets the physical path
     */
    public function getphyspath() {
        return $this->_physpath;
    }

    /**
     * called before serialization. Need to convert the DOM in text because DOMDocument is not serializable
     */
    public function OnSerialize() {
        if (!isset($this->_txtdoc))
            $this->_txtdoc = $this->getHTML();
        $this->_document = null;
        $this->_docxpath = null;
        $this->_customscriptcode_bottom_dyn = '';
        $this->_customscriptcode_dyn = '';

        if ($this->_scriptElem) {
            $this->_scriptElem_ser = $this->_scriptElem->getNodePath();
            if ($this->_bDynamic)
                $this->_jsItems = null;
        }

        $this->_scriptElem = null;
    }

    /**
     * called after deserialization
     */
    public function OnUnserialize() {
        if ($this->_txtdoc) {
            $this->_document = new \DOMDocument('1.0', 'utf-8');
            $this->_document->loadHTML($this->_txtdoc);
            $this->_docxpath = new \DOMXpath($this->_document);
        }
    }

    /**
     * called after cloning the object
     */
    public function OnClone($srcinstance) {

        $this->_document = null;
        $this->_docxpath = null;
        $this->_scriptElem = null;
        if ($srcinstance->_scriptElem)
            $this->_scriptElem_ser = $srcinstance->_scriptElem->getNodePath();
        // new html for target
        if ($srcinstance->_document) {
            $this->_txtdoc = $srcinstance->getHTML();
        }
        $this->OnUnserialize();
    }

}
