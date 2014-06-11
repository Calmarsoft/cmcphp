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

require_once('input.php');

use cmc\ui\frame,
    cmc\ui\view,
    cmc\ui\dynview;

/**
 * factory for select widget, the list widget
 */
class checkboxfactory extends widgetfactory {

    const className = __CLASS__;

    static function makewidget(frame $frame, $xpath = '', $initialval = null) {
        return new checkbox($frame, $xpath);
    }

}

/**
 * checkbox component
 *
 * @copyright     Copyright (c) Calmarsoft company (FRANCE) (http://calmarsoft.com)
 * @link          http://cmc.calmarsoft.com CMC Project
 * @license       http://www.gnu.org/licenses/ GNU General Public License version 3
 * @version       0.9
 */
class checkbox extends input {

    const factory = checkboxfactory::className;

    public function __construct($frame, $xpath = '') {
        $this->_bDynamic = true;
        parent::__construct($frame, $xpath);
    }

    public function viewInitialUpdate(view $view, frame $frame) {
        if (!$this->bOption(self::OPT_INPUT_LEAVE_AUTOCOMPLETE))
            $this->DOMUpdateAttr('autocomplete', 'off');
        parent::viewInitialUpdate($view, $frame);
    }
    /**
     * Changes the DOM using the given property
     * 
     * @param baseview $view    view being updated
     * @param string $propname    property name
     * @param mixed $propval property new value
     * @return boolean  true if success
     */
    protected function applyPropertyDOM($view, $propname, $propval) {
        switch ($propname) {
            case 'value':
                if ($propval===true || $propval===1)
                    $this->DOMUpdateAttr('checked', '');
                else
                    $this->DOMRemoveAttr('checked');
                return true;

            default:
                return parent::applyPropertyDOM($view, $propname, $propval);
        }
    }
    
    /**
     * Retreives the original property value (from model)
     * 
     * @param string $propname property name
     * @return mixed property value or false
     */
    protected function modelPropertyDOM($propname) {
        $val=null;
        switch($propname) {
            case 'value':
                $c = $this->DOMGetAttr('checked');
                $val = ( ($c!=='0') && ($c!==false) );
                break;
            default:
                $val = parent::modelPropertyDOM($propname);
        }        
        if (!$val)
            return false;
        
        $this->syncProperty($propname, $val);
        return true;
    }
}