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

use cmc\ui\frame, cmc\ui\dynview;

/**
 * factory for menu widget, with id/xdpath and optional initial caption
 */
class tabfactory extends widgetfactory {

    const className = __CLASS__;

    static function makewidget(frame $frame, $xpath = '', $initialVal = '') {
        return new tab($frame, $xpath, $initialVal);
    }

}

/**
 * menu widget
 *
 * @copyright     Copyright (c) Calmarsoft company (FRANCE) (http://calmarsoft.com)
 * @link          http://cmc.calmarsoft.com CMC Project
 * @license       http://www.gnu.org/licenses/ GNU General Public License version 3
 * @version       0.9
 */
class tab extends widget {

    const factory = tabfactory::className;

    public function __construct($frame, $xpath = '') {
        parent::__construct($frame, $xpath);
        $this->_bDynamic = true;
        $this->setJSObject('tabs');
    }

    protected function applyPropertyDOM($view, $propname, $propval) {
        switch($propname) {
            case 'value':
                if (\is_numeric($propval))
                    $this->setJSObjectParms('{ active: '.$propval.' }');
                break;
        }
        parent::applyPropertyDOM($view, $propname, $propval);
    }

    public function viewUpdate(dynview $view) {
        $this->addEventPost('activate');
        parent::viewUpdate($view);
    }
}
