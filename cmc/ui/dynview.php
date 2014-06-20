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

namespace cmc\ui;

use cmc\core\ISerializable;
use cmc\sess;
use cmc\core\request;
use cmc\config;

/**
 * dynamic view
 *
 * @copyright     Copyright (c) Calmarsoft company (FRANCE) (http://calmarsoft.com)
 * @link          http://cmc.calmarsoft.com CMC Project
 * @license       http://www.gnu.org/licenses/ GNU General Public License version 3
 * @version       0.9
 */
class dynview extends view implements ISerializable {

    private $_sess;
    private $_ajax;
    private $_redirect, $_navigate;

    public static function dynview(sess $sess, view $viewbase) {
        $result = new dynview();
        $result->initialize($sess, $viewbase);
        return $result;
    }

    private function __construct() {
        $this->_redirect = null;
        $this->_navigate = null;
    }

    /**
     * initilization is made from a static version of the view
     * 
     * it clones the static view content's into the dynamic version
     * @param \cmc\sess $sess
     * @param \cmc\ui\view $viewbase
     */
    private function initialize(sess $sess, view $viewbase) {
        //echo "org:";var_dump($viewbase);
        $this->_sess = $sess;
        $this->_app = $viewbase->_app;
        $this->_respCode = $viewbase->_respCode;
        $this->_viewname = $viewbase->_viewname;
        $this->_viewLogicPath = $viewbase->_viewLogicPath;
        $this->_currentmaterial = clone($viewbase->_currentmaterial);
        $this->_currentmaterial->onClone($viewbase->_currentmaterial);
        $this->_materials_to_place = null;
        $this->_sections = array();
        $this->_material_mdt = array();
        foreach ($viewbase->_material_mdt as $path => $mdt)
            $this->_material_mdt[$path] = $mdt;
        foreach ($viewbase->_sections as $cmcId => $sect) {
            $frame = $viewbase->getFrame($cmcId);
            if ($frame) {
                if ($frame->is_dynamic()) {
                    $nodepath = $sect->getNodePath();
                    $this->_sections[$cmcId] = $this->_currentmaterial->findXpathNode(null, $nodepath);

                    if (!$this->_sess->dynFrameExist($cmcId)) {
                        $this->_sess->addNewDynFrame($cmcId, $frame);
                    }
                    $sesframe = $this->_sess->getDynFrame($cmcId);

                    $sesframe->viewPreUpdate($this, $sess);
                    $sesframe->viewInitialUpdate($this);
                }
            }
        }
        //echo "fini " . $this->_viewLogicPath; xdebug_print_function_stack();
        //echo "copy result:";var_dump($this);
    }

    public function hasDynSect($cmcId) {
        return array_key_exists($cmcId, $this->_sections);
    }
    /**
     * @return true
     */
    public function is_dynamic() {
        return true;
    }
    
    /**
     * updates the dynamic part of the view by updating the DOM from the properties
     * on each dynamic frame     
     */
    public function viewUpdate() {
        $this->_ajax = false;
        $this->_currentmaterial->setDynamic();
        foreach ($this->_sections as $cmcId => $sect) {
            $frame = $this->_sess->getDynFrame($cmcId);
            if ($frame) {
                if (!$frame->bIsSessionValid($this, $this->_sess))
                    return;
                $frame->viewAttach($this);
                $frame->viewPreUpdate($this, $this->_sess);
            }
        }
        foreach ($this->_sections as $cmcId => $sect) {
            $frame = $this->_sess->getDynFrame($cmcId);
            if ($frame) {
                $frame->viewUpdate($this, $this->_sess);
                $frame->viewPostUpdate($this, $this->_sess);
            }
        }

        $this->_currentmaterial->endStaticScriptCode($this);
    }

    /**
     * called after POST process for preparing POST answer data
     * @param type $data
     * @return type
     */
    public function POSTupdatedata($data) {
        foreach ($data as $frame => $framedata) {
            $f = $this->_sess->getDynFrameByName($frame);
            if ($f) {
                if (!$f->bIsSessionValid($this, $this->_sess))
                    return;
                $f->POSTupdatedata($this, $framedata);
            }
        }
    }

    /**
     * called for each event present in the POST request
     * 
     * this propagates the event on the corresponding frame
     * @param type $event
     * @return type
     */
    public function POSTfireEvent($event) {
        if (!array_key_exists('frame', $event))
            return;
        $f = $this->_sess->getDynFrameByName($event['frame']);
        if (!$f)
            return;
        if (!$f->bIsSessionValid($this, $this->_sess))
            return;
        $f->POSTfireEvent($this, $event);
    }

    /**
     * POST main handling method
     * 
     * decodes the data, calls POSTupdatedata and then POSTfireEvent
     */
    public function onPOST() {
        $this->_ajax = true;
        $postObject = null;
        if (array_key_exists('CONTENT_TYPE', $_SERVER))
            $type = strtok($_SERVER['CONTENT_TYPE'], ';');

        if ($type === 'application/json') {
            $postObject = json_decode(@file_get_contents('php://input'), true, 15);
        } else if ($type === 'multipart/form-data') {
            if (array_key_exists(config::POST_elem, $_POST)) {
                $postObject = json_decode($_POST[config::POST_elem], true, 15);
            }
        } else if ($type === 'application/x-www-form-urlencoded') {
            // 'simple', standard POST form
            $data = array();
            foreach ($_POST as $parm => $val) {
                $frame = strtok($parm, ':');
                $item = strtok(':');
                if ($frame && $item) {
                    if (!array_key_exists($frame, $data))
                        $data[$frame] = array();
                    $data[$frame][$item] = array('value' => $val);
                }
            }
            $postObject = array();
            $postObject['data'] = $data;
            $this->_ajax = false;
        }
        if ($postObject) {
            // first, update properties
            if (array_key_exists('data', $postObject))
                $this->POSTupdatedata($postObject['data']);
            // now trigger events
            if (array_key_exists('event', $postObject))
                $this->POSTfireEvent($postObject['event']);
        }

        if (!$this->_ajax) {
            $this->viewUpdate();
        }
    }
    /**
     * returns if answer is ajax 
     * @return boolean
     */
    public function isAjax() {
        return $this->_ajax;
    }
    /**
     * main POST answer computing method
     * @return type
     */
    public function POSTanswer() {
        // return '{"data":{"test1":{"txt_testresult":{"caption":"COUCOU!!!"}}}}';
        $postresult = array();
        $postresult['data'] = array();
        foreach ($this->_sections as $cmcId => $sect) {
            $f = $this->_sess->getDynFrame($cmcId);
            if ($f) {
                $postElem = $f->getAjaxData($this);
                if ($postElem)
                    $postresult['data'][$f->getName()] = $postElem;
                $postElem = $f->getAjaxProcess();
                if ($postElem)
                    $postresult['process'][$f->getName()] = $postElem;
            } else {
//                var_dump("Impossible de trouver $cmcId", $this, $this->_sess);
            }
        }
        if ($this->_redirect)
            $postresult['redirect'] = $this->_redirect;
        if ($this->_navigate)
            $postresult['navigate'] = $this->_navigate;
        $this->_redirect = null;
        $this->_navigate = null;

        foreach ($this->_sections as $cmcId => $sect) {
            $f = $this->_sess->getDynFrame($cmcId);
            if ($f) {
                $f->onPOSTDone($this);
            }
        }

        return json_encode($postresult);
    }

    /**
     * defines a redirect answer
     * 
     * @param string $new_location
     * @param bool $bReplace if true the original location will be hidden and won't be present in the navigator's history
     */
    public function setRedirect($new_location, $bReplace = false) {
        if ($new_location[0] == '/') {
            $new_location = request::rootpath() . substr($new_location, 1);
        }
        if ($bReplace)
            $this->_redirect = $new_location;
        else
            $this->_navigate = $new_location;
    }

    /**
     * retrieves the redirection address
     * @return string|null
     */
    public function getRedirect() {
        if ($this->_redirect) {
            return $this->_redirect;
        }
        if ($this->_navigate) {
            return $this->_navigate;
        }
        return null;
    }

    /**
     * rebinds session (used after clone from original view)
     * @param type $sess
     */
    public function setSession($sess) {
        $this->_sess = $sess;
    }

    public function dropMaterial() {
        $this->_currentmaterial = null;
    }

    public function takeMaterial($sess, $statview) {
        if (!$this->_currentmaterial) {
            $this->_currentmaterial = clone($statview->_currentmaterial);
            $this->OnUnserialize();
            $this->initialize($sess, $statview);
        }
    }

    // when serialized with the session
    public function OnSerialize() {
        //echo "srial " . $this->_viewLogicPath; xdebug_print_function_stack();
        $this->_sess = null;
        $this->_redirect = null;
        $this->_navigate = null;
        //TODO: consider wiping dynamic views $this->_currentmaterial = null;
        if ($this->_respCode != 404)
            $this->_respCode = 200;    // this is because 404 pages are cached by origin
        parent::OnSerialize();
    }

    public function OnUnserialize() {
        $this->_app = $this->_sess->getApp();
        parent::OnUnserialize();
        //var_dump($this->_sess);
        //var_dump($this);
    }

}
