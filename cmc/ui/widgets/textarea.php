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

use cmc\ui\frame, cmc\ui\view;

/**
 * factory for textarea widget, with id/xdpath and optional initial value
 */
class textareafactory extends widgetfactory {
    const className=__CLASS__;
    static function makewidget(frame $frame, $xpath = '', $initialValue='')
    {
        return new textarea($frame, $xpath, $initialValue);
    }
}
/**
 * textarea widget
 *
 * @copyright     Copyright (c) Calmarsoft company (FRANCE) (http://calmarsoft.com)
 * @link          http://cmc.calmarsoft.com CMC Project
 * @license       http://www.gnu.org/licenses/ GNU General Public License version 3
 * @version       0.9
 */
class textarea extends widget {
    const factory = textareafactory::className;
    
    public function __construct($frame, $xpath = '', $initialValue='') {
        parent::__construct($frame, $xpath);
        $this->_bDynamic = true;
        
        if ($initialValue!=null)
            $this->setValue($initialValue);
    }
    
    protected function applyPropertyDOM($view, $propname, $propval) {
        switch($propname) {
            case 'value':
                if (is_string($propval)) {
                    $this->DOMSetText($propval);
                    return true;
                }
                break;
        }
        parent::applyPropertyDOM($view, $propname, $propval);
    }
    
    public function viewInitialUpdate(view $view, frame $frame) {
        if (!$this->bOption(self::OPT_INPUT_LEAVE_AUTOCOMPLETE))
            $this->DOMUpdateAttr('autocomplete', 'off');
        parent::viewInitialUpdate($view, $frame);
    }
}


