<?php

/**
 * Application: asalae / Adullact.
 * Date: 25/04/14
 * @author: Florian Ajir <florian.ajir@adullact.org>
 * @license CeCiLL V2 <http://www.cecill.info/licences/Licence_CeCILL_V2-fr.html>
 */
class SedaSchemaParser {

    /**
     * @var SimpleXmlElement[] $schemas
     */
    private static $schemas;

    /**
     * @var array $ns
     */
    private static $ns;

    /**
     * @var string $version
     */
    private static $version;

    /**
     * @var array $sedatree
     */
    private static $sedatree;

    /**
     * @var array $filter
     */
    private static $filter;

    /**
     * @var array $blacklist
     */
    private static $blacklist;

    /**
     * xml NameSpaces pour la création des messages
     * @var array $sedaNS
     */
    public static $sedaNS = array(
        '0.2' => 'fr:gouv:ae:archive:draft:standard_echange_v0.2',
        '1.0' => 'fr:gouv:culture:archivesdefrance:seda:v1.0'
    );

    /**
     * Initialisations
     */
    private static function _init() {
        self::$schemas = array();
        self::$ns = array();
        self::$sedatree = array(
            '@objects' => array(),
            '@codes' => array()
        );
        self::$filter = array();
        self::$blacklist  = array(
            'ArchiveTransfer' => array('Integrity', 'NonRepudiation'), //seda v0.2
            'OtherMetadata'
        );
    }

    /**
     * Méthode principale
     * @param string $version
     * @param string $root
     * @param array $filter
     * @param array $blacklist
     * @return array
     * @throws Exception si fichier schema introuvable
     */
    public static function schemaToTree($version, $root, $filter = array(), $blacklist = array()) {
        //Initialisations
        self::_init();
        self::$version = $version;
        if (!empty($filter))
            self::$filter = $filter;
        self::$blacklist = array_merge(self::$blacklist, $blacklist);
        //Chemin des schemas seda
        $schemas_dir = APP . WEBROOT_DIR . DS . 'schema_xml' . DS;
        switch ($version) {
            case '0.2' :
                $seda_prefix = 'seda_v0-2';
                $prefix_path = $schemas_dir . $seda_prefix . DS . 'v02' . DS;
                $schemaPath = $prefix_path . 'archives_echanges_v0-2_' . strtolower($root) . '.xsd';
                break;

            case '1.0' :
                $seda_prefix = 'seda_v1-0';
                $prefix_path = $schemas_dir . $seda_prefix . DS . 'v10' . DS;
                $schemaPath = $prefix_path . $seda_prefix . '_' . strtolower($root) . '.xsd';
                break;

            default :
                throw new Exception('Version de SEDA non implémentée');
        }
        if (!file_exists($schemaPath))
            throw new Exception('Echec lors de l\'ouverture du schéma SEDA v' . $version . ' : ' . $schemaPath);

        $schema = simplexml_load_file($schemaPath);
        //Chargement des namespaces
        self::$ns = $schema->getDocNamespaces();
        //Chargement des schemas seda
        self::_loadSchemas($prefix_path);
        //Charger le noeud racine
        $root_type = self::_getRootElementType($schema, $root);
        $root_element = self::_getComplexType($root_type, $schema);
        //root UniqueID
        $documentation = self::_getDocumentation($root_element);
        self::$sedatree['@root'] = $documentation['UniqueID'];
        //Construction de la structure de l'arbre
        self::_parseObjectElement($root_element, $schema);
        return self::$sedatree;
    }

    /**
     * Méthode principale
     * @param string $version
     * @param string $element
     * @return array
     * @throws Exception si version seda incorrecte
     */
    public static function getElementAttributes($version, $element) {
        self::_init();
        $result = array();
        //Chemin des schemas seda
        $schemas_dir = APP . WEBROOT_DIR . DS . 'schema_xml' . DS;
        switch ($version) {
            case '0.2' :
                $seda_prefix = 'seda_v0-2';
                $prefix_path = $schemas_dir . $seda_prefix . DS . 'v02' . DS;
                break;

            case '1.0' :
                $seda_prefix = 'seda_v1-0';
                $prefix_path = $schemas_dir . $seda_prefix . DS . 'v10' . DS;
                break;

            default :
                throw new Exception('Version de SEDA non implémentée');
        }
        //Chargement des schemas seda
        self::_loadSchemas($prefix_path);
        //Chercher le noeud
        $xpath = "//xsd:element[@name='$element']";
        $schema = self::_findSchema($xpath);
        $node = self::_findNode($xpath);
        if (!empty($schema) && !empty($schema)) {
            $type = self::_getNodeAttribute($node, 'type');
            if (!empty($type))
                $result = self::_getElementComplexType($type, $schema);
        }
        if (!empty(self::$sedatree['@codes']))
            $result['@codes'] = self::$sedatree['@codes'];
        return $result;
    }

    /**
     * @param string $dir_path
     */
    private static function _loadSchemas($dir_path) {
        $dir = new DirectoryIterator($dir_path);
        foreach ($dir as $file) {
            if ($file->isDot()) continue;
            $fileExtension = (strpos($file->getFilename(), '.') === false) ? '' : substr($file->getFilename(), strpos($file->getFilename(), '.') + 1);
            if ($file->isFile() && $fileExtension == 'xsd') { // TODO
                $code = simplexml_load_file($file->getPathname());
                $ns = $code['targetNamespace']->__toString();
                if (array_key_exists($ns, self::$schemas)) {
                    if (is_object(self::$schemas[$ns]) && get_class(self::$schemas[$ns]) == 'SimpleXMLElement') {
                        self::$schemas[$ns] = array(self::$schemas[$ns]);
                    }
                    self::$schemas[$ns][] = $code;
                } else
                    self::$schemas[$ns] = $code;
                self::$ns = array_merge(self::$ns, $code->getDocNamespaces());
            } elseif ($file->isDir()) {
                self::_loadSchemas($file->getPathname());
            }
        }
    }

    /**
     * @param SimpleXmlElement $element
     * @param SimpleXmlElement $schema
     * @throws Exception
     */
    private static function _parseObjectElement(SimpleXmlElement $element, SimpleXmlElement $schema) {
        //UniqueID
        $object_id = self::_getUniqueID($element);
        // TODO
        // if $object_id == false throw new Exception();
        //Si pas encore parsé
        if (!empty($object_id) && !array_key_exists($object_id, self::$sedatree['@objects'])) {
            //Parser la structure de l'objet
            $structure = self::_getElementDetails($element);
            $structure['@elements'] = array();
            //Eléments
            $nodes = $element->xpath('xsd:sequence/xsd:element');
            $children = array();
            foreach ($nodes as $node) {
                //@definition et @documentation
                $elt = self::_getElementDetails($node);
                //Element UniqueID
                $element_id = $elt['@documentation']['UniqueID'];

                if (empty($elt['@definition']['type']))
                    throw new Exception("Elément {self::_getObjectClassTerm($element)} : déclaration type manquante");

                $type = $elt['@definition']['type'];

                /**
                 * Filtres
                 */
                $objectClass = self::_getObjectClassTerm($node);
                $property = self::_getPropertyTerm($node);

                if (self::_isFiltered($objectClass, $property))
                    continue;

                //type field : *:*
                if (preg_match('#(.)+:(.)+#', $type) && !array_key_exists($element_id, $structure['@elements'])) {
                    //Primitive type
                    $elt['@primitive'] = self::_getPrimitiveTypeByType($type);
                    $elt['@attributes'] = array();
                    $declaration = explode(':', $type);
                    $completetype = $type;
                    $prefix = $declaration[0];
                    $type = $declaration[1];
                    //Schema dans lequel le type est déclaré
                    $typeSchema = !empty(self::$schemas[self::$ns[$prefix]]) ? self::$schemas[self::$ns[$prefix]] : null;
                    $rules = $typeSchema->xpath("xsd:complexType[@name='$type']/xsd:simpleContent/xsd:*");
                    //Chercher la liste de codes
                    $values = self::_getEnumerationCodes($completetype, $schema);
                    if (!empty($values))
                        $elt['@codes'] = $completetype;
                    if (!empty($rules)) {
                        foreach ($rules as $rule) {
                            $ruleAttrs = self::_findTypeAttributes($rule);
                            $base = self::_getNodeAttribute($rule, 'base');
                            if (!empty($base)) {
                                $elt['@base'] = $base;
                                $heritedPT = self::_getPrimitiveTypeByType($base);
                                if (!empty($heritedPT))
                                    $elt['@primitive'] = $heritedPT;

                                if (preg_match('#(.)+:(.)+#', $base)) {
                                    $herit = explode(':', $base);
                                    $heritedSchema = !empty(self::$schemas[self::$ns[$herit[0]]]) ? self::$schemas[self::$ns[$herit[0]]] : null;
                                    if (!empty($heritedSchema)) {
                                        //Chercher attributs hérités
                                        $herited = self::_getElementComplexType($base, $heritedSchema);
                                        $ruleAttrs = array_merge($herited['@attributes'], $ruleAttrs);

                                        if (!empty(self::$sedatree['@codes'][$base]))
                                            $elt['@codes'] = $base;

                                        if (!empty($herited['@primitive']))
                                            $elt['@primitive'] = $herited['@primitive'];
                                    }
                                }
                            } else
                                throw new Exception('Attribut "base" non déclarée pour le type : ' . $type);
                            $elt['@attributes'] = array_merge($elt['@attributes'], $ruleAttrs);
                        }
                    } else {
                        $rules = $typeSchema->xpath("xsd:simpleType[@name='$type']/xsd:restriction");
                        foreach ($rules as $rule) {
                            $base = self::_getNodeAttribute($rule, 'base');
                            if (!empty($base))
                                $elt['@base'] = $base;
                        }
                    }
                    $elt['@type'] = 'field';
                    $structure['@elements'][$element_id] = $elt;
                } //Type Element (Object)
                elseif (self::_isElementByType($type, $schema)) {
                    //Parser si pas encore présent dans le tableau d'objets
                    $child = self::_getComplexType($type, $schema);
                    $child_name = self::_getObjectClassTerm($child);
                    $child_id = self::_getUniqueID($child);
                    $elt['@type'] = 'object';
                    $elt['@uid'] = $child_id;
                    $structure['@elements'][$element_id] = $elt;
                    //Si non filtré ajouter infos
                    if (!empty($child)
                        && !empty($child_name) // OtherMetadataType = null
                        && !self::_isFiltered($child_name)
                        && !empty($child_id) // OtherMetadataType = null
                        && !array_key_exists($child_id, self::$sedatree['@objects']))
                            $children[] = $child;

                } else
                    throw new Exception("Element $type introuvable");
            }

            //Ajouter la structure de l'objet à l'arbre
            self::$sedatree['@objects'][$object_id] = $structure;
            //Parser les enfants
            foreach ($children as $child)
                self::_parseObjectElement($child, $schema);
        }
    }

    /**
     *
     * @param string $object
     * @param string|null $property
     * @return bool true => filtrer, false => parser
     */
    private static function _isFiltered($object, $property = null) {
        if (!empty($property) && preg_match('/^ArchivalAgency(.*)Identifier$/', $property)) //Exclure propriétés ArchivalAgency*Identifier
            return true;
        elseif (in_array($object, self::$blacklist))
            return true;
        elseif (!empty($property) && in_array($property, self::$blacklist))
            return true;
        elseif (!empty($property) && !empty(self::$blacklist[$object]) && in_array($property, self::$blacklist[$object]))
            return true;
        elseif (!empty(self::$filter) && !array_key_exists($object, self::$filter))
            return true;
        elseif (!empty($property) && !empty(self::$filter[$object]) && !in_array($property, self::$filter[$object]))
            return true;
        else
            return false;
    }

    /**
     * @param SimpleXmlElement $element
     * @param SimpleXmlElement $schema
     * @return array
     * @throws Exception
     */
    private static function _getObjectElements(SimpleXmlElement $element, SimpleXmlElement $schema) {
        $elements = array();
        $nodes = $element->xpath('xsd:sequence/xsd:element');
        foreach ($nodes as $node) {
            //attributs (@definition) et @documentation
            $elt = self::_getElementDetails($node);

            if (empty($elt['@definition']['type']))
                throw new Exception("Elément {self::_getObjectClassTerm($element)} : déclaration type manquante");
            if (empty($elt['@definition']['name']))
                throw new Exception("Elément {self::_getObjectClassTerm($element)} : déclaration name manquante");

            $type = $elt['@definition']['type'];
            $name = $elt['@definition']['name'];
            $objet = self::_getObjectClassTerm($node);
            $property = self::_getPropertyTerm($node);


            //Exceptions
            if (self::_isFiltered($objet, $name))
                continue;

            /**
             * WARNING Exception : Exclure propriétés ArchivalAgency*Identifier
             */
            if (preg_match('/^ArchivalAgency(.*)Identifier$/', $property))
                continue;

            if ($objet == $property) {
                $elt['@clone'] = true;
                $elements[$name] = $elt;
            } else {
                $new = self::_getElementComplexType($type, $schema);
                if (!empty($new)) {
                    $elt = array_merge($elt, $new);
                    $elements[$name] = $elt;
                }
            }
        }
        return $elements;
    }

    /**
     * @param SimpleXmlElement $element
     * @return string
     */
    private static function _getObjectClassTerm(SimpleXmlElement $element) {
        return self::_xpathToString($element, 'xsd:annotation/xsd:documentation/ccts:ObjectClassTerm');
    }

    /**
     * @param SimpleXmlElement $element
     * @return string
     */
    private static function _getPropertyTerm(SimpleXmlElement $element) {
        return self::_xpathToString($element, 'xsd:annotation/xsd:documentation/ccts:PropertyTerm');
    }

    /**
     * @param SimpleXmlElement $element
     * @return string
     */
    private static function _getUniqueID(SimpleXmlElement $element) {
        return self::_xpathToString($element, 'xsd:annotation/xsd:documentation/ccts:UniqueID');
    }

    /**
     * @param string $name
     * @param SimpleXmlElement $schema
     * @param bool $search_others
     * @return mixed
     * @throws Exception
     */
    private static function _getComplexType($name, SimpleXmlElement $schema = null, $search_others = true) {
        $ns = explode(':', $name);
        if ($ns[0] == 'xsd') return array();
        $xpath = "xsd:complexType[@name='$name']";
        if (!is_null($schema)) {
            $element = $schema->xpath($xpath);
            if (!empty($element[0]))
                return $element[0];
        }
        if (is_null($schema) || $search_others) {
            return self::_findNode($xpath);
        }
        throw new Exception("complexType introuvable : $name");
    }

    /**
     * @param string $nodename
     * @param string $nameattr
     * @param SimpleXmlElement $schema
     * @param bool $search_others
     * @return mixed
     * @throws Exception
     */
    private static function _getNodeByName($nodename, $nameattr, SimpleXmlElement $schema = null, $search_others = true) {
        $xpath = "xsd:{$nodename}[@name='{$nameattr}']";

        if (!is_null($schema)) {
            $element = $schema->xpath($xpath);
            if (!empty($element[0]))
                return $element[0];
        }
        if (is_null($schema) || $search_others) {
            return self::_findNode($xpath);
        }
        return false;
    }

    /**
     * Recherche dans tous les documents
     * @param string $xpath
     * @return bool|SimpleXmlElement
     */
    private static function _findNode($xpath) {
        foreach (self::$schemas as $schema) {
            if (!is_object($schema) && is_array($schema)) {
                foreach ($schema as $s) {
//                    $s->registerXPathNamespace('xsd', "http://www.w3.org/2001/XMLSchema");
                    $node = $s->xpath($xpath);
                    if (!empty($node[0]))
                        return $node[0];
                }
            } else {
                $schema->registerXPathNamespace('xsd', "http://www.w3.org/2001/XMLSchema");
                $node = $schema->xpath($xpath);
                if (!empty($node[0]))
                    return $node[0];
            }
        }
        return false;
    }

    /**
     * Recherche dans tous les documents
     * @param string $xpath
     * @return bool|SimpleXmlElement
     */
    private static function _findSchema($xpath) {
        foreach (self::$schemas as $schema) {
            if (!is_object($schema) && is_array($schema)) {
                foreach ($schema as $s) {
//                    $s->registerXPathNamespace('xsd', "http://www.w3.org/2001/XMLSchema");
                    $node = $s->xpath($xpath);
                    if (!empty($node[0]))
                        return $s;
                }
            } else {
                $schema->registerXPathNamespace('xsd', "http://www.w3.org/2001/XMLSchema");
                $node = $schema->xpath($xpath);
                if (!empty($node[0]))
                    return $schema;
            }
        }
        return false;
    }

    /**
     * @param SimpleXMLElement $schema
     * @param string $name
     * @return null|string
     * @throws Exception si l'élément est introuvable
     */
    private static function _getElementType(SimpleXmlElement $schema, $name) {
        $element = $schema->xpath("xsd:element[@name='$name']");
        if (empty($rootElt))
            throw new Exception('Element introuvable : ' . $name);
        return self::_getNodeAttribute($element[0], 'type');
    }

    /**
     * @param SimpleXMLElement $schema
     * @return null|string
     * @throws Exception si l'élément est introuvable
     */
    private static function _getFirstElementType(SimpleXmlElement $schema) {
        $element = $schema->xpath("xsd:element");
        return self::_getNodeAttribute($element[0], 'type');
    }

    /**
     * @param SimpleXMLElement $schema
     * @param string $name
     * @return null|string
     * @throws Exception si l'élément est introuvable
     */
    private static function _getRootElementType(SimpleXmlElement $schema, $name) {
        try {
            $type = self::_getElementType($schema, $name);
        } catch (Exception $e) {
            $type = self::_getFirstElementType($schema);
        }
        return $type;
    }

    /**
     * @param string $type
     * @param SimpleXMLElement $schema
     * @param bool $search_others elargir la recherche aux autres schemas
     * @return bool
     */
    private static function _isElementByType($type, SimpleXMLElement $schema = null, $search_others = true) {
        $xpath = "xsd:complexType[@name='$type']";
        if (!is_null($schema)) {
            $element = $schema->xpath($xpath);
            if (count($element) > 0)
                return true;
        }
        if (is_null($schema) || $search_others) {
            $founded = self::_findNode($xpath) !== false;
            return $founded;
        }
        return false;
    }

    /**
     * @param SimpleXMLElement $xmlElement
     * @param string $xpath
     * @return string
     */
    private static function _xpathToString(SimpleXMLElement $xmlElement, $xpath) {
        $xmlElement->registerXPathNamespace('ccts', null);
        $objectName = (array)$xmlElement->xpath($xpath);
        if (empty($objectName))
            return false;
        return (string)$objectName[0];
    }

    /**
     * @param SimpleXMLElement $element
     * @return array
     */
    private static function _getNodeAttributes(SimpleXMLElement $element) {
        $attrArray = array();
        foreach ($element->attributes() as $key => $val) {
            $attrArray[(string)$key] = (string)$val;
        }
        return $attrArray;
    }

    /**
     * @param SimpleXMLElement $element
     * @return array
     */
    private static function _getElementDetails(SimpleXMLElement $element) {
        return array(
            '@documentation' => self::_getDocumentation($element),
            '@definition' => self::_getNodeAttributes($element),
        );
    }

    /**
     * @param string $type
     * @param SimpleXmlElement $schema
     * @return array
     * @throws Exception
     */
    private static function _getElementComplexType($type, SimpleXmlElement $schema) {
        //Init element details
        $elt = array(
            '@primitive' => self::_getPrimitiveTypeByType($type),
            '@attributes' => array()
        );
        //type *:*
        if (preg_match('#(.)+:(.)+#', $type)) {
            $declaration = explode(':', $type);
            $completetype = $type;
            $prefix = $declaration[0];
            $type = $declaration[1];
            //Schema dans lequel le type est déclaré
            $typeSchema = !empty(self::$schemas[self::$ns[$prefix]]) ? self::$schemas[self::$ns[$prefix]] : null;
            $rules = $typeSchema->xpath("xsd:complexType[@name='$type']/xsd:simpleContent/xsd:*");
            //Chercher la liste de codes
            $values = self::_getEnumerationCodes($completetype, $schema);
            if (!empty($values))
                $elt['@codes'] = $completetype;
            if (!empty($rules)) {
                foreach ($rules as $rule) {
                    $ruleAttrs = self::_findTypeAttributes($rule);
                    $base = self::_getNodeAttribute($rule, 'base');
                    if (!empty($base)) {
                        $elt['@base'] = $base;
                        $heritedPT = self::_getPrimitiveTypeByType($base);
                        if (!empty($heritedPT))
                            $elt['@primitive'] = $heritedPT;

                        if (preg_match('#(.)+:(.)+#', $base)) {
                            $herit = explode(':', $base);
                            $heritedSchema = !empty(self::$schemas[self::$ns[$herit[0]]]) ? self::$schemas[self::$ns[$herit[0]]] : null;
                            if (!empty($heritedSchema)) {
                                //Chercher attributs hérités
                                $herited = self::_getElementComplexType($base, $heritedSchema);
                                $ruleAttrs = array_merge($herited['@attributes'], $ruleAttrs);

                                if (!empty(self::$sedatree['@codes'][$base]))
                                    $elt['@codes'] = $base;

                                if (!empty($herited['@primitive']))
                                    $elt['@primitive'] = $herited['@primitive'];
                            }
                        }
                    } else
                        throw new Exception('Attribut "base" non déclarée pour le type : ' . $type);

                    $elt['@attributes'] = array_merge($elt['@attributes'], $ruleAttrs);
                }
            } else {
                $rules = $typeSchema->xpath("xsd:simpleType[@name='$type']/xsd:restriction");
                foreach ($rules as $rule) {
                    $base = self::_getNodeAttribute($rule, 'base');
                    if (!empty($base))
                        $elt['@base'] = $base;
                }
            }
        } //Type Element (Object)
        elseif (self::_isElementByType($type, $schema)) {
            //Si le niveau de récursivité max n'est pas atteint
            $child = self::_getComplexType($type, $schema);
            if (!empty(self::$filter) && !array_key_exists(self::_getObjectClassTerm($child), self::$filter))
                return false;
            if (!empty($child))
                $elt['@object'] = self::_parseObjectElement($child, $schema);
        } else
            throw new Exception("Element $type introuvable");

        return $elt;
    }

    /**
     * @param SimpleXMLElement $simpleContentChild
     * @return array
     */
    private static function _findTypeAttributes(SimpleXMLElement $simpleContentChild) {
        $attrs = array();
        $attributeNodes = $simpleContentChild->xpath('xsd:attribute');
        foreach ($attributeNodes as $node) {
            $attributeNodeAttrs = self::_getNodeAttributes($node);
            $attrs[$attributeNodeAttrs['name']]['@definition'] = $attributeNodeAttrs;
            $attrs[$attributeNodeAttrs['name']]['@documentation'] = self::_getDocumentation($node);
            if (!empty($attrs[$attributeNodeAttrs['name']]['@definition']['type'])
                && $attrs[$attributeNodeAttrs['name']]['@definition']['use'] != 'prohibited'
            ) {
                $values = self::_getEnumerationCodes($attrs[$attributeNodeAttrs['name']]['@definition']['type']);
                if (!empty($values))
                    $attrs[$attributeNodeAttrs['name']]['@codes'] = $attrs[$attributeNodeAttrs['name']]['@definition']['type'];
            }
        }
        return $attrs;
    }

    /**
     * @param string $name
     * @param SimpleXMLElement $schema
     * @param bool $search_others
     * @return array
     */
    private static function _getEnumerationCodes($name, SimpleXMLElement $schema = null, $search_others = false) {
        $output = array();
        $simplified = array();
        $complete = array();
        $completename = $name;
        if (!empty(self::$sedatree['@codes'][$completename]['@simplified'])) return self::$sedatree['@codes'][$completename]['@simplified'];
        if (!empty(self::$sedatree['@codes'][$completename]['@complete'])) return self::$sedatree['@codes'][$completename]['@complete'];
        App::import('Lib', 'AppTools');
        if (preg_match('#(.)+:(.)+#', $name)) {
            $declaration = explode(':', $name);
            if ($declaration[0] == 'xsd') return array();
            $prefix = $declaration[0];
            $name = $declaration[1];
            if (empty($schema))
                $schema = !empty(self::$schemas[self::$ns[$prefix]]) ? self::$schemas[self::$ns[$prefix]] : null;
        }
        $path = WWW_ROOT . 'files' . DS . 'sedacodes' . DS . self::$version;
        $filepath = $path . DS . $name;
        $simplifiedfilepath = $path . DS . 'Simple' . $name;
        if (file_exists($filepath)) {
            $json = file_get_contents($filepath);
            $complete = json_decode($json, true);
        } else {
            $xpath = "xsd:simpleType[@name='$name']//xsd:enumeration";
            if (!is_null($schema)) {
                $enums = $schema->xpath($xpath);
                if (!empty($enums))
                    foreach ($enums as $enum)
                        $complete[$enum['value']->__toString()] = self::_getDocumentation($enum);
            }
            if (is_null($schema) || ($search_others && empty($output))) {
                foreach (self::$schemas as $s) {
                    $enums = $s->xpath($xpath);
                    if (!empty($enums))
                        foreach ($enums as $enum)
                            $complete[$enum['value']->__toString()] = self::_getDocumentation($enum);
                }
            }
            if (!empty($complete)) {
                if (!is_dir(WWW_ROOT . 'files')) mkdir(WWW_ROOT . 'files');
                if (!is_dir(WWW_ROOT . 'files' . DS . 'sedacodes')) mkdir(WWW_ROOT . 'files' . DS . 'sedacodes');
                if (!is_dir($path)) mkdir($path);
                ksort($complete);
                file_put_contents($filepath, json_encode($complete));
            }
        }
        if (file_exists($simplifiedfilepath)) {
            $json = file_get_contents($simplifiedfilepath);
            $simplified = json_decode($json, true);
        }
        //Tri par clé et ajout tableau dans variable de classe
        if (!empty($complete)) {
            ksort($complete);
            self::$sedatree['@codes'][$completename]['@complete'] = $complete;
            $output = $complete;
        }
        if (!empty($simplified)) {
            ksort($simplified);
            self::$sedatree['@codes'][$completename]['@simplified'] = $simplified;
            $output = $simplified;
        }
        return $output;
    }

    /**
     * @param SimpleXMLElement $element
     * @return array
     */
    private static function _getDocumentation(SimpleXMLElement $element) {
        $docs = array();
        $documentations = $element->xpath('xsd:annotation/xsd:documentation/ccts:*');
        if (!empty($documentations))
            foreach ($documentations as $doc)
                $docs[$doc->getName()] = self::superTrim(sprintf("%s", $doc));
        else {
            $definition = self::_xpathToString($element, 'xsd:annotation/xsd:documentation');
            if (!empty($definition))
                $docs['Definition'] = self::superTrim(self::_xpathToString($element, 'xsd:annotation/xsd:documentation'));
        }
        return $docs;
    }

    private static function _getPrimitiveTypeByType($type) {
        //Vérification forme
        if (!preg_match('#(.)+:(.)+#', $type))
            return false;

        //Découpage
        $expl = explode(':', $type);
        $ns = $expl[0];
        $typename = $expl[1];

        if (!array_key_exists($ns, self::$ns))
            return false;

        $uri = self::$ns[$ns];

        if (!array_key_exists($uri, self::$schemas))
            return false;

        $schema = self::$schemas[$uri];
        $typenode = self::_getNodeByName('complexType', $typename, $schema);
        if (empty($typenode))
            $typenode = self::_getNodeByName('simpleType', $typename, $schema);

        if (empty($typenode))
            return false;

        $xpath = 'xsd:annotation/xsd:documentation/ccts:PrimitiveType';
        return self::_xpathToString($typenode, $xpath);
    }

    /**
     * @param array $data données à exporter en XML
     * @return string XML
     */
    public static function arrayToXml($data) {
        App::uses('Xml', 'Utility');
//        $xmlObject = Xml::fromArray($data); // You can use Xml::build() too
        $xmlObject = Xml::build($data, array('return' => 'domdocument'));
        $xmlObject->formatOutput = true;
        $xmlObject->preserveWhiteSpace = false;

        $xpath = new DOMXPath($xmlObject);

        // not(*) does not have children elements
        // not(@*) does not have attributes
        // text()[normalize-space()] nodes that include whitespace text
        foreach( $xpath->query('//*[not(*) and not(@*) and not(text()[normalize-space()])]') as $node ) {
            $node->parentNode->removeChild($node);
        }
        return $xmlObject->saveXML();
    }

    /**
     * @param string $xml
     * @return array
     */
    public static function xmlToArray($xml) {
        App::uses('Xml', 'Utility');
//        return Xml::toArray(Xml::build($xml, array('return' => 'domdocument')));
        return Xml::toArray(Xml::build($xml));
    }

    /**
     * @param SimpleXMLElement $node
     * @param string $attributeToFind
     * @return string|null
     */
    private static function _getNodeAttribute(SimpleXMLElement $node, $attributeToFind) {
        foreach ($node->attributes() as $attr => $val) {
            if ($attr == $attributeToFind)
                return $val->__toString();
        }
        return null;
    }

    /**
     * remove new lines & multiple spaces
     * @param $string
     * @return string
     */
    public static function superTrim($string) {
        return trim(preg_replace('/\s\s+/', ' ', $string));
    }

}