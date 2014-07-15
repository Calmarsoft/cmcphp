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

require_once('compositelist.php');


use cmc\ui\frame, cmc\ui\view, cmc\ui\dynview;

/**
 * factory for select widget, the list widget
 */
class selectfactory extends widgetfactory {

    const className = __CLASS__;

    static function makewidget(frame $frame, $xpath = '', $initialval = null) {
        return new select($frame, $xpath);
    }

}

/**
 * component for 'list' kind objects
 *
 * @copyright     Copyright (c) Calmarsoft company (FRANCE) (http://calmarsoft.com)
 * @link          http://cmc.calmarsoft.com CMC Project
 * @license       http://www.gnu.org/licenses/ GNU General Public License version 3
 * @version       0.9
 */
class select extends compositelist {
    const factory = selectfactory::className;
    private $_fieldNames;
    private $_list_ser;
    
    public function __construct($frame, $xpath = '') {
        parent::__construct($frame, $xpath);   
        $this->_bDynamic = true;
        $this->_list_ser = null;
    }
    
    public function setListData($data) {
        if ($data==null || $data==false)
            return;
        if (is_array($data)) {
            if (count($data)<=0)
                return;
            $keys = array_keys($data[0]);
            $rows = $data;
        } else if (is_a($data, '\Iterator')){
            if ($data->rewind()) {
                $row = array_slice($data->getRowCopy(), 0, 2);                  
                $keys = array_keys($row);
            } else
                return;
            $keys = array($keys[0], $keys[1]);
            $rows = array();
            while ($row) {
                array_push($rows, $row);
                if ($data->next())
                    $row = array_slice($data->getRowCopy(), 0, 2);                
                else 
                    $row = null;
            }
        } else 
            return;
        
        if (count($keys)<2)
            return;
        $this->_fieldNames = $keys;
        $this->setCompositeMap(array('@value'=>$keys[0], 'text()'=>$keys[1]));
        $this->setCompData($rows);
    }
   
    public function viewInitialUpdate(view $view, frame $frame) {
        if (!$this->bOption(self::OPT_INPUT_LEAVE_AUTOCOMPLETE))
            $this->DOMUpdateAttr('autocomplete', 'off');
        parent::viewInitialUpdate($view, $frame);
    }
    
   /**
     * puts the 'select' attribute on the correct row
     * @param array $data   the data row
     * @param \DOMElement $domItem   the DOM row
     */
    protected function OncompositeRowDOM($data, $domItem) {
        $sel = $this->getValue();    
        if ($data[$this->_fieldNames[0]]==$sel)
            $domItem->setAttribute ('selected', '');
    }
    
    protected function applyPropertyDOM($view, $propname, $propval) {
        if ($propname=='value')  // mark as valid
            return true;
        
        return parent::applyPropertyDOM($view, $propname, $propval);
    }
    
    /** 
     * keeps list data
     */
    public function OnSerialize() {
        if (array_key_exists(self::prop_Data, $this->_properties)
         && $this->_properties[self::prop_Data] !== null) {
            $this->_list_ser = array();
            foreach ($this->_properties[self::prop_Data] as $row) {
                array_push($this->_list_ser, $row);
            }
        }
        parent::OnSerialize();
    }

    public function OnUnSerialize() {
        if ($this->_list_ser!==null)
            $this->_properties[self::prop_Data] = $this->_list_ser;
        $this->_list_ser = null;
        parent::OnUnserialize();
    }

}