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

namespace cmc\ui\widgets;

require_once('cmc/ui/frame.php');
require_once('cmc/db/datasource.php');
require_once('cmc/core/ui/widgetViews.php');

use cmc\core\IClonable as IClonablep;
use cmc\core\ISerializable as ISerializablep;
use cmc\sess,
    cmc\cmc;
use cmc\db\datasource;
use cmc\core\ui\widgetViews;
use cmc\ui\frame,
    cmc\ui\view;
use cmc\ui\dynview;

abstract class widgetfactory {

    const className = __CLASS__;

    static function makewidget(frame $frame, $xpath = '', $initialVal = null) {
        return null;
    }

}

/**
 * 
 * common model for each widget
 *
 * the widget as a relationship with a frame's view. It follows the lifecycle of the frame
 * inside the view - we can change the view but the frame may remain valid -.
 * it at first linked to the initial DOM. Then it can be updated using the Ajax scheme.
 * 
 * @copyright     Copyright (c) Calmarsoft company (FRANCE) (http://calmarsoft.com)
 * @link          http://cmc.calmarsoft.com CMC Project
 * @license       http://www.gnu.org/licenses/ GNU General Public License version 3
 * @version       0.9
 */
abstract class widget implements IClonablep, ISerializablep {

    /**
     * When enabled, don't set 'autocomplete=off' attribute in input items (default is disabled)
     */
    const OPT_INPUT_LEAVE_AUTOCOMPLETE = 1;
    const jqueryHighlight = 'ui-state-highlight';
    const jqueryFocus = 'ui-state-focus';
    const jqueryHover = 'ui-state-hover';

    protected $_options;
    protected $_dftTagId = 'id';
    private $_frame;
    //protected $_domElement;
    //protected $_domElement_path;
    //private $_cltproperties;  // client side property values
    protected $_wviews, $_currwview;
    private $_objxpath;       // xpath of the component
    protected $_name;           // name in the frame
    protected $_jsObj;          // javascript object (jQuery ..)
    protected $_jsObjParms;
    protected $_domid;          // main object id in DOM (for jQuery)
    protected $_properties;     // all widget properties such as caption, color, additional classes, ...
    protected $_constants;      // defines widget user-defined constants, which can be referenced later
    protected $_bDynamic;
    private $_ajaxAnswer;
    private $_widgetListeners;
    protected $_actualscript;
    protected $_composcript;

    // widget is instanciated with parent frame and xpath of the element. If not xpath, this is element id

    public function __construct($frame, $xpath = '') {
        $this->_frame = $frame;
        $this->_wviews = new widgetViews();
        $this->_properties = array();
        $this->_widgetListeners = array();
        $this->_bDynamic = false;

        if ($xpath !== '') {
            if (substr($xpath, 0, 1) == '/')
                $this->_objxpath = $xpath;
            else if (preg_match('/^\..+/', $xpath)) {
                $class = substr($xpath, 1);
                $this->_objxpath = '//*[contains(concat(\' \', @class, \' \'), \' ' . $class . ' \')]';
                $this->_domid = $xpath;
            } else {
                $this->_objxpath = "//*[@" . $this->_dftTagId . "='$xpath']";
                $this->_domid = '#' . $xpath;
            }
        }
    }

    /**
     * reassigns the underlying frame
     * 
     * @param \cmc\ui\frame $frame
     */
    public function setFrame(frame $frame) {
        $this->_frame = $frame;
    }
    /*
     * retrieves underlying frame
     */
    public function getFrame() {
        return $this->_frame;
    }

    /**
     * assigns a name
     * @param strings $name
     */
    public function setName($name) {
        $this->_name = $name;
    }

    /**
     * retrieves the name
     * @return type
     */
    public function getName() {
        return $this->_name;
    }

    /**
     * sets the javascript 'object' name, used in the JavaScript framework (jQuery, ...)
     * @param string $jsType
     */
    public function setJSObject($jsType) {
        $this->_jsObj = $jsType;
        $this->_actualscript = false;
    }

    public function setJSObjectParms($jsParms) {
        $this->_jsObjParms = $jsParms;
        $this->_actualscript = false;
    }

    // lifecyle events
    // 
    /**
     * view loaded: view's DOM is available
     * used to update the current dom item from the current view
     * @param \cmc\ui\view $view
     */
    public function viewLoaded(view $view) {
        if ($this->_objxpath != '') {
            $this->_currwview = $this->_wviews->initDOMElement($view, $this->_frame, $this->_objxpath);
        } else
            $this->_currwview = null;
    }

    public function AddWidgetEventListener($eventname, callable $cb) {
        if (!array_key_exists($eventname, $this->_widgetListeners)) {
            $this->_widgetListeners[$eventname] = array();
        }
        $this->_widgetListeners[$eventname][get_class($cb[0]) . '.' . $cb[1]] = $cb;
    }

    /**
     * adds a callback listened for given event on given widget
     * @param type $eventname
     * @param \cmc\ui\callable $cb
     */
    public function AddEventListener($eventname, callable $cb) {
        return $this->_frame->AddEventListener($eventname, $this->getName(), $cb);
    }

    protected function OnWidgetEvent($eventName) {        
        if (array_key_exists($eventName, $this->_widgetListeners)) {
            $arguments = func_get_args();
            foreach ($this->_widgetListeners[$eventName] as $cb) {
                call_user_func_array($cb, $arguments);
            }
        }
    }

    /**
     * 
     * some direct functions, for initial view render
     * - to be avoided when possible (prefer properties)
     */

    /**
     * shortcut method to change the value as HTML
     *
     * @param string $html_code
     */
    public function DOMSetHtml($html_code) {
        if ($this->_currwview)
            $this->_currwview->DOMSetHtml($html_code);
    }

    /*
     * DOMSetText - set text part of the component
     * @param string $new_text
     */

    public function DOMSetText($new_text) {
        if ($this->_currwview)
            $this->_currwview->DOMSetText($new_text);
    }

    /**
     * retrieves text part from the DOM
     * @return string
     */
    public function DOMGetText() {
        if ($this->_currwview)
            return $this->_currwview->DOMGetText();
    }

    /*
     * update/set attibute value in the DOM
     * @param string $attr
     * @param string $attrval
     */

    public function DOMUpdateAttr($attr, $attrval = null) {
        if ($this->_currwview)
            return $this->_currwview->DOMUpdateAttr($attr, $attrval);
    }

    /*
     * remove attibute value in the DOM
     * @param string $attr
     * @param string $attrval
     */

    public function DOMRemoveAttr($attr) {
        if ($this->_currwview)
            return $this->_currwview->DOMRemoveAttr($attr);
    }

    /**
     * gets an attribute value from the DOM
     * @param string $attr
     * @return string
     */
    public function DOMGetAttr($attr) {
        if ($this->_currwview)
            return $this->_currwview->DOMGetAttr($attr);
    }

    /**
     * gets an attribute value from a named child node in the DOM
     * @param string $nodename
     * @param string $attr
     * @return string|boolean
     */
    public function DOMGetNodeAttr($nodename, $attr) {
        if ($this->_currwview)
            return $this->_currwview->DOMGetNodeAttr($nodename, $attr);
    }

    /**
     * sets an attribute value from a named child node in the DOM
     * @param string $nodename
     * @param string $attr
     * @param string $value
     * @return boolean
     */
    public function DOMPutNodeAttr($nodename, $attr, $value) {
        if ($this->_currwview)
            return $this->_currwview->DOMPutNodeAttr($nodename, $attr, $value);
    }

    /*     * **
     *  
     * livecycle events
     * 
     * ** */

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
            case 'caption':
                if (is_string($propval)) {
                    $this->DOMSetText($propval);
                    return true;
                }
                break;
            case 'html':
                if (is_string($propval)) {
                    $this->DOMSetHtml($propval);
                    return true;
                }
                break;
            case 'href':
                if (is_string($propval)) {
                    $this->DOMUpdateAttr('href', $propval);
                    return true;
                }
                break;
            case 'visible':
                if ($propval)
                    $this->DOMRemoveAttr('hidden');
                else
                    $this->DOMUpdateAttr('hidden');
                break;
            case 'enabled':
                if ($propval)
                    $this->addScriptCode('.enable()');
                else
                    $this->addScriptCode('.disable()');
                $this->DOMRemoveAttr('disabled');
                if (!$propval)
                    $this->DOMUpdateAttr('disabled');
                break;
            default:
                return false;
        }
    }

    /**
     * Retreives the original property value (from model)
     * 
     * @param string $propname property name
     * @return mixed property value or false
     */
    protected function modelPropertyDOM($propname) {
        $val = null;
        switch ($propname) {
            case 'caption':
                $val = $this->DOMGetText();
                break;
            case 'href':
                $val = $this->DOMGetAttr('href');
                break;
            case 'visible':
                $hidden = $this->DOMGetAttr('hidden');
                if (!$hidden || $hidden === 'false')
                    $val = true;
                else
                    $val = false;
                break;
            case 'enabled':
                $disabled = $this->DOMGetAttr('disabled');
                if (!$disabled || $disabled === 'false')
                    $val = true;
                else
                    $val = false;
                break;
            default:
        }
        if (!$val)
            return false;

        $this->syncProperty($propname, $val);
        return true;
    }

    protected function getAjaxPropVal($propname) {
        return $this->_properties[$propname];
    }

    protected function setDataStateAjax($view, $ajax) {
        
    }

    /**
     * apply properties on the initial DOM (either session or template)
     * or for Ajax answer
     * 
     * @param baseview $view
     * @param boolean $bDom true if DOM, false if Ajax
     */
    private function applyProperties($view, $bDom) {
        if (!$bDom) {
            $this->_ajaxAnwer = array();
            $success = true;
        }
        if (!$this->_currwview)
            return;

        foreach ($this->_properties as $propname => $propval) {
            if ($bDom || $this->_currwview->CltProp($propname) === null || $this->_currwview->CltProp($propname) !== $propval) {
                $success = true;

                if ($bDom)
                    $success = $this->applyPropertyDOM($view, $propname, $propval);
                else {
                    $val = $this->getAjaxPropVal($propname);
                    if ($val !== null)
                        $this->_ajaxAnswer[$propname] = $val;
                }

                if ($success)
                    $this->_currwview->SetCltProp($propname, $propval);
            }
        }
        if ($bDom)
            $this->setDataStateAjax($view, false);
        else
            $this->setDataStateAjax($view, true);
    }

    /**
     * initial update part (event trigered before cloning the view to the session)
     * 
     * @param baseview $view
     */
    public function viewInitialUpdate(view $view, frame $frame) {
        $this->applyProperties($view, true);
        $script = $this->getScriptCode();
        if ($script && !$frame->is_dynamic()) {
            $view->addScriptCode(trim($script, ' '));
        }
        $this->_actualscript = '';
    }

    /**
     * dynamic pre update
     * current implementation restores the DOM element from current view
     * @param \cmc\ui\dynview $view
     */
    public function viewPreUpdate(dynview $view) {
        if ($this->_frame->is_dynamic())
            $this->_bDynamic = true;
        
        if ($this->_wviews)
            $this->_currwview = $this->_wviews->restoreDOMElem($view);
    }

    /**
     * dynamic update
     * current implementation applies the properties to the DOM, and updates the scripting part
     * @param \cmc\dynview $view
     */
    public function viewUpdate(dynview $view) {
        $this->applyProperties($view, true);
        $script = $this->getScriptCode();
        if ($script)
            $view->addScriptCode(trim($script, ' '));
    }

    /**
     * new properties come from a POST
     * @param type $wprops
     */
    public function POSTupdatedata($wprops) {
        foreach ($wprops as $propname => $propval) {
            $this->syncProperty($propname, $propval);
        }
    }

    /**
     * gets updated data for the Ajax anwser (updated value, error, or empty if unchanged)
     * @return string
     */
    public function getAjaxData($view) {
        $this->applyProperties($view, false);
        $result = $this->_ajaxAnswer;
        $this->_ajaxAnswer = array();
        return $result;
    }

    /**
     * POST 'done' event
     * 
     * @param type $view
     */
    public function onPOSTDone($view) {
        
    }

    /**
     * defines some constants for later use in the widget
     * 
     * the constants can be referenced in place of field values
     * @param array $constants
     */
    public function setConstants($constants) {
        $this->_constants = $constants;
    }

    /**
     *  getter/setters
     */

    /**
     * server-side new property value
     * 
     * @param string $propname
     * @param mixed $newval
     */
    public function setProperty($propname, $newval) {
//        var_dump($this->_name . " / " . $this->_domid.": $propname => $newval");
//        xdebug_print_function_stack();
        $this->_properties[$propname] = $newval;
        if ($this->_currwview && is_array($newval))
            $this->_currwview->SetCltProp($propname, null);
    }

    // direct datasource rows into the property
    /**
     * direct datasource ...
     * 
     * @param string $propname
     * @param \cmc\datasource $ds
     */
    public function setPropertyDatasource($propname, datasource $ds) {
        $data = array();
        foreach ($ds as $line) {
            $dline = array();
            foreach ($line as $col => $val) {
                $dline[$col] = $val;
            }
            array_push($data, $dline);
        }
        $ds->close();
        $this->_properties[$propname] = $data;
        if ($this->_currwview)
            $this->_currwview->SetCltProp($propname, null);
    }

    /**
     * assigns a property name with a query name
     * 
     * implementation will pull the datasource from the default data environment of the session
     * @param \cmc\sess $sess
     * @param type $propname
     * @param type $query
     */
    public function setPropertyQuery(sess $sess, $propname, $query) {
        $de = $sess->getDataEnv();
        if ($de) {
            $ds = $de->getQueryDS($query);
            if ($ds) {
                $this->setPropertyDatasource($propname, $ds);
            }
        }
    }

    /**
     * sets a property value identical on client and on server
     * @param string $propname
     * @param mixed $value
     */
    public function syncProperty($propname, $value) {
        $this->_properties[$propname] = $value;
        if ($this->_currwview)
            $this->_currwview->SetCltProp($propname, $value);
    }

    /**
     * retrieves the current property value
     * @param string $propname
     * @return mixed
     */
    public function getProperty($propname) {
        if (!array_key_exists($propname, $this->_properties))
            $this->modelPropertyDOM($propname);
        if (!array_key_exists($propname, $this->_properties))
            return null;
        return $this->_properties[$propname];
    }

    /**
     * server-side textual value update
     * 
     * @param string $newval
     * @return boolean
     */
    public function setCaption($newval) {
        return $this->setproperty('caption', $newval);
    }

    /**
     * server-side HTML value update
     * 
     * @param string html text
     * @return boolean
     */
    public function setHTML($newval) {
        return $this->setproperty('html', $newval);
    }

    /**
     * sets the 'value' property
     * @param string $newval
     * @return boolean
     */
    public function setValue($newval) {
        return $this->setproperty('value', $newval);
    }

    /**
     * sets the 'visible' property
     * @param boolean $newval
     * @return boolean
     */
    public function setVisible($newval) {
        return $this->setproperty('visible', $newval);
    }

    /**
     * shows the widget
     */
    public function show() {
        $this->setVisible(true);
    }

    /**
     * hides the widget
     */
    public function hide() {
        $this->setVisible(false);
    }

    /**
     * sets the 'visible' property
     * @param boolean $newval
     * @return boolean
     */
    public function setEnabled($newval) {
        return $this->setproperty('enabled', $newval);
    }

    /**
     * shows the widget
     */
    public function enable() {
        $this->setEnabled(true);
    }

    /**
     * hides the widget
     */
    public function disable() {
        $this->setEnabled(false);
    }

    /**
     * gets the caption property valud
     * @return string|boolean
     */
    public function getCaption() {
        return $this->getproperty('caption');
    }

    /**
     * gets the 'value' property
     * @return string|boolean
     */
    public function getValue() {
        return $this->getproperty('value');
    }

    /**
     * gets the 'visible' property
     */
    public function getVisible() {
        return $this->getproperty('visible');
    }

    /**
     * gets the 'enabled' property
     */
    public function getEnabled() {
        return $this->getproperty('enabled');
    }

    /**
     * enables a bool option 
     * 
     * @param integer $opt
     */
    public function setOptions($opt) {
        $this->_options |= $opt;
    }

    /**
     * disables a bool option
     * @param integer $opt
     */
    public function unsetOptions($opt) {
        $this->_options &= ~$opt;
    }

    /**
     * tests an option
     * @param integer $opt
     */
    public function bOption($opt) {
        return (($this->_options & $opt) == $opt);
    }

    /**
     * checks if the component as client interaction and feedback
     * @return boolean
     */
    public function bDynamic() {
        return $this->_bDynamic;
    }
        
    /**
     *  gets JavaScript Snipset to add in the document.ready() section - creation, client side validation, ajax update -
     */
    public function getScriptCode() {
        if (!$this->bDynamic())
            return '';
        //   var_dump('--', $this->_actualscript , $this->_domid , $this->_jsObj , $this->_name);
        if (!$this->_actualscript && $this->_domid && $this->_name) {
            $obj = $this->_jsObj;
            if (!$obj)
                $obj = cmc::className($this);
            $parms = '';
            if ($this->_jsObjParms) {
                $parms = ',' . $this->_jsObjParms;
            }
            $this->_actualscript = 'cmc.n(\'' . $this->_frame->getName() . '\',' .
                    '\'' . $this->_name . '\',' .
                    '\'' . $this->_domid . '\',' .
                    '\'' . $obj . '\'' . $parms . ')' .
                    $this->_composcript . ';';
        }
        return $this->_actualscript;
    }

    /**
     * defines an event that will trigger a POST request
     * @param string event name ('click', 'change', ...)
     */
    public function addEventPost($event) {
        $this->addScriptCode('.eventpost(\'' . $event . '\')');
    }

    /**
     * defines an event that will trigger a user defined function
     * @param type event name ('click', 'change', ...)
     * @param type javascript function name
     */
    public function addEventLocal($event, $fn) {
        if ($fn)
            $this->addScriptCode('.event(\'' . $event . '\', ' . $fn . ')');
        else
            $this->addScriptCode('.event(\'' . $event . '\')');
    }

    /**
     * adds a validation to the widget
     * @param validation name
     * @param (optional) validation parameters
     * Examples: 
     * $f->addValidation('nonEmpty');       // Must not be empty
     * $f->addValidation('minChars', 5);    // Must have at least 5 chars
     * $f->addValidation('length', 10);     // Must be exactly 10 chars
     * $f->addValidation('email');          // Must be a valid email syntax
     * @todo handle it both on client and server (currently only on client)
     */
    public function addValidation() {
        $code = '.validate(';
        $cnt = 0;
        foreach (func_get_args() as $arg) {
            if ($cnt > 0)
                $code .=',' . $arg;
            else {
                if (!strchr($arg, '.'))
                    $code .= 'cmc.valid.' . $arg;
                else
                    $code .= $arg;
            }
            $cnt++;
        }
        $code.=')';
        $this->addScriptCode($code);
    }

    /**
     * adds some script code bound to widget client side object
     * @param string $code
     */
    public function addScriptCode($code) {
        $this->_composcript .= $code;
        $this->_actualscript = false;
    }

    // when serialized with the app
    public function OnSerialize() {
        if ($this->_wviews)
            $this->_wviews->OnSerialize();
        $this->_ajaxAnswer = null;
        $this->getScriptCode();
        $this->_composcript = '';
        $this->_properties = array();       // data remains in 'clt' part
    }

    public function OnUnserialize() {
        if ($this->_wviews)
            $this->_wviews->OnUnSerialize();
        if ($this->_properties !== null && $this->_currwview) {
            foreach ($this->_currwview->getCltProps() as $propname => $propval) {
                $this->_properties[$propname] = $propval;
                //var_dump($propname, $propval);
            }
        }

//        xdebug_print_function_stack();
//        var_dump($this);
    }

    public function onClone($srcinstance) {
        /* if ($_SERVER['REQUEST_METHOD']!='POST') {
          echo "clone: "; var_dump($srcinstance);
          } */
        $this->_ajaxAnswer = null;
        $this->_wviews = new widgetViews();
        if ($srcinstance->_wviews)
            $this->_wviews->OnClone($srcinstance->_wviews);
    }

}
