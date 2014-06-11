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
**/
namespace cmc\ui\widgets;

require_once('widget.php');

use cmc\ui\frame, cmc\ui\view, cmc\core\request;
/**
 * factory for link widget, with id/xdpath and optional initial caption
 */
class linkfactory extends widgetfactory {
    const className=__CLASS__;
    static function makewidget(frame $frame, $xpath = '', $initialCaption=null)
    {
        return new link($frame, $xpath, $initialCaption);
    }
}
/**
 * link widget
 *
 * @copyright     Copyright (c) Calmarsoft company (FRANCE) (http://calmarsoft.com)
 * @link          http://cmc.calmarsoft.com CMC Project
 * @license       http://www.gnu.org/licenses/ GNU General Public License version 3
 * @version       0.9
 */
class link extends widget {
    const factory = linkfactory::className;
    
    public function __construct($frame, $xpath = '', $initialCaption=null) {
        parent::__construct($frame, $xpath);
        if ($initialCaption)
            $this->setCaption($initialCaption);
    }
    /**     
     * in initial load, modify href value to reflect the request::rootpath value 
    * @param \cmc\ui\view $view
     */
    public function viewLoaded(view $view) 
    {
      parent::viewLoaded($view);
      $this->modelPropertyDOM('href');
      $href = $this->getProperty('href');
      if (request::rootpath()!='/' && $href && $href[0]=='/')          
          $this->setProperty('href', request::rootpath() . $href);
    }
    /**
     * utility function to happend a value in the href attribute
     * @param type $val
     * @return type
     */
    public function hrefAppend($val)
    {
        if (!isset($val) || strlen($val)==0)
            return; // nothing to do..
        $href = $this->getProperty('href');
        if (strlen($href)>0 && $val[0]!='/')
            $href .= '/';
        $href .= $val;
        $this->setProperty('href', $href);
    }
    /** 
     * shortcut to href attribute
     * @return type
     */
    public function gethRef()
    {
        return $this->getProperty('href');
    }
    /**
     * changes the href attribute value
     * @param type $val
     */
    public function sethref($val)
    {
        $this->setProperty('href', $val);
    }
    
}


