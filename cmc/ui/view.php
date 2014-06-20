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

require_once('cmc/core/ui/material.php');
require_once('cmc/app.php');

use cmc\core\ISerializable;
use cmc\core\ui\material;
use cmc\core\request;
use cmc\app,
    cmc\sess,
    cmc\config,
    cmc\error\fatalErrors;

/**
 * base document of actual page
 * 
 * it is loaded from different materials, as defined by special tags
 *
 * @copyright     Copyright (c) Calmarsoft company (FRANCE) (http://calmarsoft.com)
 * @link          http://cmc.calmarsoft.com CMC Project
 * @license       http://www.gnu.org/licenses/ GNU General Public License version 3
 * @version       0.9
 */
class view implements ISerializable {

    protected $_app;
    protected $_respCode = 200;
    protected $_currentmaterial;
    protected $_viewname;
    protected $_sections;
    private $_sections_ser;
    protected $_viewLogicPath;
    protected $_frames;
    protected $_material_mdt;
    private $_materials_to_place;
    private $_imgData;

    const className = __CLASS__;

    /**
     * replaces/moves/insert some items from child materials to master material
     * 
     * @param \cmc\core\ui\material $parent
     * @param type $className
     * @param type $replace
     * @param type $add
     */
    private function updReplaceMasterItems(material $parent, $className, $replace, $add = false) {
        // update "merge" items
        $mergeitems = $this->_currentmaterial->findClassNodes($className);
        foreach ($mergeitems as $mitem) {
            if (!$add) {
                // search for corresponding item in the parent frame
                if ($mitem->tagName == 'meta') {
                    $pitem = null;
                    $mattr = $mitem->getAttribute('name');
                    if ($mattr) {
                        $pitems = $parent->findXpathNodes(null, $mitem->parentNode->getNodePath().'/meta');
                        foreach ($pitems as $pmeta) {
                            $pmetaname = $pmeta->getAttribute('name');
                            if ($pmetaname && $pmetaname == $mattr)
                                $pitem = $pmeta;
                        }
                    }
                } else
                    $pitem = $parent->findXpathNode(null, $mitem->getNodePath());

                if ($pitem) {
                    if (!$replace)
                        $pitem->nodeValue .= $mitem->nodeValue;
                    else
                        $pitem->nodeValue = $mitem->nodeValue;

                    foreach ($mitem->attributes as $mattr) {
                        $newattr = $mattr->value;
                        if ($mattr->name == 'class')
                            $newattr = preg_replace('|\b' . $className . '\b|', '', $newattr);
                        if ($mattr->name == 'name')
                            continue;

                        if (!$replace) {
                            if ($pitem->hasAttribute($mattr->name)) {
                                if ($mattr->name == 'class' && $newattr != '')
                                    $newattr = ' ' . $newattr;
                                $newattr = $pitem->getAttribute($mattr->name) . $newattr;
                            }
                        }
                        if ($newattr != '')
                            $pitem->setAttribute($mattr->name, $newattr);
                        else
                            $pitem->removeAttribute($mattr->name);
                    }
                }
            } else {
                // add a node to the parent
                if ($mitem->parentNode) {
                    // finds the parent item in the parent frame
                    $ppitem = $parent->findXpathNode(null, $mitem->parentNode->getNodePath());
                    if ($ppitem) {
                        $ref = null;
                        $exists = $parent->findXpathNodes($ppitem, $mitem->tagName);
                        if ($mitem->tagName === 'style' && (!$exists || $exists->length == 0))
                            $exists = $parent->findXpathNodes($ppitem, 'link');
                        if ($exists && $exists->length > 0)
                            $ref = $exists->item($exists->length - 1);
                        if ($ref !== null)
                            $ref = $ref->nextSibling;

                        // now copy the whole original node
                        $copy = $ppitem->ownerDocument->importNode($mitem, true);
                        if ($ref !== null)
                            $ppitem->insertBefore($copy, $ref);
                        else
                            $ppitem->appendChild($copy);
                    }
                }
            }
        }
    }

    /**
     * if a parent model is requested, load, and store it in 'materials_to_place', indexed by cmc-id
     */
    private function load_ParentModels($lang) {
        $result = 0;
        // seek if a parent model is present
        $material = $this->_currentmaterial->findCMCParentNode();
        if ($material) {
            $parentname = $this->_currentmaterial->peekAttribute($material, config::VW_APARENT);
            $pos = $material->getAttribute(config::VW_APOSITION);
            $material->removeAttribute(config::VW_APOSITION);
            $mat_id = $material->getAttribute(config::VW_ACMCID);
            // cmc-id is for consistency
            // load the parent material
            $parentframe = material::load($lang, $parentname);
            if ($parentframe) {
                // parent will now be the current material, mark previous to be inserted at 'pos'
                $this->_materials_to_place[$pos] = $material;

                $this->updReplaceMasterItems($parentframe, config::VW_MERGEITEM, false);
                $this->updReplaceMasterItems($parentframe, config::VW_REPLACEITEM, true);
                $this->updReplaceMasterItems($parentframe, config::VW_ADDITEM, false, true);

                $this->_material_mdt[$parentframe->getphyspath()] = $parentframe->getmtime();
                $this->_currentmaterial = $parentframe;
                $result++;
            }
        }
        return $result;
    }

    /**
     * we now have the master material, so we actually can
     * place the child materials in their respective positions
     * @return int numer of placed materials
     */
    private function load_PlaceMaterials() {
        $result = 0;
        foreach ($this->_materials_to_place as $pos => $material) {
            $replacepos = $this->_currentmaterial->findAttributeValueNodes(config::VW_APOSITION, $pos);
            if ($replacepos) {
                assert(is_a($replacepos, 'DOMNodeList'));
                assert($replacepos->length == 1);
                //$material->removeAttribute(config::VW_ACMCID);
                $replacepos->item(0)->removeAttribute(config::VW_APOSITION);
                $this->_currentmaterial->replace($replacepos->item(0), $material);
                unset($this->_materials_to_place[$pos]);
                $result++;
            }
        }
        return $result;
    }

    /**
     * loads the sub materials
     * @param type $lang
     * @return int
     */
    private function load_SubSections($lang) {
        $result = 0;
        // handles sub sections
        $dynmaterials = $this->_currentmaterial->findClassNodes(config::VW_CSECT);
        foreach ($dynmaterials as $dmat) {
            $mat_id = $dmat->getAttribute(config::VW_ACMCID);
            $mat_lang = $dmat->getAttribute('lang');
            $matframe = material::load($lang, $mat_id, $mat_lang);
            if ($matframe) {
                //echo "chargement '$matpath' <br>";
                $matnode = $matframe->findAttributeValueNodes(config::VW_ACMCID, $mat_id);
                assert(is_a($matnode, 'DOMNodeList'));
                assert($matnode->length == 1);
                if ($matnode->length == 1) {
                    $newnode = $matnode->item(0);                    
                    foreach ($dmat->attributes as $mattr) {
                        $name = $mattr->name;
                        if ($name===config::VW_CSECT || $name==config::VW_ACMCID) 
                            continue;
                        if (!$newnode->hasAttribute($name))
                            $newnode->setAttribute($name, $mattr->value);
                        else if ($name==='class')
                            $newnode->setAttribute($name, $newnode->getAttribute($name). ' ' . $mattr->value);
                    }                      
                    $newnode->removeAttribute(config::VW_ACMCID);
                    $this->_currentmaterial->replace($dmat, $newnode, true);
                    $this->_material_mdt[$matframe->getphyspath()] = $matframe->getmtime();
                    $result++;
                }
            } else {
                //echo "non trouvé: '$matpath' pour '$mat_id'<br>";
            }
        }
        return $result;
    }

    /**
     * @return true
     */
    public function is_dynamic() {
        return false;
    }

    /**
     * material load entry point
     * 
     * the function will take care of material links using above private subroutines
     * @param type $lang
     * @param type $path
     * @return boolean
     */
    public function load($lang, $path) {
        // chargement initial
        assert(strlen($path) > 0);

        if ($path[0] == '/')
            $path = substr($path, 1);
        $this->_viewname = $path;

        $this->_currentmaterial = material::load($lang, $path);
        if (!$this->_currentmaterial)
            return false;
        $this->_material_mdt[$this->_currentmaterial->getphyspath()] = $this->_currentmaterial->getmtime();
        if (config::Multilingual)
            $this->_viewLogicPath = $lang . '/' . $this->_viewname;
        else
            $this->_viewLogicPath = $this->_viewname;

        $this->_materials_to_place = array();
        do {
            $actions = 0;
            $actions += $this->load_ParentModels($lang);
            $actions += $this->load_SubSections($lang);
            $actions += $this->load_PlaceMaterials();
        } while ($actions > 0);

        $this->_currentmaterial->cleanupDOM();

        if (config::MAT_scriptmove)
            $this->_currentmaterial->moveScriptCodes();

        return true;
    }

    /**
     * localized logical view path
     * @return string
     */
    public function getLogicPath() {
        return $this->_viewLogicPath;
    }

    /**
     * attach frames and corresponding rootnode to the basevie 
     */
    private function addFrameInstance($cmcId, $node) {

        $this->_sections[$cmcId] = $node;
        $framedef = $this->_app->getFrameInstance($cmcId, true);

        if ($framedef != false) {
            $this->_frames[$cmcId] = $framedef;
            /*$srcpath = $framedef->getSourcePath();
            if ($srcpath)
                $this->_material_mdt[$srcpath] = filemtime($srcpath);*/
            $framedef->viewAttach($this);
            $framedef->viewStaticUpdate($this);
            if (!$framedef->is_dynamic())
                $framedef->viewInitialUpdate($this);
            $framedef->viewPostStaticUpdate($this);
        }
    }

    /**
     * initializes frames to be used in this view
     */
    public function framesInit() {
        $this->_sections = array();
        $cmcIdNodes = $this->_currentmaterial->findAttributeNodes(config::VW_ACMCID);
        foreach ($cmcIdNodes as $node) {
            $cmcId = $node->getAttribute(config::VW_ACMCID);
            $node->removeAttribute(config::VW_ACMCID);
            $this->addFrameInstance($cmcId, $node);
        }
        $node = $this->_currentmaterial->document();
        $this->addFrameInstance('', $node);
        $this->_currentmaterial->endStaticScriptCode($this);
    }

    /**
     * Retrieves a frame instance from Id
     * 
     * @param type $cmcId
     * @return type
     */
    public function getFrame($cmcId) {
        if (array_key_exists($cmcId, $this->_frames))
            return $this->_frames[$cmcId];
        $framedef = $this->_app->getFrameInstance($cmcId);
        if ($framedef != false) {
            $this->_frames[$cmcId] = $framedef;
        }
        return $framedef;
    }

    /**
     * gets the image data, in case of image answer
     * 
     * @return null|resource
     */
    public function getImageData() {
        return $this->_imgData;
    }

    /**
     * used to define the image answer for the view
     * also changs the answer type to image type
     * (called from a frame implementation)
     * 
     * @param resource $imgData
     */
    public function setImageAnswer($imgData) {
        \cmc\sess()->getRequest()->setAnswerType(request::type_image);
        $this->_imgData = $imgData;
    }

    /*     * *
     *  section facility functions, for widgets usage
     * * */
    /*
     * returns the rootnode of the section
     */

    /**
     * returns a view's section root node
     * 
     * @param string $secId
     * @return boolean|DOMNode
     */
    private function getSection($secId) {
        if (!array_key_exists($secId, $this->_sections))
            return false;
        $sect = $this->_sections[$secId];
        return $sect;
    }

    /**
     * seeks a DOM element inside a section
     * will return the first element if there is a multiple match
     * 
     * @param string $secId
     * @param string $xpath
     * @return boolean|DOMNode
     */
    public function getSectionDOMElement($secId, $xpath) {
        $sect = $this->getSection($secId);
        if (!$sect)
            return false;
        return $this->_currentmaterial->findXpathNode($sect, $xpath);
    }

    /**
     * seeks DOM elements inside a section
     * 
     * @param string $secId
     * @param string $xpath
     * @return boolean|DOMNodeList
     */
    public function getSectionDOMElements($secId, $xpath) {
        $sect = $this->getSection($secId);
        if (!$sect)
            return false;
        return $this->_currentmaterial->findXpathNodes($sect, $xpath);
    }

    /**
     * seeks a DOM element in the whole view
     * 
     * @param string $path
     * @return boolean|\DOMNode
     */
    public function getDOMElement($path) {
        return $this->_currentmaterial->findXpathNode(null, $path);
    }

    /**
     * seeks a DOM element from a DOM parent element
     * 
     * @param DOMNode $parent
     * @param string $path
     * @return DOMNode
     */
    public function getDOMSubElement($parent, $path) {
        return $this->_currentmaterial->findXpathNode($parent, $path);
    }

    /**
     * adds some custom script code in the view, in main section position
     * 
     * @param string $scriptCode
     */
    public function addScriptCode($scriptCode) {
        $this->_currentmaterial->addScriptCode($scriptCode);
    }

    /**
     * adds some custom sript code in the view, in bottom section position
     * 
     * @param type $scriptcode
     */
    public function addBottomScriptCode($scriptcode) {
        $this->_currentmaterial->addBottomScriptCode($scriptcode);
    }

    /**
     * returns the associative array of sections
     * 
     * Name=>DOMNode
     * @return array
     */
    public function getSections() {
        return $this->_sections;
    }

    /**
     * returns the view name
     * @return strings
     */
    public function getName() {
        return $this->_viewname;
    }

    /**
     * returns the CMC application
     * @return \cmc\app
     */
    public function getapp() {
        return $this->_app;
    }

    /**
     * rebinds the application to the object
     * @param \cmc\app $app
     */
    public function setapp(app $app) {
        $this->_app = $app;
    }

    /**
     * defines an HTTP response code
     * @param integer $code
     */
    public function setResponseCode($code) {
        $this->_respCode = $code;
    }

    /**
     * returns the current HTTP response code
     * @return integer
     */
    public function getResponseCode() {
        return $this->_respCode;
    }

    /**
     * creates a new view instance
     * @param \cmc\app $app
     * @return \cmc\ui\view
     */
    public static function create(app $app) {
        return new view($app);
    }

    private function __construct(app $app) {
        $this->_app = $app;
        $this->_frames = array();
        $this->_material_mdt = array();
        $this->_imgData = null;
    }

    /**
     * final rendering process
     * 
     * at this stage, all frames have been executed
     * the rendering is directly done if available, or returns false in case of error
     * 
     * @param \cmc\sess $sess
     * @return boolean
     */
    public function renderView(sess $sess) {
        //TODO: logger les évènements en échec!
        $req = $sess->getRequest();
        switch ($req->getAnswerType()) {
            case request::type_html:
                header('Content-Type: text/html');
                print($this->_currentmaterial->getHTML());
                break;
            case request::type_image:
                if ($this->_imgData) {
                    $sess->getRequest()->renderImage($this->_imgData);
                    imagedestroy($this->_imgData);
                    unset($this->_imgData);
                } else
                    return false;
                break;
            default:
                http_response_code(406);
                ob_flush();
                fatalErrors::trigger($this->_app->getSession(), 'No appropriate answer available');
                return false;
        }
        return true;
    }

    /**
     * used to check if the material items have changed
     * 
     * it checks all files needed to build the material,
     * EXCEPT php source files (like frame implementation)
     * This is used to rebuild the view instead of reloading the static part from the cache
     * @return boolean
     */
    public function materialChanged() {
        foreach ($this->_material_mdt as $filepath => $mdt) {
            if (filemtime($filepath) > $mdt)
                return true;
        }
        return false;
    }

    /**
     * used to check if the view safely can be written in the cache
     * @return boolean
     */
    public function ValidForSave() {
        if (!$this->_currentmaterial)
            return false;
        return $this->_currentmaterial->ValidForSave();
    }

    /**
     * returns material instance
     * @return \cmc\core\ui\material
     */
    public function material() {
        return $this->_currentmaterial;
    }

    // when serialized with the app
    public function OnSerialize() {
        if ($this->_app === null)  // avoids multiple calls
            return;

        if ($this->_currentmaterial) {
            $this->_sections_ser = array();
            foreach ($this->_sections as $cmdId => $node) {
                $this->_sections_ser[$cmdId] = $node->getNodePath();
            }
            $this->_currentmaterial->OnSerialize();
        }

        $this->_imgData = null;
        $this->_frames = array();
        $this->_sections = null;
        $this->_materials_to_place = null;
        $this->_app = null;

        if ($this->_respCode != 404)
            $this->_respCode = 200;    // this is because 404 pages are cached by origin        
    }

    public function OnUnserialize() {
        if (isset($this->_sections))    // avoids multiple calls
            return;
        $this->_sections = array();
        $this->_frames = array();
        if ($this->_currentmaterial) {
            $this->_currentmaterial->OnUnserialize();
            //var_dump($this);        
            foreach ($this->_sections_ser as $cmcId => $nodepath) {
                $this->_sections[$cmcId] = $this->_currentmaterial->findXpathNode(null, $nodepath);
            }
            $this->_sections_ser = null;
        }
    }

}
