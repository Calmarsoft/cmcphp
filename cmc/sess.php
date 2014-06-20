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

namespace cmc;

require_once('cmc.php');

use cmc\core\ISerializable;
use cmc\core\translation;
use cmc\core\request;
use cmc\ui\dynview,
    cmc\ui\frame;

/**
 * Default session class
 * 
 * Implementation must derive this class in order to store session specific data like
 * data environment, current user's id, ...
 * 
 * @copyright     Copyright (c) Calmarsoft company (FRANCE) (http://calmarsoft.com)
 * @link          http://cmc.calmarsoft.com CMC Project
 * @license       http://www.gnu.org/licenses/ GNU General Public License version 3
 * @version       0.9
 */
class sess implements ISerializable {

    const SESS_sessobj = 'SESS_sessobj';

    private $_sequence;
    private $SESS_mark;
    private $_translation, $_lang;
    private $_app;
    private $_sesviews;
    private $_dynframes;
    private $_dynframesByName;
    private static $_sess;
    private static $_sess_started;
    private static $_bMultiTask;    
    private $_request;

    /**
     * initialization process: deserizalises the session and embedded data, or creates a new one
     */
    static private function static_init() {
        //TODO: use abstracted plugable variable caching
        $use_memcache = false;

        $req_url = request::requestURI();

        if (config::SESS_save && config::SESS_path($req_url)) {
            session_name('cmc' . md5(request::rootpath_short() . config::APP_guid));
            session_start();
            self::$_sess_started = true;
            self::$_bMultiTask = false;
            register_shutdown_function(array(__CLASS__, 'session_shutdown'));
        } else
            self::$_sess_started = false;

        if ($use_memcache) {
            $memcache_path = 'tcp://localhost:11211/BLABLAA';
            ini_set('session.save_path', $memcache_path);
            ini_set('session.save_handler', 'memcache');
            ini_set('memcache.session_redundancy', 1);
        }
    }

    /**
     * shutdown handler
     */
    static public function session_shutdown() {
        if (self::$_sess)
            self::$_sess->finish();
    }

    /**
     * general function for using the session
     * 
     * @param \cmc\app $app
     * @param string $ClassName
     * @return \cmc\sess
     */
    static public function current($app, $ClassName = __CLASS__) {
        if (isset(self::$_sess) && is_a(self::$_sess, $ClassName)) {
            return self::$_sess;
        }

        if (!isset(self::$_sess)) {   // just in case we change type (not expected!)
            self::static_init();
        }

        if (isset($_SESSION[self::SESS_sessobj])) {
            $_sess = $_SESSION[self::SESS_sessobj];
        }

        if (isset($_sess) && is_a($_sess, $ClassName) && self::$_sess_started && cmc::mark() == $_sess->SESS_mark) {
            self::$_sess = $_sess;
            $_sess->_app = $app;
            $_sess->OnUnserialize();
        } else {
            self::$_sess = new $ClassName($app);
            if (isset($_SESSION[self::SESS_sessobj]))
                unset($_SESSION[self::SESS_sessobj]);
        }
        self::$_sess->initialize();

        return self::$_sess;
    }

    /**
     * init done when the session is created or recovered the first time
     */
    private function initialize() {
        if (!$this->_request)
            $this->_request = new request($this->_app);

        $this->updateLang();
    }

    /**
     * updates current language infomation for the session
     * 
     * This depends on: the base url ( fr/path/to/view ),
     * custom parameters,
     * the 'primary' request language, 
     * default app language,
     */
    private function updateLang() {
        $orglang = '';
        if (isset($this->_lang))
            $orglang = $this->_lang;
        $lang = $orglang;

        $lang = $this->_request->getLangUrl($this->_app);
        if (!isset($lang) || $lang == '')
            $lang = $this->_request->getAcceptPrimaryLang();

        if (!$this->_app->Language($lang))
            $lang = $this->_app->dft_Parameter('lang');

        if ($lang != $orglang || !$this->_translation) {
            $this->_translation = new translation($lang);
            $this->_lang = $this->_translation->getLangName();
        }
        $this->_translation->updateLocale();
    }

    /**
     * returns a view path from current request
     * @return string
     */
    function getViewPath() {
        $path = $this->getReqViewPath();
        if ($path != '' && $path != '/')
            return $path;
        else
            return $this->_app->dft_Parameter('path');
    }

    /**
     * returns a localized view path from current request
     * @return string
     */
    public function getViewPathLoc() {
        $view_path = $this->getViewPath();
        if (config::Multilingual) {
            $lang = $this->getLangName();
            $viewpath_loc = config::buildUrlLang($lang, $view_path);
        } else
            $viewpath_loc = $view_path;

        return $viewpath_loc;
    }

    /**
     * returns a view object from current request
     * 
     * it can be retrieved in the session, application cache or re-instantiated
     * @return \cmc\ui\view
     */
    public function getRequestView() {
        $view_path = $this->getViewPath();
        $view_pathloc = $this->getViewPathLoc();
        $lang  = '';
        if (config::Multilingual) 
            $lang = $this->getLangName();
        //var_dump($view_path, $view_pathloc);
        // if the view is already in our session...
        if (array_key_exists($view_pathloc, $this->_sesviews)) {
            $view = $this->_sesviews[$view_pathloc];
            if (!$view->materialChanged() && ($this->_app->hasBaseView($view_pathloc)) || !\cmc\config::APP_cache) {
                if (!$view->material()) {
                    $statview = $this->_app->getRequestBaseView($view_path, $view_pathloc, $lang);
                    $view->takeMaterial($this, $statview);
                }
                return $view;
            } else {
                if (is_a($view, dynview::className)) {
                    $rmvdynframe = array();
                    foreach ($this->_dynframes as $cmcId => $frame) {
                        if ($view->hasDynSect($cmcId))
                            array_push($rmvdynframe, $cmcId);
                    }
                    foreach ($rmvdynframe as $cmcId) {
                        unset($this->_dynframes[$cmcId]);
                    }
                }
                unset($this->_sesviews[$view_pathloc]);
            }
        }

        $view = $this->_app->getRequestBaseView($view_path, $view_pathloc, $lang);

        if ($view && $view->getResponseCode() != 303) {
            $dyn = dynview::dynview($this, $view);  // dynamic version 
            if ($dyn->ValidForSave() && $dyn->getResponseCode() == 200) // cache only views loading nicely
                $this->_sesviews[$view_pathloc] = $dyn;

            return $dyn;
        } else
            return $view;
    }

    protected function __construct($app) {
        $this->SESS_mark = cmc::mark();
        $this->_app = $app;
        $this->_sesviews = array();
        $this->_dynframes = array();
        $this->_dynframesByName = array();
        $this->_sequence = 0;
    }

    public function getSeq() {
        return $this->_sequence;
    }
    
    
    static public function multitask_enter() {
        if (self::$_sess_started  && !self::$_bMultiTask) {
            session_write_close();
            ob_start();
            self::$_bMultiTask = true;
        }
    }
    static public function multitask_leave() {
        if (self::$_sess_started  && self::$_bMultiTask) {
            session_start();
            self::$_bMultiTask = false;
        }        
    }

    /**
     * actions to make for terminating the session
     */
    private function finish() {
        if (self::$_sess === $this) { //avoid unwanted sessions
            if (self::$_sess_started && $this->_app->bRanOK()) {
                $seq = self::$_sess->_sequence + 1;
                if (!array_key_exists(self::SESS_sessobj, $_SESSION) || /*$_SESSION[self::SESS_sessobj]==null ||*/
                     $seq > $_SESSION[self::SESS_sessobj]->_sequence) {
                    self::$_sess->_sequence++;
                    self::multitask_leave();
                    $_SESSION[self::SESS_sessobj] = self::$_sess;
                    self::$_sess_started = false;
                    $this->OnSerialize();
                    session_write_close();
                } else {
                    // sequence not correct -> no session save
                }
            } else {
                if (self::$_sess_started) {  // unexpected -> session destroy
                    $this->multitask_leave();
                    session_destroy();
                }
                self::$_sess_started = false;
            }
            self::$_sess = null;
        }
    }

    function __destruct() {
        
    }

    /**
     * checks a dynamic frame instance
     * @param string $cmcId
     * @return boolean
     */
    public function dynFrameExist($cmcId) {
        return array_key_exists($cmcId, $this->_dynframes);
    }

    /**
     * adds a new dynamic frame instance in the session
     * 
     * @param string $cmcId the frame id
     * @param \cmc\ui\frame $frame
     */
    public function addNewDynFrame($cmcId, frame $frame) {
        if ($this->dynFrameExist($cmcId))
            return;

        $this->_dynframes[$cmcId] = clone($frame);
        $this->_dynframes[$cmcId]->SetSession($this);
        $this->_dynframes[$cmcId]->OnClone($frame);
        $this->_dynframesByName[$frame->getName()] = $this->_dynframes[$cmcId];
        return $this->_dynframes[$cmcId];
    }

    /**
     * retrieves a dynamic frame instance by Id
     * @param string $cmcId
     * @return null|\cmc\ui\dynframe
     */
    public function getDynFrame($cmcId) {
        if (array_key_exists($cmcId, $this->_dynframes))
            return $this->_dynframes[$cmcId];
        return null;
    }

    /**
     * retrieves a dynamic frame instance by name
     * @param string $cmcId
     * @return null|\cmc\ui\dynframe
     */
    public function getDynFrameByName($name) {
        if (array_key_exists($name, $this->_dynframesByName))
            return $this->_dynframesByName[$name];
        return null;
    }

    /**
     * called on 'newpage' load
     */
    function onNewpage() {
        $this->initialize();
    }

    /**
     * returns current translation object
     * 
     * @return \cmc\core\translation
     */
    function getTranslation() {
        return $this->_translation;
    }

    /**
     * translates a text or key
     * @param string $key
     * @return false|string
     */
    function translate($key) {
        $tr = $this->getTranslation();
        if (!$tr) {
            return false;
        }
        return $tr->getText($key);
    }

    /**
     * linked data enviroment
     * @return \cmc\db\dataenv
     */
    public function getDataEnv() {
        return null;
    }

    /**
     * retrives the view path (unlocalized)
     * 
     * @return string|false
     */
    function getReqViewPath() {
        return $this->_request->getViewPath($this->_app);
    }

    /**
     * retrieves the request path (without REST part)
     * 
     * @param cmc\app $app
     * @return string|false
     */
    function getPathInfo() {
        return $this->_request->getPath($this->_app);
    }

    /**
     * retrieves the request parameters
     * 
     * @param cmc\app $app
     * @return string|false
     */
    function getParams() {
        return $this->_request->getParams();
    }
    /**
     * retrieves a request parameter
     * 
     * @param cmc\app $app
     * @return string|false
     */
    function getParam($paramname) {
        return $this->_request->getParam($paramname);
    }    

    /**
     * retrieves the request object
     * 
     * @return cmc\core\request
     */
    function getRequest() {
        return $this->_request;
    }

    /**
     * retrives current language name
     * @return string
     */
    function getLangName() {
        if (!isset($this->_translation))
            return '';
        else
            return $this->_translation->getLangName();
    }

    public function isEnabled() {
        return self::$_sess_started;
    }
    /**
     * retrives the linked application object
     * @return cmc\app
     */
    public function getApp() {
        return $this->_app;
    }

    // called before serialize
    public function OnSerialize() {
        $this->_app = null;
        $this->_request = null;
        $this->_translation = null;
        //var_dump($this->_sesviews);
        foreach ($this->_dynframes as $frame) {
            $frame->OnSerialize();
        }

        $this->_dynframesByName = null;
        foreach ($this->_sesviews as $sesview) {
            if (!config::SESS_save_mat) {
                $sesview->dropMaterial();
            }
            $sesview->OnSerialize();
        }

        $de = $this->getDataEnv();
        if ($de)
            $de->OnSerialize();
    }

    // called after unserialize
    public function OnUnserialize() {
        //if ($_SERVER['REQUEST_METHOD']!=='POST') var_dump($this);
        $this->_dynframesByName = array();
        foreach ($this->_dynframes as $frame) {
            $frame->OnUnSerialize();
            $frame->setSession($this);
            $this->_dynframesByName[$frame->getName()] = $frame;
        }

        foreach ($this->_sesviews as $sesview) {
            $sesview->setSession($this);
            $sesview->OnUnSerialize();
        }
        //var_dump($this);
    }

}
