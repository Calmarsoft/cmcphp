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
require_once('ui/frame.php');
require_once('core/ui/documentFrame.php');

use cmc\core\cache;
use cmc\core\request;
use cmc\core\ui\documentFrame;
use cmc\error\fatalErrors, cmc\error\runtimeErrors;
use cmc\core\ISerializable;
use cmc\ui\view;

/**
 * The application object model
 * 
 * Must derive from it in order to define application's supported frames.<br>
 * The default implementation integrates the "documentFrame" frame
 * There should be only one app or derived class instance in the program
 * 
 * @copyright     Copyright (c) Calmarsoft company (FRANCE) (http://calmarsoft.com)
 * @link          http://cmc.calmarsoft.com CMC Project
 * @license       http://www.gnu.org/licenses/ GNU General Public License version 3
 * @version       0.9
 */
class app implements ISerializable {

    const APP_appobj = 'APP_appobj';
    const _refreshSecs = 60;

    private $APP_mark;
    protected $dft_Parameters = array(
        'path' => 'main',
        'Encoding' => 'utf-8',
        'lang' => 'en',
        '404_view' => 'errors/404'
    );
    protected $_languages = array(
        'fr' => 'Français',
        'en' => 'English',
        'de' => 'Deutsch'
    );
    private $_defaultFrameClasses = array(
        documentFrame::className
    );
    protected $_404_view = 'errors/404';
    // members regarding serialization
    private $_ranOK;
    private static $_serialkey;
    private static $_theApp;
    private $_towrite;
    private $_lastwrite;
    // static parts of views and their frames
    private $_appframes;
    private $_appviews;
    private $_appviews_ser;
    private $_emptyView;

    /**
     * takes an application instance from session, cache, or creates it
     * 
     * @param string $ClassName the custom application class name (use __CLASS__)
     * @return type
     */
    static function current($ClassName = __CLASS__) {
        if (isset(self::$_theApp))
            return self::$_theApp;

        if (config::APP_cache) {
            self::$_serialkey = self::APP_appobj . md5(request::rootpath_short() . config::APP_guid . (request::isSSL() ? 'S' : ''));
            if (cache::global_exists(self::$_serialkey)) {
                self::$_theApp = cache::global_get(self::$_serialkey);
            }
        }

        $newObject = false;
        if (!isset(self::$_theApp) || !\is_a(self::$_theApp, $ClassName) || (self::$_theApp->APP_mark != cmc::mark())) {
            self::$_theApp = new $ClassName();
            $newObject = true;
        }

        self::$_theApp->initialize();
        if (!$newObject)
            self::$_theApp->OnUnserialize($newObject);

        self::$_theApp->_sess = sess::current(self::$_theApp);

        // guessed object must derive current class
        \assert(\is_subclass_of(self::$_theApp, __CLASS__));


        return self::$_theApp;
    }

    /**
     * initialization after each application "refresh" (retrieval from session or first create)
     */
    protected function initialize() {
        error\runtimeErrors::initialize();
    }

    /**
     * used to mark all views as invalid in the cache
     * 
     * after this all views in the session will be recalculated
     */
    public function invalidateViews() {
        //TODO: check the usage of this function
        foreach (func_get_args() as $viewmatch) {
            $m = preg_grep('/' . $viewmatch . '/', array_keys($this->_appviews_ser));
            foreach ($m as $viewname)
                unset($this->_appviews_ser[$viewname]);

            $m = preg_grep('/' . $viewmatch . '/', array_keys($this->_appviews));
            foreach ($m as $viewname)
                unset($this->_appviews[$viewname]);
        }
        $this->_towrite = true;
    }

    // when serialized with the app
    public function OnSerialize() {
        //    var_dump($this);
        $this->_current_view = null;
        $this->_sess = null;
        $this->_appviews = array();        // individual
        foreach ($this->_appframes as $frame)
            if ($frame['instance'])
                $frame['instance']->OnSerialize();
    }

    public function OnUnserialize() {
        $this->_ranOK = false;
        foreach ($this->_appframes as $frame)
            if ($frame['instance'])
                $frame['instance']->OnUnSerialize();
        $this->_towrite = false;
    }

    /**
     * used to mark current request as OK
     * 
     * this is useful for error handling
     */
    protected function setRanOK() {
        $this->_ranOK = true;
    }

    /**
     * test the 'RanOK' status
     * 
     * @return boolean
     */
    public function bRanOK() {
        return $this->_ranOK;
    }

    /**
     * save views and application data in cache
     */
    private function saveApplication() {
        $this->_emptyView = null;
        // handles the views
        $invalid = array();
        foreach ($this->_appviews as $viewkey => $view) {
            if (!$view->ValidForSave()) {
                array_push($invalid, $viewkey);
                //var_dump("Removing $viewkey !!");
            }
        }
        foreach ($invalid as $invalid_view) {
            $this->invalidateViews($invalid_view);
        }
        foreach ($this->_appviews as $viewkey => $view) {
            if (!array_key_exists($viewkey, $this->_appviews_ser) ||
                    ($this->_appviews_ser[$viewkey]['_towrite']) ||
                    ((microtime(true) - $this->_appviews_ser[$viewkey]['_lastwrite']) > self::_refreshSecs)) {
                $view->onSerialize();
                //echo "SAVE ".$view->getName();var_dump($view);
                cache::global_store(self::$_serialkey . '_' . $viewkey, $view);
                $this->_appviews_ser[$viewkey]['_towrite'] = false;
                $this->_appviews_ser[$viewkey]['_lastwrite'] = microtime(true);
                $this->_towrite = true;
            }
        }

        // now main object
        if ($this->_towrite || (microtime(true) - $this->_lastwrite > self::_refreshSecs))
            try {
                $this->_lastwrite = microtime(true);
                $this->_towrite = false;
                $this->OnSerialize();
                //echo "SAVEG";var_dump($this);
                cache::global_store(self::$_serialkey, $this);
            } catch (Exception $e) {
                $e = $e;    // anti-warning
            }
    }

    /**
     * retrives the list of supported frame classes
     * 
     * @return array
     */
    protected function getFrameClasses() {
        return $this->_defaultFrameClasses;
    }

    /**
     * retieves a parameter in the application
     * 
     * $key can be 'lang', 'path',...    
     * @param string $key
     * @return string
     */
    function dft_Parameter($key) {
        return $this->dft_Parameters[$key];
    }

    /**
     * returns the Language name for a language key
     * 
     * @param string $key
     * @return boolean|string
     */
    function Language($key) {
        if (isset($this->_languages[$key]))
            return $this->_languages[$key];
        return false;
    }

    /**
     * returns the list of language keys
     * @return array
     */
    function getLanguageList() {
        return array_keys($this->_languages);
    }

    function getLanguageUrlRegex() {
        $regex = config::getUrlForm();
        $suppLang = $this->getLanguageList();
        $regexl = '';
        foreach ($suppLang as $slang) {
            if ($regexl)
                $regexl.='|';
            $regexl.=$slang;
        }
        $regex = str_replace('$lang', $regexl, $regex);
        return $regex;
    }

    /**
     * loads a static view from given path and session language
     * @param string $path
     * @return boolean|\cmc\ui\view
     */
    function load_viewbase($path) {
        //var_dump("load $path");
        $view = view::create($this);
        $lang = $this->_sess->getLangName();
        $success = $view->load($lang, $path);
        if ($success)
            return $view;

        unset($view);
        return false;
    }

    protected function __construct() {
        $this->dft_Parameters['path'] = config::my_dft_Path;
        $this->dft_Parameters['404_view'] = config::my_dft_Path;

        $this->APP_mark = cmc::mark();
        $this->_appframes = array();
        $this->_appviews = array();
        $this->_appviews_ser = array();
        $this->_towrite = true;
        $this->addFrames();
    }

    function __destruct() {
        if ($this === app::$_theApp) {
            if (config::APP_cache && $this->_ranOK)
                $this->saveApplication();
        }
    }

    /**
     * retrieves related session object
     * @return type
     */
    public function getSession() {
        return $this->_sess;
    }

    /**
     * initializes the application frames (static frames)
     */
    private function addFrames() {
        foreach ($this->getFrameClasses() as $class)
            $this->_appframes[$class::getId()] = array('ClassName' => $class, 'instance' => null);
    }

    /**
     * retrieves, creates or creates an application's frame (static frames)
     * @param string $frameid
     * @param boolean $reset used to recreate the frame object
     * @return boolean|\cmc\ui\frame
     */
    public function getFrameInstance($frameid, $reset = false) {
        if (array_key_exists($frameid, $this->_appframes)) {
            if ($reset)
                $this->_appframes[$frameid]['instance'] = null;
            if (is_null($this->_appframes[$frameid]['instance']))
                $this->_appframes[$frameid]['instance'] = new $this->_appframes[$frameid]['ClassName']();

            return $this->_appframes[$frameid]['instance'];
        }

        return false;
    }

    /**
     * caculates a valid path from the given path
     * @param string $lang    language or ''
     * @param string $path
     * @return string
     */
    private function getFixPath($lang, $path) {
        $rpath = $this->_sess->getRequest()->testRequestPath($lang, $path);
        return $rpath;
    }

    /**
     * test if a view is allready present in the application
     * @param string $view_pathloc specific view path
     * @return boolean
     */
    public function hasBaseView($view_pathloc) {
        if (array_key_exists($view_pathloc, $this->_appviews_ser) || array_key_exists($view_pathloc, $this->_appviews))
            return true;
        return false;
    }

    /**
     * main view seek and initialization function
     * 
     * find the most appropriate view, or error view for given with paths 
     * then instantiates the view and related frames, and widgets
     * @param string $view_path   regular view path
     * @param string $view_pathloc localized view path
     * @return null|\cmc\ui\view
     */
    public function getRequestBaseView($view_path, $view_pathloc, $lang) {
        $retcode = 200;
        $reload = true;
        $view = null;

        //if ($_SERVER['REQUEST_METHOD']!='POST') echo "getreqv<br>";
        // static views are cached with loc
        if (array_key_exists($view_pathloc, $this->_appviews)) {
            // in cache, we must not cal DefInit
            $view = $this->_appviews[$view_pathloc];
            $reload = false;
        } else {
            //var_dump($view_path);var_dump($view_pathloc);var_dump($lang);
            // need to find a valid path for the view
            $fixpath = $this->getFixPath($lang, $view_path);
            if (!$fixpath) {
                // no view => go to 404 page
                $fixpath = $this->getFixPath($lang, $this->dft_Parameters['404_view']);
                if ($fixpath) {
                    // redirection in error case
                    if (config::ERR_REDIRECT) {
                        if (!$this->_emptyView)
                            $this->_emptyView = view::create($this);
                        $this->_emptyView->setResponseCode(303);
                        if (0 === strcmp($this->dft_Parameters['404_view'], $this->dft_Parameters['path']) || $fixpath === '')
                            $fixpath = request::rootpath();
                        header('Location: ' . $fixpath);
                        return $this->_emptyView;
                    }
                    else {
                        // error management page -> reset viewpath, view_pathloc
                        $view_path = $fixpath;
                        if (config::Multilingual) {
                            $lang = $this->_sess->getLangName();
                            $view_pathloc = $lang . '/' . $view_path;
                        } else
                            $view_pathloc = $view_path;
                        $retcode = 404;
                    }
                } else {
                    fatalErrors::trigger($this->getSession(), fatalErrors::noValidView, 1, $view_path);
                }
            }
            if (config::DFT_REDIRECT && $retcode != 404) {
                if ($this->_sess->getRequestPath() != request::rootpath() . $fixpath) {
                    $this->_emptyView->setResponseCode(303);
                    header('Location: ' . $fixpath);
                    return $this->_emptyView;
                }
            }

            if (array_key_exists($view_pathloc, $this->_appviews_ser) && cache::global_exists(self::$_serialkey . '_' . $view_pathloc)) {
                $view = cache::global_get(self::$_serialkey . '_' . $view_pathloc);
                if (\is_a($view, view::className)) {
                    $view->setApp($this);
                    $view->OnUnserialize();
                    $this->_appviews[$view_pathloc] = $view;
                    $reload = false;
                }
            }
        }
        //if ($view) if ($_SERVER['REQUEST_METHOD']!='POST') echo "view<br>";
        if ($view)
            if ($view->materialChanged()) { /* if material source changed, the frames cache is reset */
                $reload = true;
                $this->addFrames();
            }
        if ($reload) {
            $this->_towrite = true;
            //var_dump("load_viewbase: $view_path");
            $view = $this->load_viewbase($view_path);

            if (!$view)
                return null;

            $this->_appviews[$view_pathloc] = $view;
            // now we need to dress our view with the widget static part
            $view->framesInit();
        }
        $view->setResponseCode($retcode);
        return $view;
    }

    /**
     * runs the current request - either initial or Ajax one
     * @return boolean
     */
    public function run() {

        try {

            $this->_bRanOK = false;
            cmc::$startProcessTime = microtime(true);

            //TODO: put configurable expirity for static views (like 1h for example for blog page)
            // and immmediate expirity for others
            header_remove("Expires");

            //TODO: put the header depending on the view's language
            $langn = $this->_sess->getLangName();
            if ($langn && strlen($langn) > 0)
                header('Content-Language: ' . $langn, true);

            // static, global part: views with frames in it
            $this->_sess->onNewPage();
            $req = $this->_sess->getRequest();
            $redir = config::Request_Filter($req);
            if ($redir) {
                http_response_code(303);
                header('Location: ' . $redir);
                return true;
            }
            $view = $this->_sess->getRequestView();
            $btimeBanner = config::TIME_Banner($req->getPath($this));

            if ($view) {
                if ($btimeBanner)
                    ob_start();
                switch ($req->getMethod()) {
                    case 'GET':
                        $staticCode = $view->getResponseCode();
                        $ok = true;
                        if ($staticCode != 303) {
                            $view->viewUpdate();
                            $new_loc = $view->getRedirect();
                            if ($new_loc) {
                                http_response_code(303);
                                header('Location: ' . $new_loc);
                            } else {
                                http_response_code($staticCode);
                                if (!$req->getAnswerType())
                                    $req->setAnswerType(request::type_html);
                                $ok = $view->renderView($this->_sess);
                            }
                        } else
                            http_response_code($staticCode);

                        if ($ok)
                            $this->setRanOK();
                        /* else 
                          fatalErrors::trigger($this->_sess, "norender"); */
                        break;
                    case 'POST':
                        if (!$view->is_dynamic()) {
                            http_response_code(406);
                            fatalErrors::trigger($this->_sess, 'POST data is not acceptable in this context');
                            die();
                        }
                        ob_start();
                        $this->_sess->multitask_enter();
                        $view->onPOST();
                        ob_clean();
                        $this->_sess->multitask_leave();

                        if ($view->isAjax()) {
                            header('Content-Type: text/json', true);
                            http_response_code($view->getResponseCode());
                            print($view->POSTanswer());
                        } else {
                            if (!$req->getAnswerType())
                                $req->setAnswerType(request::type_html);

                            $new_loc = $view->getRedirect();
                            if ($new_loc) {
                                http_response_code(303);
                                header('Location: ' . $new_loc);
                            } else {
                                $view->renderView($this->_sess);
                            }
                        }
                        $this->setRanOK();
                        break;
                    default:
                        http_response_code(500);
                }
            } else {
                error\fatalErrors::trigger($this->_sess, "noview", 1, $this->_sess->getReqViewPath());
            }
            cmc::$endProcessTime = microtime(true);
            if ($btimeBanner) {
                $parset = (\cmc\cmc::$endCMCTime - \cmc\cmc::$startTime) * 1000;
                $exect = (\cmc\cmc::$endProcessTime - \cmc\cmc::$endCMCTime) * 1000;
                $totalt = $parset + $exect;
                $mem = memory_get_peak_usage(false) / 1024 / 1024;

                header("X-Timings: parse=$parset,execution=$exect,total=$totalt,mem=${mem}MB");
                if ($req->getAnswerType() == request::type_html) {
                    printf('<style>.cmc-timings {float: left;margin-left: 10px;margin-top:0px;}</style>');
                    printf('<br><pre class="cmc-timings">parse time=%.03f ms<br>', $parset);
                    printf('Execution time=%.03f ms, Total=%.03f ms<br>Memory max uage=%.02fMB', $exect, $totalt, $mem);
                }
                ob_end_flush();
            }
            return true;
        } catch (\Exception $e) {
            runtimeErrors::uncaughtException($e);
        }
    }

}

/**
 * easy and global access to current application object
 * @return \cmc\app
 */
function app() {
    return app::current();
}

/**
 * easy and global access to current session object
 * @return \cmc\sess
 */
function sess() {
    return sess::current(app());
}

function qry() {
    return sess()->getRequest();
}
