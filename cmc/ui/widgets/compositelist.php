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
require_once('cmc/db/datasource.php');

use cmc\ui\frame, cmc\ui\view, cmc\ui\dynview, cmc\config, cmc\core\ui\material;

/**
 * factory for compositelist widget, the general purpose list widget
 */
class compositelistfactory extends widgetfactory {

    const className = __CLASS__;

    static function makewidget(frame $frame, $xpath = '', $initialval = null) {
        return new compositelist($frame, $xpath);
    }

}

/**
 * general purpose list widget; can be used for listbox, combobox, grids, enumerations, ...
 *
 * @copyright     Copyright (c) Calmarsoft company (FRANCE) (http://calmarsoft.com)
 * @link          http://cmc.calmarsoft.com CMC Project
 * @license       http://www.gnu.org/licenses/ GNU General Public License version 3
 * @version       0.9
 */
class compositelist extends widget {

    const prop_Data = 'compData';
    const factory = compositelistfactory::className;
    const matchVar = '/[$]{([a-zA-Z_][a-zA-Z0-9%_\-]*)}|[$]([a-zA-Z][a-zA-Z0-9%]*)/';

    protected $_composite_map = array(), $_composite_mapkeys = null;
    private $_postUpdData;

    public function __construct($frame, $xpath = '') {
        parent::__construct($frame, $xpath);
        $this->_bDynamic = $frame->is_dynamic();
        $this->setCompData(array());
    }
    /**
     * specifies how to map the list shape and the fields
     * 
     * @param array key=>val array, with key as item xpath, and val as fieldname
     * @param array $mapkeys string array, to specify which field(s) uniquely identifies a row
     */
    public function setCompositeMap($map, $mapkeys = null) {
        $this->_composite_map = $map;
        $this->_composite_mapkeys = $mapkeys;
        if ($this->_currwview)
            $this->_currwview->resetLineModel();
    }
    /**
     * prepares the json entry to propagate the composite map to the client side
     * @return string
     */
    private function jsonCompositeMap() {
        $res = \json_encode($this->_composite_map);
        if ($this->_composite_mapkeys)
            $res .= ',' . \json_encode($this->_composite_mapkeys);
        return $res;
    }

    private $refdata;
    /**
     * subroutine used in applyCompositeDOM with function preg_replace_callback
     * needs to be public because this is a callback
     * used to place the data inside an expression used in the composite map
     * @param array regular expresion match
     * @return string the value to substitute
     * @access private
     * @internal
     */
    public function substData($matches) {
        $key = $matches[1]; 
        if (!$key) $key = $matches[2];
        if (array_key_exists($key, $this->refdata))
            $val = $this->refdata[$key];
        else if (array_key_exists($key, $this->_constants))
                $val = $this->_constants[$key];
        else
            $val = '';
        return $val;
    }

    /**
     * allows custom actions on the widget, on DOM build
     * @param array $data   the data row
     * @param DOMElement $domItem   the DOM row
     */
    protected function OncompositeRowDOM($data, $domItem) {        
    }
    /**
     * upadates the list with the provided data
     * 
     * data is an iterator with associative arrays: a data source, an array of associative arrays, ...
     * @param \cmc\ui\view $view
     * @param Iterator $data
     */
    protected function applyCompositeDOM($view, $data) {
        $linecount = 0;
        // effacement des fils
        $rootnode = null;
        $linemodel = null;
        
        if ($this->_currwview) {
            $rootnode = $this->_currwview->getDOMNode();
            $linemodel = $this->_currwview->getLineModel($view);
        }
        if (!$rootnode || !$linemodel || $data===null)
            return;
        
        $node = $rootnode;

        $to_remove = array();
        foreach ($node->childNodes as $child)
            array_push($to_remove, $child);
        foreach ($to_remove as $child)
            $node->removeChild($child);
        
        // parcours des données    
        if ($data)
        foreach ($data as $line) {
            $newnode = null;
            foreach ($this->_composite_map as $path => $keym) {
                $html = false;
                
                if ($newnode == null) {
                    $newnode = $linemodel->cloneNode(true);
                    $this->OncompositeRowDOM($line, $newnode);
                }

                $item = $view->getDOMSubElement($newnode, $path);
                if ($item) {
                    $this->refdata = $line;
                    // allows ${field} in the "value" part
                    $cntrepl = 0;
                    if ($keym[0]==='!') {
                        $key = substr($keym, 1);
                        $html = true;
                    } else
                        $key = $keym;
                    $val = preg_replace_callback(self::matchVar, array($this, 'substData'), $key, -1, $cntrepl);
                    if ($cntrepl == 0) {
                        if (array_key_exists($key, $line))
                            $val = $line[$key];
                        else if ($this->_constants!==null && array_key_exists($key, $this->_constants))
                            $val = $this->_constants[$key];
                        else
                            $val = '';
                    }

                    switch ($item->nodeType) {
                        case XML_ELEMENT_NODE:
                            if ($item->nodeName==='select') {
                                // combo selection
                                foreach ($item->childNodes as $sel) {
                                    if ($sel->nodeType==XML_ELEMENT_NODE &&
                                        $sel->nodeName=='option' &&
                                        $sel->hasAttribute('value') &&
                                        $sel->getAttribute('value')===$val) {
                                        $sel->setAttribute ('selected', '');
                                    }
                                }
                            }
                            else {
                                if (!$html)
                                    $item->nodeValue = $val;
                                else
                                    material::DOMsetHtml($item, $val);
                            }
                            break;
                        case XML_ATTRIBUTE_NODE:
                            $item->value = $val;
                            break;
                        case XML_TEXT_NODE:
                            if (!$html)
                                $item->data = $val;
                            else
                                material::DOMsetHtml($item, $val);                            
                            break;
                    }
                }
            }
            if ($newnode != null) {
                $this->OnWidgetEvent('newline', $linecount, $line, $newnode);
                $node = $rootnode->appendChild($newnode);
                $linecount++;
            }
        }
        if ($linecount == 0) {
            $newnode = $linemodel->cloneNode(true);            
            $style = '';
            if ($newnode->hasAttribute('style')) {
                $style=chop($newnode->getAttribute('style'));
                if ($style!=='' && $style[strlen($style)-1]!==';') $style.=';';
            }
            $newnode->setAttribute('style', $style.'display:none;');
            //$newnode->setAttribute('hidden', 'true');
            $node = $rootnode->appendChild($newnode);
        }
    }
    /**
     * gets the current composite value for Ajax answer
     * @return array
     */
    protected function getAjaxCompositeVal() {
        $data = $this->_properties[self::prop_Data];
        if ($data===null)
            return null;

        $fields = array();
        foreach ($this->_composite_map as $path => $key) {
            $matches = null;
            $nmatch = preg_match_all(self::matchVar, $key, $matches);
            if ($nmatch==0)
                $fields[$key] = 0;
            else {
                foreach ($matches[1] as $match)
                    $fields[$match] = 0;
            }
        }
        if ($this->_composite_mapkeys)
            foreach ($this->_composite_mapkeys as $key)
                $fields[$key] = 0;

        $result = array();
        $dataline = null;
        foreach ($data as $line) {
            foreach ($fields as $key=>$val) {                
                if (array_key_exists($key, $line)) {
                    if ($dataline == null)
                        $dataline = array();
                    $dataline[$key] = $line[$key];
                } else if ($this->_constants!==null && array_key_exists($key, $this->_constants)) {
                    if ($dataline == null)
                        $dataline = array();
                    $dataline[$key] = $this->_constants[$key];
                }
            }
            if ($dataline != null)
                array_push($result, $dataline);
        }
        return $result;
    }
  
    
    private $_dataValid;
    /**
     * updates internal data for avoiding expensive dublicates
     * @param type $ajax
     */
    protected function setDataStateAjax($view, $ajax) {
        if ($ajax) {
            $this->_dataValid = 'ajax';
            if ($this->_currwview)
                $this->_currwview->restoreDOMElem($view);
        } else {
            $this->_dataValid = 'dom';            
        }
    }
    
    /**
     * gets a property value for Ajax answer
     * @param string self::prop_Data
     * @return mixed
     */
    protected function getAjaxPropVal($propname) {
        switch ($propname) {
            case self::prop_Data:
                return $this->getAjaxCompositeVal();
        }

        return parent::getAjaxPropVal($propname);
    }
    /**
     * applies a property to the DOM
     * @param \cmc\ui\view
     * @param string $propname
     * @param mixed $propval
     * @return mixed
     */
    protected function applyPropertyDOM($view, $propname, $propval) {
        switch ($propname) {
            case self::prop_Data:
            //    if ($this->getFrame()->is_dynamic() == $view->is_dynamic())
                    return $this->applyCompositeDOM($view, $propval);
        }

        parent::applyPropertyDOM($view, $propname, $propval);
    }
    
    private function _postUpdDataFindRowKey($array, $key) {
        foreach($array as $i => $item)
            if ($item['key']==$key)
                return $i;
        return false;
    }
    /**
     * synchronize a property changed on the client
     * 
     * for compositelist, it stores it in an array for all changed lines (or all if no rowkey fields were defined)
     * @param string $propname
     * @param mixed $value
     */
    public function syncProperty($propname, $value) {
        switch ($propname) {
            case self::prop_Data:
                if ($this->_postUpdData===null)
                    $this->_postUpdData = array();
                foreach ($value as $line) {
                    if ($line != null) {
                        if (array_key_exists('_rowkey', $line)) {
                            $key = $line['_rowkey'];
                            unset($line['_rowkey']);
                            $idx = $this->_postUpdDataFindRowKey($this->_postUpdData, $key);
                            if ($idx===false)
                                array_push($this->_postUpdData, array('key' => $key,
                                    'data' => $line));
                            else
                                $this->_postUpdData[$idx] = array('key' => $key, 'data' => $line);
                        }
                        else
                            array_push($this->_postUpdData, $line);
                    }
                }
                $this->_dataValid='';
                $this->_properties[self::prop_Data] = null;
                $this->_currwview->SetCltProp(self::prop_Data, null);                    
                return; // not using parent implementation (avoids setval)
        }
        parent::syncProperty($propname, $value);
    }
    /**
     * retrieves the client-side changed data in the compositelist object
    * @return array
     */
    public function getPostCompData() {
        $result = $this->_postUpdData;
        $this->_postUpdData = array();
        
        return $result;
    }

    /* pour debug, affichage des chemin des sous-noeuds */

    private function dump_nodePath($node) {
        if ($node->attributes)
            foreach ($node->attributes as $name => $attr)
                echo $attr->getNodePath() . "<br>";

        if ($node->childNodes) {
            foreach ($node->childNodes as $item) {
                echo $item->getNodePath() . "<br>";
                $this->dump_nodePath($item);
            }
        }
    }
    /**
     * useful for the developer: dumps the available xpath values for an item in the composite liste
     * (extracted from the first line which represents the 'model')
     */
    public function dump_lineElems($view) {
        if (!$this->_currwview)
            $this->viewLoaded($view);        
        $this->updateLineModel($view);
        if ($this->_currwview && $this->_currwview->getLineModel($view))
            $this->dump_nodePath($this->_currwview->getLineModel($view));
        else
            echo "null<br>";
    }

    private function updateLineModel($view) {
        $this->_dataValid = null;
        $node = null;       
        $bNewModel = false;
        
        if ($this->_currwview && $this->_currwview->getLineModel($view) == null && $this->_composite_map) {
                        
            $linemodel = null;
            // get the first li child
            
            $node = $this->_currwview->GetDOMNode();

            if ($node && $node->hasChildNodes()) {
                $i = 0;
                while (!$linemodel && $i < $node->childNodes->length) {
                    $item = $node->childNodes->item($i);
                    if ($item->hasChildNodes() || $item->hasAttributes()) {
                        $linemodel = $item->cloneNode(true);
                        $linemodel->removeAttribute('hidden');
                        $bNewModel = true;
                    }
                    $i++;
                }
            }
            
            if ($linemodel) {
                $to_remove = array();
                foreach ($node->childNodes as $child)
                    array_push($to_remove, $child);
                foreach ($to_remove as $child)
                    $node->removeChild($child);

                // remove map entries that are inexistent
                $rmvlist = array();
                foreach ($this->_composite_map as $path => $key) {
                    $mapItem = $view->getDOMSubElement($linemodel, $path);
                    if (!$mapItem) {
                        array_push($rmvlist, $path);
                    } else {
                        // for some items, places the 'autocomplete' attribute to off by default
                        switch($mapItem->nodeName) {
                            case 'textarea':
                            case 'input':
                            case 'select':
                                if (!$this->bOption(self::OPT_INPUT_LEAVE_AUTOCOMPLETE))
                                    $mapItem->setAttribute('autocomplete', 'off');
                                break;
                        }
                    }
                }
                foreach ($rmvlist as $path)
                    unset($this->_composite_map[$path]);
                //check that all keys are present in values
                if ($this->_composite_mapkeys!==null)
                    foreach ($this->_composite_mapkeys as $key) {
                        if (!array_search($key, $this->_composite_map)) {                           
                            $attrname = 'data-'.$key;
                            $linemodel->setAttribute($attrname, '');
                            $this->_composite_map['@'.$attrname] = $key;
                        }
                    }
                    
                $code = '.hdrmap(' . $this->jsonCompositeMap() . ')';
                $this->addScriptCode($code);                    
            }
            $this->_currwview->setLineModel($linemodel);
        }        
    }
    
    /**
     * default widget's implementation of the static part of update
     * 
     * this is preparing the line model (extracted from the first entry in the list)
     * @param \cmc\ui\view $view
     * @param \cmc\ui\frame $frame
     */
    public function viewInitialUpdate(view $view, frame $frame) {
        $this->updateLineModel($view);
        parent::viewInitialUpdate($view, $frame);
    }
        /**
     * default implementation of dynamic update
     * 
     * @param \cmc\ui\dynview $view
     */
    public function viewPreUpdate(dynview $view) {
        $this->updateLineModel($view);
        parent::viewPreUpdate($view);
    }    
    /**
     * default implementation of dynamic update
     * 
     * @param \cmc\ui\dynview $view
     */
    public function viewUpdate(dynview $view) {
        parent::viewUpdate($view);
    }
    
   /**
     * POST 'done' event
     * 
     * @param type $view
     */
    public function onPOSTDone($view) {        
    }
    
    /**
     * assigns a datasource to the compositelist for automatic input handling
     * 
     * @param type $sess
     * @param type $qry
     */
    public function setDataQuery($sess, $qry) {
        $this->_postUpdData = array();
        $this->setPropertyQuery($sess, self::prop_Data, $qry);
    }
    /**
     * assigns the data object (for example an array of associative arrays)
     * @param Iterator $data
     */
    public function setCompData($data) {
        $this->_postUpdData = array();
        $this->setProperty(self::prop_Data, $data);
    }
    
    public function getCompData() {
        return $this->getProperty(self::prop_Data);
    }

    public function OnSerialize() {
        // wipe out material 'data' if DOM is saved and valid data is Ajax
        if ($this->_dataValid==='ajax' && config::SESS_save_mat) {
            // valid data is in $data -> wipe out the DOM part
            if ($this->_currwview) {
                $rootnode = $this->_currwview->getDOMNode();
                if ($rootnode) {
                    $to_remove = array();
                    foreach ($rootnode->childNodes as $child)
                        array_push($to_remove, $child);
                    foreach ($to_remove as $child)
                        $rootnode->removeChild($child); 
                }
            }
        }
        // remove data if dom is the valid part
        if ($this->_currwview) {
            //if ($this->_dataValid==='dom' || !config::SESS_save_mat) {
                if (array_key_exists(self::prop_Data, $this->_properties))
                  $this->_currwview->SetCltProp(self::prop_Data, null);
            //}
        }        
//        if (array_key_exists(self::prop_Data, $this->_properties))
//            $this->_properties[self::prop_Data] = null;
        parent::OnSerialize();
    }

    public function OnUnSerialize() {
        parent::OnUnserialize();
    }

    public function onClone($srcinstance) {
        parent::onClone($srcinstance);
    }


}


