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

use cmc\ui\frame;
/**
 * factory for label widget, with id/xdpath and optional initial caption
 */
class labelfactory extends widgetfactory {
    const className=__CLASS__;
    static function makewidget(frame $frame, $xpath = '', $initialCaption=null)
    {
        return new label($frame, $xpath, $initialCaption);
    }
}

/**
 * label widget
 * 
 * can be used in <span>, <div> and various tags containing text
 * @copyright     Copyright (c) Calmarsoft company (FRANCE) (http://calmarsoft.com)
 * @link          http://cmc.calmarsoft.com CMC Project
 * @license       http://www.gnu.org/licenses/ GNU General Public License version 3
 * @version       0.9
 */
class label extends widget {
    const factory = labelfactory::className;
    
    public function __construct($frame, $xpath = '', $initialCaption=null) {
        parent::__construct($frame, $xpath);
        
        if ($initialCaption)
            $this->setCaption($initialCaption);
        if ($frame->is_dynamic())
            $this->_bDynamic = true;
    }

}


