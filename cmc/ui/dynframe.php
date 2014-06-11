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
namespace cmc\ui;

include_once('frame.php');

use cmc\sess,
    cmc\error\fatalErrors;

/**
 * dynamic frame
 * 
 * @copyright     Copyright (c) Calmarsoft company (FRANCE) (http://calmarsoft.com)
 * @link          http://cmc.calmarsoft.com CMC Project
 * @license       http://www.gnu.org/licenses/ GNU General Public License version 3
 * @version       0.9
 */
abstract class dynframe extends frame {

    private $_bPending = false;
    private $_bCancelPend = false;
    private $_pendingdata = null;
    private $_sess = null;
    private $_eventListeners = array();

    public function initialize() {
        parent::initialize();
    }

    /**
     * @return true
     */
    public function is_dynamic() {
        return true;
    }
   /**
     * true if in dynamic state: dynamic and linked to a session
     */
    public function is_dynamic_state() {
        return $this->_sess != null; 
    }


    /**
     * @return sess
     */
    public function getSession() {
        return $this->_sess;
    }

    /**
     * @param sess $sess
     */
    public function setSession($sess) {
        $this->_sess = $sess;
    }

    /**
     * prepares dynamic update
     * 
     * @param type $view
     * @param type $sess
     */
    public function viewPreUpdate($view, $sess) {
        foreach ($this->_widgets as $w)
            $w->viewPreUpdate($view);
    }

    /**
     * dynamic update
     * override this function to make process each time the view is refreshed
     *  (not called when class is used for POST processing; you need to use specific overrides or handlers for this)
     * @param type $view
     * @param type $sess
     */
    public function viewUpdate($view, $sess) {
        
    }

    /**
     * implementation of dynamic updating
     * 
     * updates widgets and related scripting code, according to changes
     * made by viewUpdate method
     * @param type $view
     * @param type $sess
     */
    public function viewPostUpdate($view, $sess) {
        foreach ($this->_widgets as $w)
            $w->viewUpdate($view);
        if ($this->_code !== '') {
            $view->addScriptCode($this->_code);
        }
        if ($this->_bPending) {
            $pdata = json_encode($this->_pendingdata);
            $view->addBottomScriptCode('cmc.postpend(\'' . $this->getName() . '\', ' . $pdata . ');');
            $this->_bPending = false;
            $this->_pendingdata = null;
        }
    }

    /**
     * returns the frame's data part of Ajax answer
     * @return mixed
     */
    public function getAjaxData($view) {
        $result = null;
        foreach ($this->_widgets as $w) {
            $wdata = $w->getAjaxData($view);
            if ($wdata) {
                if (!$result)
                    $result = array();
                $result[$w->getName()] = $wdata;
            }
        }
        return $result;
    }

    /**
     * return's the frame's process part of Ajax answer
     * this is some action to perform on client just after rendering changes
     * @return boolean
     */
    public function getAjaxProcess() {
        if ($this->_bPending) {
            $result = array();
            $result['pending'] = true;
            $result['pendingdata'] = $this->_pendingdata;
            $this->_bPending = false;
            $this->_pendingdata = null;
            return $result;
        }
        if ($this->_bCancelPend) {
            $result = array();
            $result['cancel'] = true;
            $this->_bCancelPend = false;
            return $result;
        }
        return false;
    }

    /**
     *  event mamangement
     */

    /**
     * binds a callback for 'click' event on given widget
     * @param type $widget_name
     * @param \cmc\ui\callable $cb
     */
    public function AddClickEvent($widget_name, callable $cb) {
        if (!$this->is_dynamic_state()) // no callback in static...
            fatalErrors::trigger(null, 'callback_wrongstate', 2);
        $w = $this->w($widget_name);
        if ($w) {
            $w->addEventPost('click');
            $this->AddEventListener('click', $widget_name, $cb);
        }
    }

    /**
     * binds a callback for 'change' event on givent widget
     * @param type $widget_name
     * @param \cmc\ui\callable $cb
     */
    public function AddChangeEvent($widget_name, callable $cb) {
        if (!$this->is_dynamic_state()) // no callback in static...
            fatalErrors::trigger(null, 'callback_wrongstate', 2);
        $w = $this->w($widget_name);
        if ($w) {
            $w->addEventPost('change');
            $this->AddEventListener('change', $widget_name, $cb);
        }
    }

    /**
     * binds a callback for processing the 'process' event
     * 
     * @param \cmc\ui\callable $cb
     */
    public function AddProcessEvent(callable $cb) {
        if (!$this->is_dynamic_state()) // no callback in static...
            fatalErrors::trigger(null, 'callback_wrongstate', 2);
        $this->AddEventListener('process', null, $cb);
    }

    /**
     * adds a callback listened for given event on given widget
     * @param type $eventname
     * @param type $widget_name
     * @param \cmc\ui\callable $cb
     */
    public function AddEventListener($eventname, $widget_name, callable $cb) {
        if (!array_key_exists($eventname, $this->_eventListeners)) {
            $this->_eventListeners[$eventname] = array();
        }
        $this->_eventListeners[$eventname][$widget_name][get_class($cb[0]) . '.' . $cb[1]] = $cb;
    }

    /**
     * defines a client side validation callback
     * 
     * the callback function will be used each time the validation state of the frame is changing
     * (and also on first load with all values ready)
     * @param type $cb_name
     */
    public function clientSetValidationCB($cb_name, $timeout=null) {
        if (is_numeric($timeout))
            $timeout = ', '.$timeout;
        $this->_code .= '
            cmc.addValidationListener(\'' . $this->getName() . '\',' . $cb_name . $timeout. ');';
    }

    /**
     * defines a client side keyboard typing callback
     * 
     * @param type $cb_name
     */
    public function clientSetTypingCB($cb_name) {
        $this->_code .= '
            cmc.addTypingListener(\'' . $this->getName() . '\',' . $cb_name . ');';
    }

    /**
     * defines a client side click callback
     * 
     * @param type $widget_name
     * @param type $cb_name
     */
    public function clientAddClickCB($widget_name, $cb_name) {
        $w = $this->w($widget_name);
        if ($w) {
            $w->addEventLocal('click', $cb_name);
        }
    }

    /**
     * defines pending data information
     * 
     * this will ensure a return on the server just after client rendering
     * @param type $pendingData
     */
    public function setPending($pendingData) {
        $this->_bPending = true;
        $this->_pendingdata = $pendingData;
    }

    /**
     * cancels other pending processes
     */
    public function cancelPend() {
        $this->_bCancelPend = true;
    }
    /**
     * retreives if we ask for a new pending request
     */
    public function isPending() {
        return $this->_bPending;
    }

    /**
     * general event handler
     * 
     * dispatches the POST events on callbacks and listeners
     * @param type $view
     * @param type $name
     * @param type $widget
     * @param type $eventData
     */
    public function onEvent($view, $name, $widget, $eventData) {
        switch ($name) {
            case 'click':
                $this->onClick($view, $widget, $eventData);
                break;
            case 'upload':
                $this->onUpload($view, $widget, $eventData);
                break;
            case 'process':
                $this->onProcess($view, $widget, $eventData);
                break;
        }

        $wname = null;
        if ($widget)
            $wname = $widget->getName();
        if ($this->_eventListeners && array_key_exists($name, $this->_eventListeners)) {
            if (array_key_exists($wname, $this->_eventListeners[$name])) {
                foreach ($this->_eventListeners[$name][$wname] as $cb) {
                    if ($widget)
                        call_user_func_array($cb, array($view, $widget, $eventData));
                    else
                        call_user_func_array($cb, array($view, $eventData));
                }
            }
        }
    }

    /**
     * onClick default handler
     * @param type $view
     * @param type $widget
     * @param type $eventData
     */
    public function onClick($view, $widget, $eventData) {
        
    }

    /**
     * onUpload default handler
     * @param type $view
     * @param type $widget
     * @param type $eventData
     */
    public function onUpload($view, $widget, $eventData) {
        
    }

    /**
     * onProcess default handler
     * @param type $view
     * @param type $widget
     * @param type $eventData
     */
    public function onProcess($view, $widget, $eventData) {
        
    }

    /**
     * POST 'done' event
     * 
     * @param type $view
     */
    public function onPOSTDone($view) {
        foreach ($this->_widgets as $w)
            $w->onPOSTDone($view);
    }

    /**
     * called for each event present in the POST request
     * @param type $view
     * @param type $event
     * @return type
     */
    public function POSTfireEvent($view, $event) {
        if (!array_key_exists('name', $event))  // event must have a name at least
            return;
        $name = $event['name'];
        $widget = null;
        if (array_key_exists('widget', $event) && array_key_exists($event['widget'], $this->_widgets))
            $widget = $this->_widgets[$event['widget']];
        $this->onEvent($view, $name, $widget, $event);
    }

    /**
     * called after POST process for preparing POST answer data
     * @param type $view
     * @param type $data
     */
    public function POSTupdatedata($view, $data) {
        foreach ($data as $wname => $wprops)
            if (array_key_exists($wname, $this->_widgets))
                $this->_widgets[$wname]->POSTupdatedata($wprops);
    }

    public function OnSerialize() {
        $this->_sess = null;
        parent::OnSerialize();
    }

}
