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

require_once('widgets/widget.php');

use cmc\ui\widgets\widget;
use cmc\app, cmc\sess;

/**
 * frame is a container of other frames or widgets
 * and linked to html view
 *
 * @copyright     Copyright (c) Calmarsoft company (FRANCE) (http://calmarsoft.com)
 * @link          http://cmc.calmarsoft.com CMC Project
 * @license       http://www.gnu.org/licenses/ GNU General Public License version 3
 * @version       0.9
 */
abstract class frame {
    protected $_widgetdef = array();
    protected $_widgets;      // linked widgets
    protected $_sourcePath=null;
    protected $_code='';

    /**
     * returns the Id of the frame in the view<br>
     * this value must match a section id in the view
     * 
     * @see \cmc\dftconfig::VW_ACMCID 
     * @return string|null
     */
    static public function getId() { return null;}

    public function __construct() {
        $this->_widgets = array();
        $this->initialize();
    }
    /**
     * adds a new widget in the frame
     * @param type $name
     * @param \cmc\ui\widgets\widget $obj
     * @return \cmc\ui\widgets\widget
     */
    public function addWidget($name, widget $obj) {
        $this->_widgets[$name] = $obj;
        $obj->setFrame($this);
        $obj->setName($name);
        return $obj;
    }

    public function initialize() {
        foreach($this->_widgetdef as $wname => $wdef)
        {
            $factory = $wdef[0];
            array_shift($wdef);
            $obj = $factory::makewidget($this, $wdef[0]);
            $this->addWidget($wname, $obj);
        }
    }
    /**
     * 'short' widget access function
     * @param string $widget_name
     * @return \cmc\ui\widget
     */
    public function w($widget_name) {
        if (array_key_exists($widget_name, $this->_widgets))
            return $this->_widgets[$widget_name];
        return null;
    }
    /**
     * not dynamic ...
     * @return false
     */
    public function is_dynamic() {
        return false;
    }
    /**
     * true if in dynamic state: dynamic and linked to a session
     */
    public function is_dynamic_state() {
        return false;       
    }
    /**
     * successors must implement a name
     */
    abstract public function getName();

    /**
     * preparing initial static update
     * @param type $view
     */
    public function viewPreStaticUpdate($view) {
        foreach ($this->_widgets as $w) {
            $w->viewLoaded($view);
        }
    }
    /**
     * main static update
     * override this function to process components before it is cached in the application
     * - called each time the view must be entirely calculated (i.e: not in application or in session material cache)
     * - APP_cache, SESS_save and SESS_save_mat parameter change the cases when this function is called
     * @param cmc\view $view
     */
    public function viewStaticUpdate($view) {
    }
    /**
     * first dynamic update on view
     * override this function to process components before material is processed from the application cache into the session cache
     * - called each time the session material is intialized (warning, this may be during POST if SESS_save_mat=false)
     * - APP_cache, SESS_save and SESS_save_mat parameter change the cases when this function is called
     * @param cmc\view $view
     */
    public function viewInitialUpdate($view) {        
    }
    /**
     * after static update
     * @param cmc\view $view
     */
    public function viewPostStaticUpdate($view) {
        foreach ($this->_widgets as $w) {
            $w->viewInitialUpdate($view, $this);
        }
    }
    /**
     * gets the frame's scripting code
     * @return type
     */
    public function getScriptCode() {
        return $this->_code;
    }      
   /**
    * is the session still valid ?
    * 
    * if not the application may return to a fallback page
    * @param type $view
    * @param type $sess
    * @return boolean
    */
    public function bIsSessionValid($view, $sess) {
        return true;
    }
    
    /**
     * path of php source code
     * @return type
     */
    public function getSourcePath()
    {
        return $this->_sourcePath;
    }

    // when serialized with the app
    public function OnSerialize() {
        foreach ($this->_widgets as $w)
            $w->OnSerialize();
        $this->_code = '';
    }

    public function OnUnserialize() {
        foreach ($this->_widgets as $w) {
            $w->setFrame($this);
            $w->OnUnserialize();
        }
    }

    public function OnClone($srcinstance) {        
        $this->_widgets = array();
        $this->_code = '';
        foreach ($srcinstance->_widgets as $wn => $w)
        {
            $new_w = clone($w);
            $new_w->OnClone($w);
            $new_w->SetFrame($this);
            $this->_widgets[$wn] = $new_w;
        }
    }
    
    /* racourcis 
     */

    /**
     * shortcut for current 
     */
    public function qry() {
        $this->sess()->getRequest();
    }
    /**
     * shortcut for current application
     * @return type
     */
    public function app() {
        return app::current();
    }
    /**
     * shortcut for current session
     * @return type
     */
    public function sess() {
        $app = app::current();
        return sess::current($app);
    }
    /**
     * shortcut for current data environment
     * @return type
     */
    public function dtaenv() {
       return $this->sess()->getDataEnv();
    }
    /**
     * shortcut for a datasource of the data environment
     * @param type $dsName
     * @return null
     */
    public function datasource($dsName) {
        $dte = $this->dtaenv();
        if (!$dte) {
            return null;
        }
        return $dte->getQueryDS($dsName);
    }
    /** 
     * shortcut for a datasource's first record
     * @param type $dsName
     * @param type $params
     * @return null
     */
    public function dataSourceFirst($dsName, $params=false) {
        $dte = $this->dtaenv();
        if (!$dte) {
            return null;
        }
        return $dte->getQueryFirst($dsName, $params);
    }
    /**
     * shortcut for datasource execution
     * @param type $dsName
     * @param type $params
     * @return null
     */
    public function dataSourceExec($dsName, $params=false) {
        $dte = $this->dtaenv();
        if (!$dte) {
            return null;
        }
        return $dte->queryExecute($dsName, $params);
    }

    
}

