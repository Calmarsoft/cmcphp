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

namespace cmc\core;

use cmc\cmc;
use cmc\config;
use cmc\error\fatalErrors;

/**
 * handles operations about the request
 *
 * @copyright     Copyright (c) Calmarsoft company (FRANCE) (http://calmarsoft.com)
 * @link          http://cmc.calmarsoft.com CMC Project
 * @license       http://www.gnu.org/licenses/ GNU General Public License version 3
 * @version       0.9
 */
class request {

    /**
     * html content type
     */
    const type_html = 1;

    /**
     * image content type
     */
    const type_image = 2;

    /**
     * correspondance from http content types to a default supported content type
     */
    private static $content_types = array(
        'text/html' => self::type_html,
        '*/*' => self::type_html,
        'image/webp' => self::type_image,
        'image/jpeg' => self::type_image, 'image/png' => self::type_image, 'image/*' => self::type_image);
    private $_app;
    private $_params;
    private $_reqContentMatch;
    private $_reqContent;
    private $_answerContent;
    /**
     * some global properties about the request
     */

    /**
     * main physical path
     * @internal
     */
    static protected $ROOT_physpath;

    /**
     * main path seen from client
     * @internal
     */
    static protected $ROOT_path;

    /**
     * request URI
     */
    static protected $Request_URI;

    /**
     * same as ROOT_path but without begining or trailing '/'
     * @internal
     */
    static protected $ROOT_path_short;

    /**
     * gets the filtered server value
     * turns around defective filter_input function
     * @internal
     */
    private static function getServerVal($key) {
        $s = \filter_input(INPUT_SERVER, $key);
        if ($s == null) {
            if (array_key_exists($key, $_SERVER)) {
                $s = \filter_var($_SERVER[$key]);
            }
        }
        return $s;
    }

    /**
     * physical path of the application's root. This is taken from the "main" php script path.
     */
    static function rootphyspath() {
        if (!self::$ROOT_physpath) {
            $s = self::getServerVal('ORIG_SCRIPT_FILENAME');
            if (!$s)
                $s = self::getServerVal('SCRIPT_FILENAME');
            if (!$s) {
                fatalErrors::trigger(null, 'noscriptname1', 1);
            }
            self::$ROOT_physpath = \dirname($s);
        }
        return self::$ROOT_physpath;
    }

    /**
     * logical root path of the application; i.e. the path known to the client
     */
    static function rootpath() {
        if (!self::$ROOT_path) {
            $s = self::getServerVal('ORIG_SCRIPT_NAME');
            if (!$s)
                $s = self::getServerVal('SCRIPT_NAME');
            if (!$s) {
                fatalErrors::trigger(null, 'noscriptname2', 1);
            }

            self::$ROOT_path = \dirname($s);
            if (self::$ROOT_path != '/')
                self::$ROOT_path.='/';
        }
        return self::$ROOT_path;
    }

    /**
     * request uri (cached)
     */
    static function requestURI() {
        if (!self::$Request_URI) {
            self::$Request_URI = self::getServerVal('REQUEST_URI');
        }
        return self::$Request_URI;
    }

    /**
     * same as request::rootpath() but without begining or trailing '/'
     */
    static function rootpath_short() {
        if (!self::$ROOT_path_short) {
            $s = self::rootpath();
            if (preg_match("|^/?(.*)/|", $s, $m))
                $s = $m[1];
            self::$ROOT_path_short = $s;
        }
        return self::$ROOT_path_short;
    }
    
    /**
     * relocates a path from root and view path
     * @param type string
     */
    public function relocate_path($url, $viewpath, $matpath = config::MAT_path, $absolute = config::URL_absolute) {
        //echo 't: '; var_dump($url, $reqpath, $viewpath);
        if ($url === '')
            return false;

        $reqpath = $this->getPath();
        /**
         * replaces https:// or http:// by //
         */
        if (preg_match('!^//.*!', $url))
            return false;
        if (preg_match('!^http(s)|()://.*!', $url)) {
            if (\cmc\config::FixHtml_httplinks)
                $url = substr($url, strpos($url, ':') + 1);
            return $url;
        }
       
        // for absolute links, re-root it
        if ($url !== '' && $url[0] === '/')
            return self::rootpath() . substr($url, 1);

        /**
         * replaces ../ used in views by root referenced links
         */
        $depth = substr_count(substr($viewpath, 1), '/') + 1;
        $rdepth = substr_count(substr($reqpath, 1), '/') + 1;
        while (preg_match('!^\.\./.*!', $url) === 1 && ($depth > 0)) {
            $url = substr($url, 3);
            $depth--;
            $rdepth--;
        }
        // the reference is in view tree
        if ($depth !== 0) {
            if ($matpath !== '/')
                $url = $matpath . $url;
        }

        if ($absolute) {
            return self::rootpath() . $url;
        } else {

            for ($i = 0; $i < $rdepth - $depth; $i++) {
                $url = '../' . $url;
            }
            //echo '--> ';var_dump($url);
            return $url;
        }
        /*
          if ($depth < self::$matpath_len) {
          if (self::$matrepaste == null) {
          self::$matrepaste = array();
          $idx = self::$matpath_len;
          $repaste = strtok($matpath, '/') . '/';
          for (; $idx > 0; $idx--) {
          self::$matrepaste[$idx] = $repaste;
          $repaste .= strtok('/');
          }
          }
          $repaste = self::$matrepaste[self::$matpath_len - $depth];
          } else
          $repaste = '';

          return $repaste . $url;
         */
    }

    public function __construct($app) {
        $this->_app = $app;
    }

    /**
     * finds a valid path from the provided path and language
     *
     * @param string view path
     * @param string language
     * @deprecated
     */
    private function seekViewPath($path, $lang) {
        $matpath = config::MAT_path;
        $viewExt = config::VIEW_EXT;

        if ($path[0] == '/')
            $path = substr($path, 1);

        if (!config::MAT_valid($path))
            return false;

        do {
            if ($lang != '')
                $fix_path = $lang . '/' . $path;
            else
                $fix_path = $path;

            $loc_path = realpath($matpath . $fix_path . $viewExt);
            $success = file_exists($loc_path);
            if ($success) {
                $fix_path = $path;
                $loc_path = realpath($matpath . $fix_path . $viewExt);
                $success = file_exists($loc_path);
            }
            if (!$success)
                $path = dirname($path); // remove last part of the path...
        } while (!$success && $path && $path != '.');

        if (!$success)
            return false;

        return $fix_path;
    }

    /**
     * query complete request path (without parameters)
     */
    private function getFullPath() {
        $uri = self::requestURI();
        if ($uri) {
            $result = strtok($uri, '?');
            cmc::str_truncprefix($result, request::rootpath());
            return $result;
        } else
            return false;
    }

    /**
     * tests if a path is illegible
     * @param string language
     * @param string path
     */
    public function testRequestPath($lang, $path) {
        $orgpath = $path;
        $antibug = 0;
        do {
            if ($lang != '')
                $fix_path = config::buildUrlLang($lang, $path);
            else
                $fix_path = $path;
            // tries localized, then non localized (default localization)
            $loc_path = realpath(config::MAT_path . $fix_path . config::VIEW_EXT);
            $success = file_exists($loc_path);
            if (!$success) {
                $fix_path = $path;
                $loc_path = realpath(config::MAT_path . $fix_path . config::VIEW_EXT);
                $success = file_exists($loc_path);
            }
            if (!config::MAT_valid($path))
                return $success;
            if (!$success && is_string($path)) {
                $path = dirname($path); // remove last part of the path...                
            }
            $antibug++;
            if ($antibug > 100) {
                var_dump($orgpath);
                xdebug_print_function_stack();
                die();
            }
        } while (!$success && is_string($path) && $path !== '' && $path !== '.');

        if (!$success)
            return false;

        return $path;
    }

    private $_reqPath = null, // without 'parameters' path
            $_reqViewPath, // without 'parameters' and 'lang' path
            $_reqLang, // language in request
            $_reqParams, $_reqREST;

    
    /**
     * calcules parts from the request
     * 
     * this function will extract: base path, view path (unlocalized), language, REST and regular parameters
     * @return boolean
     */
    private function calcRequestPath() {
        if ($this->_reqPath == null) {
            $basepath = $this->getFullPath();   // full path (can include REST part)
            $this->_reqViewPath = $basepath;
            $this->_reqPath = $basepath;
            $this->_reqLang = '';
            $this->_reqREST = '';
            if (config::Multilingual) {
// config tells where is the language part in the url
                $suppLang = $this->_app->getLanguageList();
                $regex =  $this->_app->getLanguageUrlRegex();                
                $match = array();
                if (preg_match($regex, $basepath, $match)) {
                    $imatch_lang = in_array($match[1], $suppLang) ? 1 : 2;
                    $this->_reqLang = $match[$imatch_lang];
                    $this->_reqViewPath = '';
                    for ($i = 1; $i < count($match); $i++) {
                        if ($i != $imatch_lang) {
                            if ($this->_reqViewPath != '' && $match[$i][0] != '/')
                                $this->_reqViewPath .= '/';
                            $this->_reqViewPath .= $match[$i];
                        }
                    }
                }
            }
            if ($this->_reqViewPath === '') {
                $this->_reqViewPath = $this->_app->dft_Parameter('path');
            }
// now we have the language, and a supposed view
// need to try to find the view part of it
            $path = $this->testRequestPath($this->_reqLang, $this->_reqViewPath);
            if (!$path) {
                //echo "test fail ".$this->_reqLang."--".$this->_reqViewPath;
                return false;
            }
            $plen = strlen($path);
            $RESTstr = '';
            if ($plen < strlen($this->_reqViewPath))
                $RESTstr = substr($this->_reqViewPath, $plen + 1);
            $this->_reqViewPath = $path;
            $this->_reqParams = $_REQUEST;
            $this->_params = $this->_reqParams;
            $i = 0;
            if ($RESTstr!=='') {
                foreach (preg_split('|/|', $RESTstr) as $rest) {
                    $this->_reqREST[$i] = $rest;
                    $this->_params[$i] = $rest;
                    $i = $i + 1;
                }
            }
            /*    var_dump("calc req: $basepath, reqPath:".$this->_reqPath.", viewpath: ".$this->_reqViewPath.
              ", lang:".$this->_regLang.
              ", REST:".$this->_reqREST.
              ", params: ".$this->_reqParams); */
        }
        return true;
    }

    /**
     * retrives the view path (unlocalized)
     * 
     * @return string|false
     */
    public function getViewPath() {
        if (!$this->calcRequestPath())
            return false;
        return $this->_reqViewPath;
    }

    /**
     * retrieves the request path (with REST part)
     * 
     * @return string|false
     */
    public function getPath() {
        if (!$this->calcRequestPath())
            return false;
        return $this->_reqPath;
    }

    /**
     * retrieves the request path (with REST part and with prefix)
     * 
     * @return string|false
     */
    public function getReqPath() {
        if (!$this->calcRequestPath())
            return false;
        return self::rootpath() . $this->_reqPath;
    }

    /**
     * retrieves the request parameters
     * 
     * @return string|false
     */
    public function getReqParams() {
        if (!$this->calcRequestPath())
            return false;
        return $this->_reqParams;
    }

    /**
     * retrieves the request REST parameters
     * 
     * @return string|false
     */
    public function getRESTItems() {
        if (!$this->calcRequestPath())
            return false;
        return $this->_reqREST;
    }

    /**
     * retrieves both REST and standard parametrs
     * 
     * @return string|false
     */
    public function getParams() {
        if (!$this->calcRequestPath())
            return false;
        return $this->_params;
    }

    /**
     * retrieves a parameter from REST and standard parametrs
     * 
     * @param type $parmname
     */
    public function getParam($parmname) {
        $result = null;
        $parms = $this->getParams();
        if (array_key_exists($parmname, $parms)) {
            $result = $parms[$parmname];
        }        
        return $result;
    }

    public function getLangUrl() {
        $this->calcRequestPath();
        return $this->_reqLang;
    }

    /**
     * what kind of file/media/document is accepted by the client
     * @retrun string
     */
    public function getAccept() {
        $result = self::getServerVal('HTTP_ACCEPT');
        if (!$result || $result == '')
            $result = '*/*';
        return $result;
    }

    /*
     * analysze what is to be returned in prority
     * @param $pref: can be the required answer type (returns false if this type is not accepted)
     */

    private function anaContentType($pref = false) {
        $accept = preg_split('/, */', $this->getAccept());
        $accept_v = array();
        foreach ($accept as $i=>$val) {
            $match=array(); 
            if (preg_match('%^([a-z+*/]+);q=(1|(?:0.[0-9]+))$%', $val, $match)) {
                $accept[$i] = $match[1];
                $accept_v[$i] = floatval($match[2]);
            }
            else
                $accept_v[$i] = 1;
        }
        $this->_reqContent = false;
        $acceptk = array_combine($accept, $accept_v);
        
        $best = 0;
        foreach (self::$content_types as $atype => $typeval) {
            if (array_key_exists($atype, $acceptk)) {
                // if '*/*' matches, $pref is no imporance for acceptance
                if (!$pref || (is_integer($pref) && ($pref == $typeval || $atype=='*/*')) 
                           || (is_string($pref)  && ($pref == $atype)))
                    if ($best < $acceptk[$atype]) {
                        if (!$pref)
                            $this->_reqContent = $typeval;
                        else
                            $this->_reqContent = $pref;
                        $this->_reqContentMatch = $atype;
                        $best = $acceptk[$atype];
                    }
            }
        }
        /*xdebug_print_function_stack();
        var_dump($pref, self::$content_types, $acceptk);
        var_dump($best, $this->_reqContent, $this->_reqContentMatch);*/
    }

    /**
     * retrieves the contentype to return from a preference
     * 
     * for example self::type_html
     * @param integer|false $pref preferred content type, or false if none
     * @return integer content type
     */
    public function getContentType($pref = false) {
        if (!isset($this->_reqContent))
            $this->anaContentType($pref);
        return $this->_reqContent;
    }

    /**
     * retrieves the contenmatch to return from a preference
     * 
     * for example 'image/png'
     * @param integer|false $pref preferred content type, or false if none
     * @return string content match
     */
    public function getContentMatch($pref = false) {
        if (!isset($this->_reqContent))
            $this->anaContentType($pref);
        return $this->_reqContentMatch;
    }

    /**
     * sets an answer type (like 'image/png') from an answer type
     * or false if none is accepted by the client
     * 
     * @param integer the content type
     * @return boolean
     */
    public function setAnswerType($type) {
        if ($type != $this->getContentType($type) && $this->getContentMatch() != '*/*')
            return false;
        $this->_answerContent = $type;
        return true;
    }

    /**
     * retrieves the selected answer type
     * 
     * @return string
     */
    public function getAnswerType() {
        return $this->_answerContent;
    }

    /**
     * renders a image answer according to client needs
     * 
     * @param binary image data
     */
    public function renderImage($img) {
        if ($this->getAnswerType() != self::type_image)
            return;

        $imgtype = $this->getContentMatch();
        if ($imgtype == 'image/png' || $imgtype == '*/*') {
            header("Content-type: image/png");
            imagepng($img);
        } else if ($imgtype == 'image/webp' && function_exists('imagewebp')) {
            header("Content-type: image/webp");
            imagewebp($img);
        } else {
            imageinterlace($img);
            header("Content-type: image/jpeg");
            imagejpeg($img);
        }
    }

    /**
     * retrieves the "acceptLanguage" item
     * 
     * @return string
     */
    static public function getAcceptLanguage() {
        $lang = self::getServerVal('HTTP_ACCEPT_LANGUAGE');
        return $lang;
    }
    
    private static $_moz_version;
    private static $_client_info;
    /**
     * retrives the navigator information
     */
    static public function getClientInformation() {
        $nav = self::getServerVal('HTTP_USER_AGENT');
        $m=array();
        if (self::$_client_info==null) {
            $valid = preg_match('|^Mozilla/([0-9]+\.[0-9]+) [(](.+)$|', $nav, $m);
            if ($valid) {
                self::$_moz_version = $m[1];
                self::$_client_info = preg_split("/(; )|[)]/", $m[2]);
            } else {
                self::$_moz_version = -1;
                self::$_client_info = array();
            }
        }
        return self::$_client_info;
    }
    
    static public function getClientMozVersion() {
        if (self::$_moz_version==null)
            self::getClientInformation();
        return self::$_moz_version;
    }
    
    private static $_IEVersion;
    static public function getClientIEVersion(){
        if (self::$_IEVersion==null) {
            $cltinfo = self::getClientInformation();
            $ieinfo = preg_grep('/^MSIE ([0-9]+.[0-9])+$/', $cltinfo);
            if ($ieinfo && count($ieinfo)==1)
                self::$_IEVersion = substr($ieinfo[1], 5);
            else
                self::$_IEVersion = -1;
        }
        return self::$_IEVersion;
    }

    /**
     * retrieves a preferred language 
     * 
     * @return string|false
     */
    static public function getAcceptPrimaryLang() {
        $accept = preg_split('/,/', strtok(self::getAcceptLanguage(), ';'));
        if (!$accept || !is_array($accept) || count($accept) == 0)
            return false;

        return strtok($accept[0], '-');
//return \locale::getPrimaryLanguage(\Locale::acceptFromHttp($this->getAcceptLanguage()));
    }

    /**
     * retrieves the request method (POST, GET, PUT, ...)
     * 
     * @return string
     */
    public function getMethod() {
        $method = self::getServerVal('REQUEST_METHOD');
        return $method;
    }

    /**
     * is this request a POST?
     * @return boolean
     */
    public function isPost() {
        return $this->getMethod() === 'POST';
    }

    /**
     * is this request a GET?
     * @return boolean
     */
    public function isGet() {
        return $this->getMethod() === 'GET';
    }

    /**
     * test if connection is secure
     * @return boolean
     */
    public static function isSSL() {
        return self::getServerVal('HTTPS') !== null ||
                self::getServerVal('HTTP_HTTPS') !== null;
    }

    /**
     * is this request containing upload?
     * @return boolean
     */
    public function hasUpload() {
        return isset($_FILES) && (count($_FILES) > 0);
    }

}
