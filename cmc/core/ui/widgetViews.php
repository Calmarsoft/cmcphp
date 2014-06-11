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
namespace cmc\core\ui;

require_once('widgetView.php');
require_once('cmc/core/IClonable.php');
require_once('cmc/core/ISerializable.php');

use cmc\core\IClonable as IClonablep;
use cmc\core\ISerializable as ISerializablep;
use cmc\core\ui\widgetView;

/**
 * Handles the list of widgetView instances for a given widget instance
 *
 * @copyright     Copyright (c) Calmarsoft company (FRANCE) (http://calmarsoft.com)
 * @link          http://cmc.calmarsoft.com CMC Project
 * @license       http://www.gnu.org/licenses/ GNU General Public License version 3
 * @version       0.9
 */
class widgetViews implements IClonablep, ISerializablep {
    private $_wviews = array();
    private $_domElement_path = null;

    private function getItem($idx) {        
        if (!array_key_exists($idx, $this->_wviews)) {
            if (array_key_exists('', $this->_wviews))
               $idx = '';
           else 
               return null;
        }
        return $this->_wviews[$idx];
    }

    /**
     * Initialize a DOM element and returns corresponding widgetView instance
     * 
     * detect if xpath result is differrent => stores it in the array
     * @param cmc\ui\view the actual view
     * @param cmc\ui\frame the actual frame
     * @param string the item search xpath crietria
     * @return widgetView
     */
    public function initDOMElement($view, $frame, $xpath) {        
        $domElem = widgetView::seekDOMElem($view, $frame, $xpath);  
        if (!$domElem)
            return null;
        
        $domElemPath = $domElem->getNodePath();
        $idx = $view->GetName();
        // this will be the default index (first domElementPath encountered)
        if ($this->_domElement_path == null || 
            $this->_domElement_path == $domElemPath) {
            $idx = '';
            $this->_domElement_path = $domElemPath;
        }
        
        if (array_key_exists($idx, $this->_wviews)) {            
            $nview = $this->_wviews[$idx];
            $nview->updateDOMElem($domElem);
            return $nview;               
        }        
        // new view in list
        $this->_wviews[$idx] = new widgetView($domElem);
        return $this->_wviews[$idx];
    }
    /**
     * retrieves the widgetView instance from given view instance
     * @param cmc\ui\view $view
     * @return widgetView
     */
    public function restoreDOMElem($view) {
        $nview = $this->getItem($view->GetName());
        if ($nview) 
            $nview->restoreDOMElem($view);
        return $nview;
    }
    
    /**
     * called when serialized with the app
     */
    public function OnSerialize() {
        foreach($this->_wviews as $item)
            $item->OnSerialize();
    }
    /**
     * called after deserialization
     */ 
    public function OnUnserialize() {
        foreach($this->_wviews as $item)
            $item->OnUnSerialize();
    }
    /**
     * called after having cloned this object
     * @param widgetView $srcinstance
     */
    public function onClone($srcinstance) {
        foreach($srcinstance->_wviews as $name=>$item) {
            $this->_wviews[$name] = clone($item);
            $this->_wviews[$name]->onClone($item);
        }
    }
}

