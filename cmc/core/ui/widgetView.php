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

use cmc\core\ui\material;
/**
 * Holds information about a widget that is specific for a view
 *
 * This includes: the dom element, the client side properties, ...
 *
 * @copyright     Copyright (c) Calmarsoft company (FRANCE) (http://calmarsoft.com)
 * @link          http://cmc.calmarsoft.com CMC Project
 * @license       http://www.gnu.org/licenses/ GNU General Public License version 3
 * @version       0.9
 */
class widgetView {

    protected $_domElement;
    protected $_domElement_path;
    protected $_cltproperties = array();
    
    private $_linemodel;
    private $_linemodel_ser;
    
    /**
     * returns a DOM element form the view, for a given frame and xpath
     * @param cmc\ui\view $view
     * @param cmc\ui\frame $frame
     * @param string $xpath
     * @return DOMNode
     */
    static function seekDOMElem($view, $frame, $xpath) {
        return $view->getSectionDOMElement($frame->getId(), $xpath);
    }
    /**
     * @param DOMNode $domElem
     */
    public function __construct($domElem) {
        $this->updateDOMElem($domElem);
    }
    /**
     * re-assign the DOM item
     * @param DOMNode $domElem
     */
    public function updateDOMElem($domElem) {
        $this->_domElement = $domElem;
        $this->_domElement_path = $domElem->getNodePath();
    }
    /**
     * re-seek the DOM items from given view
     * @param cmc\ui\view $view
     */
    public function restoreDOMElem($view) {
        if ($this->_domElement_path) {
            $this->_domElement = $view->getDOMElement($this->_domElement_path);
        }
        if ($this->_linemodel_ser && $this->_domElement) {
            $this->_linemodel = null;
        }
    }
    /**
     * get a client-side property
     * @param string $key
     * @return mixed
     */
    public function CltProp($key) {
        if (array_key_exists($key, $this->_cltproperties))
                return $this->_cltproperties[$key];
        return null;
    }
    /**
     * sets a client side property
     * @param string $key
     * @param mixed $value
     */
    public function SetCltProp($key, $value) {
        $this->_cltproperties[$key] = $value;
    }
    /**
     * retreives the current clt properties collection
     * @return array
     */
    public function getCltProps() {
        return $this->_cltproperties;
    }
    /**
     * gets the actual node item
     * @return DOMNode
     */
    public function GetDOMNode() {
        return $this->_domElement;
    }
    /**
     * insert html code in the node, by replacing previous child nodes
     * @param string $html_code
     */
    public function DOMSetHtml($html_code) {
        if (isset($this->_domElement) && $this->_domElement && $this->_domElement->nodeType) {
            // parsing de l'html
            $cleanStart = null;
            $elem = $this->_domElement;
            $new_nodes = material::getCloneFromSource($html_code, $elem->ownerDocument);
            if ($new_nodes) {
                $orglen = $elem->childNodes->length;
                $idx = 0;
                foreach ($new_nodes as $new_node) {
                    if ($idx >= $orglen) {
                        $this->_domElement->appendChild($new_node);
                    } else {
                        $elem->replaceChild($new_node, $elem->childNodes->item($idx));
                    }
                    $idx++;
                }
                $cleanStart = $idx;
            } else {
                if ($elem->childNodes->length != 0) {
                    $cleanStart = 0;
                }
            }
            if ($cleanStart != null) {
                $clean = array();
                for ($i = $cleanStart; $i < $elem->childNodes->length; $i++) {
                    array_push($clean, $elem->childNodes->item($i));
                }
                foreach ($clean as $cleanitem) {
                    $elem->removeChild($cleanitem);
                }
            }
        }
    }
    /**
     * assigns a text value (caption) in the node
     * @param string $new_text
     */
    public function DOMSetText($new_text) {

        if (isset($this->_domElement) && $this->_domElement && $this->_domElement->nodeType) {
            /* textual part */
            if ($this->_domElement->nodeType == XML_TEXT_NODE) {
                $this->_domElement->nodeValue = $new_text;
            } else {
                if ($this->_domElement->childNodes->length == 0) {
                    $this->_domElement->appendChild(
                            $this->_domElement->ownerDocument->createTextNode($new_text));
                }
                else
                    foreach ($this->_domElement->childNodes as $node) {
                        if ($node->nodeType == XML_TEXT_NODE) {
                            $node->nodeValue = $new_text;
                            break;
                        }
                    }
            }
        }
    }
    /**
     * gets the current text value from the node item
     * @return string|null
     */
    public function DOMGetText() {
        if (isset($this->_domElement) && $this->_domElement && $this->_domElement->nodeType) {
            /* textual part */
            if ($this->_domElement->nodeType == XML_TEXT_NODE)
                return $this->_domElement->nodeValue;
            else if ($this->_domElement->childNodes->length > 0) {
                foreach ($this->_domElement->childNodes as $node)
                    if ($node->nodeType == XML_TEXT_NODE) {
                        return $node->nodeValue;
                        break;
                    }
            }
        }
        return null;
    }
    /** 
     * assigns an attribute value
     * @param string $attr
     * @param string $attrval
     */
    public function DOMUpdateAttr($attr, $attrval=null) {
        if ($this->_domElement) {
            if ($attrval==null) {
                $this->_domElement->removeAttribute($attr);
                $this->_domElement->setAttributeNode($this->_domElement->ownerDocument->createAttribute($attr));
            } else
                $this->_domElement->setAttribute($attr, $attrval);
        }
    }
    /** 
     * removes an attribute value
     * @param string $attr
     * @param string $attrval
     */
    public function DOMRemoveAttr($attr) {
        if ($this->_domElement)
            $this->_domElement->removeAttribute($attr);
    }
    /**
     * gets a value of the attribute
     * 
     * returns false if attribute not present
     * returns null if DOM element is not available
     * @param string $attr
     * @return string|null|false
     */
    public function DOMGetAttr($attr) {
        if ($this->_domElement) {
            if (!$this->_domElement->hasAttribute($attr))
                return false;
            return $this->_domElement->getAttribute($attr);
        }
        return null;
    }
    
    /**
     * gets a value of an attribute of a child node
     * @param string node name
     * @param string attribute name
     * @return string|boolean
     */
    public function DOMGetNodeAttr($nodename, $attr) {
        if ($this->_domElement) {
            $nodes = $this->_domElement->getElementsByTagName($nodename);
            foreach ($nodes as $node) {
                if ($node->hasAttribute($attr))
                    return $node->getAttribute($attr);
            }
        }
        return false;
    }
    /**
     * sets an attribute value for a childnode
     * @param string $nodename
     * @param string $attr
     * @param string $value
     * @return boolean
     */
    public function DOMPutNodeAttr($nodename, $attr, $value) {
        if ($this->_domElement) {
            $nodes = $this->_domElement->getElementsByTagName($nodename);
            foreach ($nodes as $node) {
                if ($node->hasAttribute($attr))
                    return $node->setAttribute($attr, $value);
            }
        }
        return false;
    }
    public function DOMGetNodeWithAttrValue($view, $attr, $value) {
        if ($this->_domElement && $view) {
            return $view->getDOMSubElement($this->_domElement, 'descendant::*[@' . $attr . '=\'' . $value . '\']');
        }
        return false;
    }
    /**
     * gets the current line "model", used by composite widget
     * @param cmc\ui\view $view
     * @return DOMNodeList
     */
    public function getLineModel($view) {
        
        if ($this->_linemodel_ser && (!$this->_linemodel || $this->_linemodel->ownerDocument != $view->material()->document())) {
            $ref = material::getCloneFromSource($this->_linemodel_ser, $view->material()->document());
            if (is_array($ref))
              $this->_linemodel = $ref[0];
        }          
        return $this->_linemodel;
    }
    /**
     * stores a line "model", used by composite widget
     * @param DOMNodeList $linemodel
     */
     public function setLineModel($linemodel) {
        $this->_linemodel = $linemodel;
    }   
    public function resetLineModel() {
        $this->_linemodel = $this->_linemodel_ser = null;
    }
    
    /**
     * called when serialized with the app
     */
    public function OnSerialize() {
        if ($this->_domElement!=null)
            $this->_domElement_path = $this->_domElement->getNodePath();               
        $this->_domElement = null; 
        
        if ($this->_linemodel && !$this->_linemodel_ser) {
            $doc = $this->_linemodel->ownerDocument;
            if ($doc)
                $this->_linemodel_ser = $doc->saveHTML($this->_linemodel);
        }
        $this->_linemodel = null;
        $this->_linedoc = null;
    }
    /**
     * called after deserialization
     */    
    public function OnUnserialize() {
        $this->_domElement = null;      
    }
    /**
     * called after having cloned this object
     * @param widgetView $srcinstance
     */
    public function onClone($srcinstance) {
        $this->OnSerialize();
        $this->OnUnserialize();
    }

}


