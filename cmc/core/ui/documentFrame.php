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

use cmc\ui\frame,
    cmc\core\request,
    cmc\config;

/**
 * default document frame
 * 
 * used for global updates on the view:
 *  - updating internal links with appropriate language, and fix relative paths
 *
 * @copyright     Copyright (c) Calmarsoft company (FRANCE) (http://calmarsoft.com)
 * @link          http://cmc.calmarsoft.com CMC Project
 * @license       http://www.gnu.org/licenses/ GNU General Public License version 3
 * @version       0.9
 */
class documentFrame extends frame {

    const className = __CLASS__;

    static $matpath_len = -1;
    static $preg_ext = null;
    static $matrepaste = null;

    /**
     * this frame as no id
     */
    static public function getId() {
        return '';
    }

    /**
     * frame name is 'document'
     */
    public function getName() {
        return 'document';
    }

    /**
     * performs static link reshape
     */
    public function viewInitialUpdate($view) {
        $app = $view->getApp();
        $sess = $app->getSession();
        $req = $sess->getRequest();
        //$locview = $view->getLogicPath() !== $view->getName();
        // links "repairs"
        $list = $view->getSectionDOMElements($this->getId(), '//*/img[@src]
            |//*/img[@delaysrc]
            |//*/script[@src]
            |//*/source[@src]
            |//*/link[@href]
            |//*/a[@href]');
        foreach ($list as $l) {
            if ($l->nodeName == 'link' || $l->nodeName == 'a')
                $pn = 'href';
            else
                $pn = 'src';
            $pv = $l->getAttribute($pn);
            if ($pv=='' && $l->nodeName=='img') {
                $pn = 'delaysrc';
                $pv = $l->getAttribute($pn);
            }

            if ($l->nodeName == 'a') {  // for links, just add facility to remove the extension
                if (self::$preg_ext == null)
                    self::$preg_ext = '![.]*' . config::VIEW_EXT . '$!';
                $furl = preg_replace(self::$preg_ext, '', $pv, 1, $count);
                if ($count == 0 && preg_match('![.]+.[\w]+!', $pv))
                    $furl = $req->relocate_path($pv, $view->getName());
            } else    // complete fix for others.
                $furl = $req->relocate_path($pv, $view->getName());

            if ($furl !== false)
                $l->setAttribute($pn, $furl);
        }


        // all <a>href! -> update language and root path...
        $list = $view->getSectionDOMElements($this->getId(), '//*/a[@href]');
        foreach ($list as $l) {
            $href = $l->getAttribute('href');

            if ($href && $href != '#' && strpos($href, '//') !== 0 && strpos($href, 'http') !== 0) {   // if not prefixed by a language, add it                
                if (request::rootpath() == '/' || substr($href, 0, strlen(request::rootpath())) != request::rootpath()) {
                    
                    if (config::Multilingual) {
                        $regex = $app->getLanguageUrlRegex();
                        if (!preg_match($regex, $href, $match)) {
                            if ($href[0]==='/') $href = substr($href, 1);
                            $l->setAttribute('href', request::rootpath() . config::buildUrlLang($sess->getLangName(), $href));
                        }
                    } else {
                        $l->setAttribute('href', '/'. request::rootpath_short() . $href);
                    }
                }
            }
        }
        $body = $view->getDOMElement('//body');
        if ($body) {    // this snipset is for hourglass during ajax Request
            $sess = \cmc\sess();            
            if ($sess && $sess->isEnabled()) {
                $ajaxGif = request::rootpath() . config::getAjaxImagePath();
                $html = <<<EOT
<div class="cmc-ajaxwait"></div><style>.cmc-ajaxwait{display:none;position:fixed;z-index:1000;top:0;left:0;height:100%;width:100%;background:url('$ajaxGif') 50% 50% no-repeat}body.cmc-ajaxloading .cmc-ajaxwait{background-color:rgba(0,0,0,0.1);display:block;transition:all .5s ease}body.cmc-ajaxload{overflow:hidden}body.cmc-ajaxload .cmc-ajaxwait{display:block}</style>
EOT
                ;
            } else
                $html = '';
            $req = \cmc\sess()->getRequest();
            $ban = config::PoweredBy_Banner($req->getPath(\cmc\app()));
            if ($ban)
                $html .= $ban;
            if ($html != '') {
                $extra = material::getCloneFromSource($html, $view->material()->document());
                if ($extra) {
                    foreach ($extra as $elem) {
                        $body->appendChild($elem);
                    }
                }
            }
        }
    }

}

/*
        bottom: 5px;
    margin-top: 20px;
    position: fixed;
    right: 30px;
*/