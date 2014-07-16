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

/**
 * default configuration options
 */

// disallow fopen with url
ini_set("allow_url_fopen", "0");
// utf-8 for input files
ini_set("default_charset", "UTF-8");
// some session options
ini_set('session.use_trans_sid', '0');
ini_set('session.use_cookies', '1');
ini_set('session.cookie_httponly', '1');

ini_set('session.gc_maxlifetime',   60*60*12);  // 12hours before cleanup
ini_set('session.gc_probability',     1);       // 1% probability of gc test
ini_set('session.gc_divisor',       100);

/**
 * Default configuration options
 * 
 */
abstract class dftconfig {
    // some user options in an associative array
    static $userConfig;
    
    // other options as constants
    /**
     * is a database error fatal, or do we attempt to continue the view's rendering?
     */
    const databaseErrFatal = true;
    /**
     * Action for errors: Fatal, Log, ...
     */
    const errorHandle = error\runtimeErrors::H_Fatal;
    /**
     * Errors to ignore
     * default is E_WARNING
     */
    const errorDefaultMask = E_WARNING; // mask (ignore) warning messages
    /**
     * default language in case of unavailable translation in requested language
     */
    const DFT_translation = 'en';
    /**
     * custom function to define urls for which we need to keep session information
     * returns true if this is a session-bound path
     * @param string $path the request path
     * @return boolean
     */
    static function SESS_path($path) {
        return true;
    }
    /**
     * updates an entry in the user configuration items
     * @param string $key
     * @param string $config
     */
    static function setConfig($key, $config) {
        if (!self::$userConfig)
            self::$userConfig = array();    // put default userconfig options here...
        
        self::$userConfig[$key] = $config;
    }
    /**
     * retrieves an entry in the user configuration items
     * @param type $key
     * @return type
     */
    static function val($key, $dft=null) {
        if (array_key_exists($key, self::$userConfig))
            return self::$userConfig[$key];
        else
            return $dft;
    }
    /**
     * defines the url form as a regular expression, to choose where to place the language part
     * 
     * default is #^(?:/|)($lang)/(.*)# <br>
     * (means lang/path_of_view)
     * @return string
     */
    static function getUrlForm() {        
        return '#^(?:/|)($lang)/(.*)#';
    }
    /**
     * defines how to add a language in a non-localized url
     * 
     * returns the localized url
     * caution must be taken to be consistent with getUrlFrom function
     * @param string $lang
     * @param string $url
     * @return string
     */
    static function buildUrlLang($lang, $url) {
        return $lang.'/'.$url;
    }
    
    /**
     * do we check if the UTF-8 is present in the view, and adds it if necessary ?
     */
    const checkUtf8BOM = true;
    /**
     * custom attribute used to name of the parent material
     */
    const VW_APARENT  = 'data-cmc-parent';
    /**
     * custom attribute for child/parent relationship
     * 
     * in a parent material, defines where to place the child material<br>
     * in a child material, defines the VW_APOSITION value to be used in the parent
     * 
     * example:<br>
     * Child: <div class="cmc-material" data-cmc-id="products" data-cmc-parent="model" data-cmc-position="page_mcontent"><br>
     * Parent: <section class="cmc-sect" data-cmc-position="page_mcontent">	
     */
    const VW_APOSITION='data-cmc-position'; 
    /**
     * custom attribute for defining section or view id
     * 
     * Defines the id of the material, following the view path scheme<br>
     * - can be used to seek for a child material (if not found the current text will be used)
     * - a frame can be associated to it
     * @see \cmc\ui\frame::getId()
     */
    const VW_ACMCID='data-cmc-id';
    /**
     * classname to indicate that this is a subsection
     * 
     * to be used with VW_ACMCID attribute
     */
    const VW_CSECT='cmc-sect';  
    /**
     * classname for advanced material merge: items outside of sections to be <i>merged</i> with the 'master'.
     * 
     * Useful for title, keywords, ... in the <head> section
     */
    const VW_MERGEITEM='cmc-merge';
    /**
     * classname advanced material merge: items outside of sections to be <i>added</i> into the 'master'. 
     * 
     * Useful for additional styles specific to a view ...
     */
    const VW_ADDITEM='cmc-add';     
    /**
     * classname for advanced material merge: items outside of sections to <i>replaced</i> with the 'master'.
     * 
     * Useful for title, keywords, ...
     */
    const VW_REPLACEITEM='cmc-replace';         
      
    /**
     * root location of material files
     */
    const MAT_path = 'views/';     // material path
    /**
     * url form when rewritten (by core\ui\documentFrame class). 
     * false value is not recommended in general, and not supported in REST applications
     * default: true
     */
    const URL_absolute = true;
    /**
     * Application cache option
     * When enabled, the static version of views and frames are kept
     */
    const APP_cache = false;
    /**
     * Session cache option
     * When enabled, saves the session object. This is mandatory for applications
     */
    const SESS_save = true;
    /**
     * Session material part option
     * When enabled, the material (the page) is saved with the session. This consumes some space while avoiding the
     * need of recalculating the Html page in dynframe::viewUpdate
     */
    const SESS_save_mat = false;
    
    /**
     * 'powered by' banner value. 'true' for default content, or a string for a custom content
     */
    const poweredBy = true;
    
    /**
     * used to alter the request (a view from another, redirections, ...)
     * @param \cmc\core\request $req
     */
    static function Request_Filter($req) {        
    }
    /**
     * filters if a material path is valid
     * @param string $path
     * @return boolean
     */
    static function MAT_valid($path) { return true;}
    /**
     * displays the performance banner (timings/memory) depending on the material path
     * @param string $path
     * @return boolean
     */
    static function TIME_Banner($path) { return false;}
    /**
     * @ignore
     */
    static function PoweredBy_Banner($path) { return config::poweredBy;}
    /**
     * materials files extension
     */
    static function getAjaxImagePath() {
        return 'images/ajax-loader.gif';
    }
    const VIEW_EXT = '.html';
    /**
     * materials HTML strictness<br>
     * if equals 0, the HTML text is 'fixed' during parse
     */
    const cmcStrictHTML = 0;
    /**
     * is HTML rendering stripping uneccessary spaces?
     */
    const RenderHtml_Trim = true;
    /**
     * is HTML rendering reformating HTML
     */
    const RenderHtml_Format = true;
    /**
     * if true, all http:// or https:// links in view will be replaced by //
     */
    const FixHtml_httplinks = true;
    /**
     * if true changes jquery version (google CDN) from the running client
     */
    const jQueryFixVersion = true;
    /**
     * automatic place 'min' in case of jQuery CDN detection
     */
    const jQueryAutoMinify = true;
    /**
     * use a 'latest' version
     */
    const jQueryAutoLatest = true;
    /**
     * latest IE7/8 compatible jQuery version
     */
    const jQueryLegacyLatest = '1.11.1';
    /**
     * latest jQuery version
     */
    const jQueryLatest = '2.1.1';    
    /**
     * if true automatically generates delay load of javascript files
     */
    const Javascript_DelayLoad = false;
    /**
     * are javascripts sections put together in bottom?
     */
    const MAT_scriptmove = true;
    /**
     * custom attribute for component script item
     */
    const MAT_scriptcodeid = 'cmc-code'; 
    /**
     * custom attribute of heading part id of the <div> that contains the script
     */
    const MAT_scriptbegin = 'cmc-scripthead'; 
    const MAT_scriptend = 'cmc-scripttail';
    /**
     * class for scripts to be moved to scripttail part
     */
    const MAT_scriptclass = 'latescript';
    /**
     * class for imutable script; items will be kept in the initial place
     */
    const MAT_scriptignclass = 'nomove';        
    /**
     * name of JSON part of POST in case of multipart POST request (typically uploading files)
     * must match javascript component (cmc.js)
     */
    const POST_elem = "cmcdata";
    
    const lc_date = 'd/m/Y';
    
    static function dateToISO($d) {
        if ($d) {
            $result = \DateTime::createFromFormat(\cmc\config::lc_date, $d);
            if ($result) 
                return $result->format('Y-m-d');
        }
        return $d;
    }
    static function dateFromISO($d) {
        if ($d) {
            if ($d==='0000-00-00')
                return null;
            
            $result = \DateTime::createFromFormat('Y-m-d', $d);
            if ($result) {
                return $result->format(\cmc\config::lc_date);
            }
        }
        return $d;        
    }
    
}

