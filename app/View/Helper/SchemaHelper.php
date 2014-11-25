<?php
/**
 * Application: asalae / Adullact.
 * Date: 28/04/14
 * @author: Florian Ajir <florian.ajir@adullact.org>
 * @license CeCiLL V2 <http://www.cecill.info/licences/Licence_CeCILL_V2-fr.html>
 */

App::uses('FormHelper', 'View/Helper');

class SedaHelper extends FormHelper {

    var $helpers = array('Html', 'Js', 'Form');

    /**
     * constante nombre max de mot pour affichage description (label)
     */
    const MAX_LABEL_WORDS = 8;

    /**
     * Nom du noeud principal
     * @var string
     */
    private $root;

    /**
     * Nom du prefix (seda02|seda10)
     * @var string
     */
    private $prefix;

    /**
     * Structure du formulaire
     * @var array
     */
    private $sedatree;

    /**
     * Chargement des dépendances
     */
    function initForm() {
        echo $this->Html->css('sedaform');
        echo $this->Html->script('sedaform');
    }

    /**
     * Construire un formulaire Seda à partir d'un schema xsd parsé par SedaSchemaParser
     * @param array $sedatree structure du formulaire
     * @param array $data données du formulaire
     * @param string $prefix nom du prefix : identifiant seda
     */
    public function form($sedatree, $data = array(), $prefix = 'seda02') {
        //Données enregistrées
        $this->sedatree = $sedatree;
        $this->prefix = $prefix;
        $this->root = $this->uniqueIDToName($sedatree['@root']);
        //Data racine
        $data = !empty($data[$prefix][$this->root]) ? $data[$prefix][$this->root] : array();
        //Chemin de l'objet
        $path = $prefix . '.' . $this->root;
        //Ouverture bloc
        echo $this->Html->tag('div', null, array('class' => "sedaform", 'id' => "sedaform-$prefix"));
        //Construction du formulaire
        $this->parseSedaObject($sedatree['@root'], $data, $path, false, true);
        //Fermeture bloc
        echo $this->Html->tag('/div');
    }

    private function uniqueIDToName($uniqueID, $container = 'objects') {
        if (!empty($this->sedatree['@' . $container][$uniqueID]['@documentation']['DictionaryEntryName']))
            return $this->sedatree['@' . $container][$uniqueID]['@documentation']['DictionaryEntryName'];
        else
            return false;
    }

    /**
     * @param array $attributes
     * @param array $data
     * @param string $path
     * @param string $legend
     * @param string $title
     */
    public function attributes($attributes, $data, $path, $legend = 'Attributs', $title = '') {
        if (!empty($attributes)) {
            echo $this->Html->tag('div', null, array('class' => 'sedaform'));
            echo $this->Html->tag('fieldset', null, array('class' => 'attributs', 'title' => $title));
            echo $this->Html->tag('legend', "<i class='icon-tags'></i> $legend");
            foreach ($attributes as $attribute) {
                //utilisation attribut interdit
                if ($attribute['@definition']['use'] == 'prohibited') continue;
                $attrPath = $path . '.@' . $attribute['@definition']['name'];
                //Options du champ attribut
                $opts = array(
                    'id' => str_replace('.', '-', $attrPath),
                    'type' => 'text',
                    'name' => $this->pathToName($attrPath)
                );
                // valeur attribut
                if (!empty($attribute['@definition']['name']))
                    $opts['label'] = $attribute['@definition']['name'];
                if (!empty($attribute['@documentation']['Name']))
                    $opts['title'] = $attribute['@documentation']['Name'];
                if (!empty($attribute['@documentation']['Definition']))
                    $opts['title'] .= "\n" . self::superTrim($attribute['@documentation']['Definition']);
                if (!empty($attribute['@definition']['use']) && $attribute['@definition']['use'] == 'required')
                    $opts['div']['class'] = 'required';
                if (!empty($attribute['@definition']['default'])) {
                    $opts['placeholder'] = $opts['value'] = $attribute['@definition']['default'];
                } elseif (!empty($data[$attribute['@definition']['name']]))
                    $opts['value'] = $data[$attribute['@definition']['name']];

                echo $this->Form->input($attribute['@definition']['name'], $opts);
            }
            echo $this->Html->tag('/fieldset');
            echo $this->Html->tag('/div');
        }
    }

    /**
     * @param string $uid
     * @param array $data
     * @param string $path
     * @param bool $template
     * @param bool $required objet requis cré une instance de départ
     */
    private function parseSedaObject($uid, $data, $path, $template = false, $required = false) {
        $name = $this->uniqueIDToName($uid);
        if (empty($this->sedatree['@objects'][$uid]))
            return;
        $object = $this->sedatree['@objects'][$uid];

        //Légende
        $definition = '';
        if (!empty($object['@documentation']['Definition']))
            $definition = self::superTrim($object['@documentation']['Definition']);
        elseif (!empty($object['@documentation']['DefinitionText']))
            $definition = $object['@documentation']['DefinitionText'];

        //Options du fieldset
        $opts = array(
            'class' => "sedafieldset sedafieldset-$name expanded",
            'data-name' => $this->pathToName($path),
            'data-title' => $name,
            'data-definition' => htmlspecialchars($definition),
        );

        //Template ?
        if ($template) {
            $opts['style'] = 'display:none';
            $opts['class'] .= ' sedatemplate';
            //Bloc masqué ?
            if ($required && empty($data))
                $this->parseSedaObject($uid, $data, $this->str_lreplace('template', '0', $path));
        }

        //Entourer l'objet Seda d'un fieldset
        echo $this->Html->tag('fieldset', null, $opts);
        //Bouton supprimer
        $deleteBtnOpts = array(
            "class" => "deleteSedaObject icon-remove icon-button pull-right icon-border",
            'title' => 'Supprimer',
            'onclick' => 'deleteSedaObject(this);'
        );

        if ($required || $name == $this->root) {
            $deleteBtnOpts['style'] = 'display:none;';
            $deleteBtnOpts['class'] .= ' disabled';
        }

        echo $this->Html->tag('i', '', $deleteBtnOpts);

        if (!empty($definition) && count(explode(' ', $definition)) <= 3) {
            $legend = htmlspecialchars($definition);
            $title = $name;
        } else {
            $legend = $name;
            $title = $definition;
        }

        echo $this->Html->tag('legend',
            "<i class='icon-collapse-alt icone-plier'></i>&nbsp;$legend",
            array(
                'title' => $title,
                'style' => 'cursor:pointer;',
                'onclick' => 'collapse(this)'
            ));

        //Début bloc sedaFields
        echo $this->Html->tag('div', null, array('class' => 'seda-container sedafields'));

        foreach ($object['@elements'] as $element_id => $element) {
            //Traitement du champ
            $element_name = $element['@definition']['name'];
            $tmpData = !empty($data[$element_name]) && !$template ? $data[$element_name] : array();
            $tmpPath = $path . '.' . $element_name;
            switch ($element['@type']) {
                case 'field':
                    $this->parseSedaField($element, $tmpData, $tmpPath, $template);
                    break;
                case 'object':
                    if ($element['@uid'] == $uid)
                        $this->parseSedaCloneChild($uid, $element, $tmpData, $tmpPath);
                    else
                        $this->parseSedaChild($element['@uid'], $element, $tmpData, $tmpPath);
                    break;
                default:
                    debug('Erreur de type pour ' . $element_name);
            }
        }
        //Fin bloc sedaFields
        echo $this->Html->tag('/div');
        echo $this->Html->tag('/fieldset');
    }

    /**
     * Bloc objet vide (objet blacklisté au parsage, remplacement par select)
     * @param array $object
     * @param array $data
     * @param string $path
     * @param bool $template
     * @param bool $required
     */
    private function parseLinkedObject($object, $data, $path, $template = false, $required = false) {
        $name = $object['@documentation']['PropertyTerm'];
        //Légende
        $definition = '';
        if (!empty($object['@documentation']['Definition']))
            $definition = self::superTrim($object['@documentation']['Definition']);
        elseif (!empty($object['@documentation']['DefinitionText']))
            $definition = $object['@documentation']['DefinitionText'];

        //Options du fieldset
        $opts = array(
            'class' => "sedafieldset sedafieldset-$name expanded",
            'data-name' => $this->pathToName($path),
            'data-title' => $name,
            'data-definition' => htmlspecialchars($definition)
        );

        //Template ?
        if ($template) {
            $opts['style'] = 'display:none';
            $opts['class'] .= ' sedatemplate';
            //Bloc masqué ?
            if ($required && empty($data))
                $this->parseLinkedObject($object, $data, $this->str_lreplace('template', '0', $path));
        }

        //Entourer l'objet Seda d'un fieldset
        echo $this->Html->tag('fieldset', null, $opts);
        //Bouton supprimer
        $deleteBtnOpts = array(
            "class" => "deleteSedaObject icon-remove icon-button pull-right icon-border",
            'title' => 'Supprimer',
            'onclick' => 'deleteSedaObject(this);'
        );

        if ($required) {
            $deleteBtnOpts['style'] = 'display:none;';
            $deleteBtnOpts['class'] .= ' disabled';
        }

        echo $this->Html->tag('i', '', $deleteBtnOpts);

        if (!empty($definition) && count(explode(' ', $definition)) <= 3) {
            $legend = htmlspecialchars($definition);
            $title = $name;
        } else {
            $legend = $name;
            $title = $definition;
        }

        echo $this->Html->tag('legend',
            "<i class='icon-collapse-alt icone-plier'></i>&nbsp;$legend",
            array(
                'title' => $title,
                'style' => 'cursor:pointer;',
                'onclick' => 'collapse(this)'
            ));

        //Début bloc sedaFields
        echo $this->Html->tag('div', null, array('class' => 'seda-container sedafields'));
        echo $this->Html->tag('div', null, array('class' => 'sedafield-container'));
        if ($required || (!$template && !empty($data['id']))) {
            $divOpts = array('class' => 'sedafield');
            if ($required)
                $divOpts['class'] .= ' required';
            echo $this->Html->tag('div', null, $divOpts);
            //Valeur sélectionnée
            if (!$template && !empty($data['id'])) {
                echo $this->Html->tag('input', '', array(
                    'value' => $data['id'],
                    'name' => $this->pathToName($path . '.id'),
                    'type' => 'hidden',
                    'class' => 'model_id',
                    'data-name' => $name
                ));
            } elseif ($required && !$template)
                echo $this->Html->tag('input', '', array(
                    'type' => 'hidden',
                    'data-name' => $name
                ));
            echo $this->Html->tag('/div');
        } else if (!empty($data)) {
            echo $this->Html->tag('div', null, array('class' => 'sedafield required'));
            echo $this->Html->tag('input', '', array(
                'type' => 'hidden',
                'data-name' => $name
            ));
            echo $this->Html->tag('/div');
        }
        echo $this->Html->tag('/div');
        //Fin bloc sedaFields
        echo $this->Html->tag('/div');
        echo $this->Html->tag('/fieldset');
    }

    /**
     * remove new lines & multiple spaces
     * @param $string
     * @return string
     */
    private static function superTrim($string) {
        return trim(preg_replace('/\s\s+/', ' ', $string));
    }

    /**
     * @param array $field
     * @param array $data
     * @param string $path
     * @param bool $template
     */
    private function parseSedaField($field, $data, $path, $template) {
        $name = $field['@definition']['name'];
        $multiple = isset($field['@definition']['maxOccurs']) && $field['@definition']['maxOccurs'] == 'unbounded';
        //Début div sedaField
        $containerClass = 'sedafield-container';
        if ($multiple) $containerClass .= ' sedafield-container-multiple';
        echo $this->Html->tag('div', null, array('class' => $containerClass, 'data-sedaname' => $field['@definition']['name']));
        $definition = self::superTrim($field['@documentation']['Definition']);
        //Options du champ
        $options = array(
            'title' => $definition,
            'id' => str_replace('.', '-', $path),
            'type' => 'text',
            'class' => "sedafield-input sedafield-$name " . $field['@documentation']['ObjectClassTerm'] . $field['@documentation']['PropertyTerm'],
            'div' => array('class' => 'input text sedafield'),
            'name' => $this->pathToName($path . '.@'),
            'data-name' => $field['@definition']['name'],
            'data-base' => $field['@base'],
            'value' => '',
            'after' => ''
        );

        if (count(explode(' ', $definition)) <= self::MAX_LABEL_WORDS) {
            $options['label'] = $field['@definition']['name'] . ' : ' . htmlspecialchars($definition);
            $options['title'] = $field['@definition']['name'] . "\n" . $definition;
        } else {
            $options['label'] = $this->Html->tag('abbr', $field['@definition']['name'], array('title' => $definition));
        }

        $after = '';

        //type date
        if (strpos($field['@definition']['type'], 'udt:Date') !== false) {
            $after .= $this->Html->tag('span', '<i data-time-icon="icon-time" data-date-icon="icon-calendar"></i>', array('class' => 'add-on'));
            if ($field['@definition']['type'] == 'udt:DateType') {
                $options['div']['class'] .= ' datepicker input-append date';
                $options['data-format'] = $options['placeholder'] = 'yyyy-MM-dd';
            } elseif ($field['@definition']['type'] == 'udt:DateTimeType') {
                $options['div']['class'] .= ' datetimepicker input-append datetime';
                $options['data-format'] = $options['placeholder'] = 'yyyy-MM-dd hh:mm:ss';
            }
        }
        if ($field['@base'] == 'xsd:boolean') {
            $options['type'] = "select";
            $options['empty'] = true;
            $options['options'] = array('true' => 'true - Oui', 'false' => 'false - Non');
        }
        if ($field['@definition']['type'] == 'udt:TextType')
            $options['type'] = 'textarea';
        //TODO HTML5 (CakePHP >= v2)
        /*
        elseif ($field['@base'] == 'xsd:decimal') {
            $options['type'] = "number";
            $options['step'] = "any";
        }
        */

        $required = !isset($field['@definition']['minOccurs']) || $field['@definition']['minOccurs'] == '1';
        if ($required)
            $options['div']['class'] .= ' required';
        if ($template)
            $options['disabled'] = true;

        //Affichage du bouton pour afficher/masquer les attributs
        if (!empty($field['@attributes'])) {
            $after .= $this->Html->tag('i', '', array(
                'class' => 'toggleAttributes icon-tag',
                'title' => "Affichage des attributs de \"$name\"",
                'style' => "cursor:pointer;",
                'onClick' => "toggleAttributes(this);"
            ));
        }
        //Valeur du champ
        if (!$template && !empty($data)) {
            if (is_string($data)) {
                $options['value'] = $data;
            } elseif (is_array($data)) {
                if ($this->isAssoc($data)) {
                    if (!empty($data['@']))
                        $options['value'] = $data['@'];
                } elseif (!empty($data[0])) {
                    $tmpdata = $data[0];
                    if (is_string($tmpdata)) {
                        $options['value'] = $tmpdata;
                    } elseif (is_array($data)) {
                        if (!empty($tmpdata['@']))
                            $options['value'] = $tmpdata['@'];
                    }
                }
            }
        }

        $dlFullList = '';
        $listName = '';
        if (!empty($field['@codes']) && !empty($this->sedatree['@codes'][$field['@codes']])) {
            $tmpCodes = explode(':', $field['@codes']);
            $dlFullList = $this->Html->tag('i', '', array(
                'class' => 'downloadCompleteSedaList icon-book',
                'title' => "Télécharger la liste complète des options",
                'style' => "cursor:pointer;",
                'onClick' => "downloadCompleteSedaList(this, '$tmpCodes[1]');"
            ));
            $options['type'] = 'select';
            $options['options'] = array();
            $options['empty'] = true;
            $options['data-codes'] = $tmpCodes[1];
            $listName = !empty($this->sedatree['@codes'][$field['@codes']]['@simplified']) ? '@simplified' : '@complete';
            $optionsToUnset = '';

            foreach ($this->sedatree['@codes'][$field['@codes']][$listName] as $value => $desc) {
                if (!empty($desc['Description'])) {
                    $options['options'][$value] = array(
                        'value' => $value,
                        'name' => $value . ' - ' . $desc['Name'],
                        'data-title' => $desc['Description']
                    );
                } else {
                    $options['options'][$value] = $value . ' - ' . $desc['Name'];
                }
            }

            if (!empty($options['value'])
                && $listName == '@simplified'
                && !array_key_exists($options['value'], $this->sedatree['@codes'][$field['@codes']]['@simplified'])
                && array_key_exists($options['value'], $this->sedatree['@codes'][$field['@codes']]['@complete'])
            ) {
                $optionsToUnset = $options['value'];
                $current = $this->sedatree['@codes'][$field['@codes']]['@complete'][$options['value']];
                if (!empty($current['Description'])) {
                    $options['options'][$options['value']] = array(
                        'value' => $options['value'],
                        'name' => $options['value'] . ' - ' . $current['Name'],
                        'data-title' => $current['Description']
                    );
                } else {
                    $options['options'][$options['value']] = $options['value'] . ' - ' . $current['Name'];
                }
                ksort($options['options'], $this->isAssoc($options['options']) ? SORT_LOCALE_STRING : SORT_NUMERIC);
            }
            $options['selected'] = empty($options['value']) ? '' : $options['value'];
        }
        $options['after'] = $after;

        if ($listName == '@simplified')
            $options['after'] .= $dlFullList;

        if ($multiple) {
            $options['selected'] = $options['type'] == 'select' && !empty($options['value']) ? $options['value'] : '';

            //Bouton ajouter
            $options['after'] = $this->Form->button('+', array(
                    'type' => 'button',
                    'style' => 'width: 25px;',
                    'class' => 'addSedaField',
                    'data-title' => $field['@definition']['name'],
                    'title' => 'Ajouter un nouveau ' . $field['@definition']['name'],
                    'onClick' => "addSedaField(this);"
                )) . $after;

            if ($listName == '@simplified')
                $options['after'] .= $dlFullList;

            $tmppath = $path . '.0.@';
            $options['name'] = $this->pathToName($tmppath);
            $options['id'] = str_replace('.', '-', $path . '.0');

            echo $this->Html->tag('input', '', array(
                'type' => 'hidden',
                'class' => 'count',
                'value' => empty($data) ? 1 : count($data),
                'disabled' => true
            ));

            //Insertion du premier champ avec son bouton
            echo $this->Html->tag('div', null, array('class' => 'sedafield-multiple'));
            echo $this->Form->input($path . '.0', $options);
            if (!empty($optionsToUnset))
                unset($options['options'][$optionsToUnset]);
            if (array_key_exists('@attributes', $field)) {
                $tmpdata = !empty($data[0]) ? $data[0] : $data;
                if (is_string($tmpdata))
                    $tmpdata = array();
                $this->parseSedaAttributes($field['@attributes'], $tmpdata, $path . '.0', $template);
            }
            echo $this->Html->tag('/div');

            if (is_array($data) && !$this->isAssoc($data) && count($data) > 1) {
                //Bouton supprimer
                $options['after'] = $this->Form->button('-', array(
                        'style' => 'width: 25px;',
                        'class' => 'removeSedaField',
                        'title' => 'Supprimer cette propriété',
                        'onClick' => "deleteSedaField(this);"
                    )) . $after;
                if ($listName == '@simplified')
                    $options['after'] .= $dlFullList;

                for ($i = 1; $i < count($data); $i++) {
                    $optionsToUnset = '';
                    //Valeur du champ (compatibilité avec les différents cas)
                    if (array_key_exists('value', $options))
                        unset($options['value']);
                    if (is_string($data[$i])) {
                        $options['value'] = $data[$i];
                    } elseif (is_array($data[$i])) {
                        if (!empty($data[$i]['@']))
                            $options['value'] = $data[$i]['@'];
                        elseif (!empty($data[$i]['value']))
                            $options['value'] = $data[$i]['value'];
                    }

                    if ($options['type'] == 'select') {
                        if ($listName == '@simplified') {
                            if (!empty($options['value'])
                                && !array_key_exists($options['value'], $this->sedatree['@codes'][$field['@codes']]['@simplified'])
                                && array_key_exists($options['value'], $this->sedatree['@codes'][$field['@codes']]['@complete'])
                            ) {
                                $optionsToUnset = $options['value'];
                                $current = $this->sedatree['@codes'][$field['@codes']]['@complete'][$options['value']];
                                if (!empty($current['Description'])) {
                                    $options['options'][$options['value']] = array(
                                        'value' => $options['value'],
                                        'name' => $options['value'] . ' - ' . $current['Name'],
                                        'data-title' => $current['Description']
                                    );
                                } else {
                                    $options['options'][$options['value']] = $options['value'] . ' - ' . $current['Name'];
                                }
                                ksort($options['options'], $this->isAssoc($options['options']) ? SORT_LOCALE_STRING : SORT_NUMERIC);
                            }
                        }
                        $options['selected'] = empty($options['value']) ? '' : $options['value'];
                    }

                    echo $this->Html->tag('div', null, array('class' => 'sedafield-multiple'));
                    $tmppath = $path . '.' . $i . '.@';
                    $options['name'] = $this->pathToName($tmppath);
                    echo $this->Form->input($path . '.' . $i, $options);

                    if (!empty($optionsToUnset))
                        unset($options['options'][$optionsToUnset]);

                    if (array_key_exists('@attributes', $field)) {
                        //Valeurs des attributs
                        $tmpdata = is_array($data[$i]) ? $data[$i] : array();
                        $this->parseSedaAttributes($field['@attributes'], $tmpdata, $path . '.' . $i );
                    }
                    echo $this->Html->tag('/div');
                }
            }
        }
        else { //Champ normal (unique)
            //Insertion du champ
            echo $this->Form->input($path, $options);
            //Traitement des attributs
            $data = is_array($data) ? $data : array();
            if (array_key_exists('@attributes', $field))
                $this->parseSedaAttributes($field['@attributes'], $data, $path, $template);
        }
        //Fin bloc sedafield
        echo $this->Html->tag('/div');
    }

    /**
     * Bloc attributs de champ seda
     * @param array $attributes
     * @param array $data
     * @param string $path
     * @param bool $template
     */
    private function parseSedaAttributes($attributes, $data, $path, $template = false) {
        foreach ($attributes as $i => $attribute) {
            //utilisation attribut interdit
            if (!empty($attribute['@definition']['use']) && $attribute['@definition']['use'] == 'prohibited')
                unset($attributes[$i]);
        }
        if (!empty($attributes)) {
            echo $this->Html->tag('fieldset', null, array('class' => 'attributs', 'style' => "display:none;"));
            echo $this->Html->tag('legend', "<i class='icon-tags'></i> " . __('Attributs de l\'élément'));
            foreach ($attributes as $attribute) {
                //utilisation attribut interdit
                if ($attribute['@definition']['use'] == 'prohibited') continue;
                $attrPath = $path . '.@' . $attribute['@definition']['name'];
                //Options du champ attribut
                $options = array(
                    'id' => str_replace('.', '-', $attrPath),
                    'type' => 'text',
                    'class' => 'seda-attribute ' . $attribute['@definition']['name'],
                    'name' => $this->pathToName($attrPath),
                    'data-name' => $attribute['@definition']['name']
                );

                // Type date ?
                if ($attribute['@definition']['type'] == 'xsd:date') {
                    $options['after'] = $this->Html->tag('span', '<i data-date-icon="icon-calendar"></i>', array('class' => 'add-on'));
                    $options['div'] = array('class' => ' datepicker input-append date');
                    $options['data-format'] = $options['placeholder'] = 'yyyy-MM-dd';
                } elseif ($attribute['@definition']['type'] == 'xsd:dateTime') {
                    $options['after'] = $this->Html->tag('span', '<i data-time-icon="icon-time" data-date-icon="icon-calendar"></i>', array('class' => 'add-on'));
                    $options['div']['class'] .= ' datetimepicker input-append datetime';
                    $options['data-format'] = $options['placeholder'] = 'yyyy-MM-dd hh:mm:ss';
                } elseif ($attribute['@definition']['type'] == 'xsd:boolean') {
                    $options['type'] = "select";
                    $options['empty'] = true;
                    $options['options'] = array('true' => 'Oui', 'false' => 'Non');
                }
                //TODO HTML5 (CakePHP >= v2)
                /*
                elseif ($attribute['@definition']['type'] == 'xsd:decimal') {
                    $options['type'] = "number";
                    $options['step'] = "any";
                }
                */
                // valeur attribut
                if (!empty($attribute['@definition']['name']))
                    $options['label'] = $attribute['@definition']['name'];
                if (!empty($attribute['@documentation']['Name']))
                    $options['title'] = $attribute['@documentation']['Name'];
                if (!empty($attribute['@documentation']['Definition']))
                    $options['title'] .= "\n" . self::superTrim($attribute['@documentation']['Definition']);
                if (!empty($attribute['@definition']['use']) && $attribute['@definition']['use'] == 'required')
                    $options['div']['class'] = 'required';
                if (!empty($attribute['@definition']['default'])) {
                    $options['placeholder'] = $options['value'] = $options['data-default'] = $attribute['@definition']['default'];
                }
                if (!empty($attribute['@definition']['fixed'])) {
                    $options['value'] = $options['data-default'] = $attribute['@definition']['fixed'];
                    $options['readonly'] = true;
                }
                if ($template)
                    $options['disabled'] = true;
                elseif (is_array($data) && array_key_exists('@'.$attribute['@definition']['name'], $data) && empty($attribute['@definition']['fixed']))
                    $options['value'] = $data['@'.$attribute['@definition']['name']];

                //Liste de codes ?
                if (!empty($attribute['@codes']) && !empty($this->sedatree['@codes'][$attribute['@codes']])) {
                    $options['type'] = 'select';
                    $tmpCodes = explode(':', $attribute['@codes']);
                    $options['data-codes'] = $tmpCodes[1];
                    $options['empty'] = true;
                    $listName = !empty($this->sedatree['@codes'][$attribute['@codes']]['@simplified']) ? '@simplified' : '@complete';

                    $options['options'] = array();
                    foreach ($this->sedatree['@codes'][$attribute['@codes']][$listName] as $value => $desc) {
                        $options['options'][] = array(
                            'value' => $value,
                            'name' => $value . ' - ' . implode($desc, ' - '),
                            'data-title' => implode($desc, ' - ')
                        );
                    }

                    if ($listName == '@simplified') {
                        $options['after'] = $this->Html->tag('i', '', array(
                            'class' => 'downloadCompleteSedaList icon-book',
                            'title' => "Télécharger la liste complète des options",
                            'style' => "cursor:pointer;",
                            'onClick' => "downloadCompleteSedaList(this, '$tmpCodes[1]');"
                        ));
                    }

                    if (!empty($options['value'])
                        && $listName == '@simplified'
                        && !array_key_exists($options['value'], $this->sedatree['@codes'][$attribute['@codes']]['@simplified'])
                        && array_key_exists($options['value'], $this->sedatree['@codes'][$attribute['@codes']]['@complete'])
                    ) {
                        $current = $this->sedatree['@codes'][$attribute['@codes']]['@complete'][$options['value']];
                        if (!empty($current['Description'])) {
                            $options['options'][$options['value']] = array(
                                'value' => $options['value'],
                                'name' => $options['value'] . ' - ' . $current['Name'],
                                'data-title' => $current['Description']
                            );
                        } else {
                            $options['options'][$options['value']] = $options['value'] . ' - ' . $current['Name'];
                        }
                        ksort($options['options'], $this->isAssoc($options['options']) ? SORT_LOCALE_STRING : SORT_NUMERIC);
                    }
                    $options['selected'] = empty($options['value']) ? '' : $options['value'];
                }
                echo $this->Form->input($attribute['@definition']['name'], $options);
            }
            echo $this->Html->tag('/fieldset');
        }
    }

    /**
     * @param string $uid
     * @param array $child
     * @param array $data
     * @param string $path
     */
    private function parseSedaChild($uid, $child, $data, $path) {

        //Titre du conteneur des fieldsets objets
        $title = !empty($child['@definition']['name'])
            ? $child['@definition']['name']
            : '';

        $min = isset($child['@definition']['minOccurs']) ? $child['@definition']['minOccurs'] : '1';
        $max = isset($child['@definition']['maxOccurs']) ? $child['@definition']['maxOccurs'] : '1';

        //Définition du conteneur
        $definition = '';
        if (!empty($child['@documentation']['Definition']))
            $definition = self::superTrim($child['@documentation']['Definition']);
        elseif (!empty($child['@documentation']['DefinitionText']))
            $definition = self::superTrim($child['@documentation']['DefinitionText']);
        $definition_html = htmlspecialchars($definition);
        //innerFieldset
        echo $this->Html->tag('fieldset', null, array(
            'class' => 'innerFieldset expanded ' . $child['@definition']['name'],
            'data-min' => $min,
            'data-max' => $max,
            'title' => $title,
            'data-title' => $title,
            'data-definition' => $definition_html,
            'data-entryname' => $child['@documentation']['DictionaryEntryName'],
            'data-name' => $this->pathToName($path)
        ));

        //Libelle du fieldset conteneur
        $libelle = !empty($definition_html) && count(explode(' ', $definition_html)) <= 3
            ? $definition_html
            : $title;

        $legend = "<i class='icon-collapse-alt icone-plier'></i>&nbsp;$libelle (<span class='nbSedaObjects'>0</span>)";

        echo $this->Html->tag('legend', $legend, array(
            'title' => "$title \n" . $definition,
            'style' => 'cursor:pointer;',
            'onclick' => 'collapse(this)'
        ));

        $required = $min == '1';
        $multiple = $max == 'unbounded';
        $maxone = $max == '1';

        echo $this->Html->tag('input', '', array(
            'type' => 'hidden',
            'class' => $maxone ? 'count maxone' : 'count',
            'value' => '0',
            'disabled' => true
        ));

        $template = false;
        if ($multiple || !$required) {
            $path .= '.template';
            $template = true;
        }

        echo $this->Html->tag('div', null, array('class' => 'seda-container sedaobjects'));

        // peut etre optimisé
        if (empty($this->sedatree['@objects'][$uid])) {
            $this->parseLinkedObject($child, $data, $path, $template, $required);
            $cpt = $required ? 1 : 0;
            if ($template && !empty($data)) {
                if ($this->isAssoc($data)) {
                    $this->parseLinkedObject($child, $data, str_replace('.template', $maxone ? '' : ".$cpt", $path), false, $required);
                } else {
                    foreach ($data as $obj) {
                        $this->parseLinkedObject($child, $obj, str_replace('template', $cpt, $path), false, $required);
                        $cpt++;
                    }
                }
            }
        } else {
            $this->parseSedaObject($uid, $data, $path, $template, $required);
            $cpt = $required ? 1 : 0;
            if ($template && !empty($data)) {
                if ($this->isAssoc($data)) {
                    $this->parseSedaObject($uid, $data, str_replace('.template', $maxone ? '' : ".$cpt", $path));
                } else {
                    foreach ($data as $obj) {
                        $this->parseSedaObject($uid, $obj, str_replace('template', $cpt, $path));
                        $cpt++;
                    }
                }
            }
        }

        echo $this->Html->tag('/div');

        // bouton ajouter
        $options = array(
            'type' => 'button',
            'class' => 'addSedaObject add' . $child['@definition']['name'] . $child['@documentation']['ObjectClassTerm'],
            'title' => 'Ajouter un nouveau bloc ' . $child['@documentation']['DictionaryEntryName'],
            'style' => 'vertical-align: top; margin: 0; padding: 4px;',
            'onClick' => "addSedaObject(this, '$min', '$max');"
        );

        if (!$multiple && ($required || !empty($data)))
            $options['disabled'] = true;

        echo $this->Form->button('Ajouter ' . $child['@definition']['name'], $options);
        echo $this->Html->tag('/fieldset');

    }


    /**
     * @param string $uid
     * @param array $child
     * @param array $data
     * @param string $path
     */
    private function parseSedaCloneChild($uid, $child, $data, $path) {
        $name = $this->uniqueIDToName($uid);
        $documentation = $child['@documentation'];
        $definition = $child['@definition'];

        //Titre du conteneur des fieldsets objets
        if (empty($definition['name']))
            $definition['name'] = $name;

        $datadef = htmlspecialchars(self::superTrim($documentation['Definition']));

        echo $this->Html->tag('fieldset', null, array(
            'class' => 'innerFieldset expanded sedaclone ' . $definition['name'],
            'title' => $definition['name'],
            'data-title' => $definition['name'],
            'data-min' => isset($child['@definition']['minOccurs']) ? $child['@definition']['minOccurs'] : '1',
            'data-max' => isset($child['@definition']['maxOccurs']) ? $child['@definition']['maxOccurs'] : '1',
            'data-definition' => $datadef,
            'data-entryname' => $documentation['DictionaryEntryName'],
            'data-name' => $this->pathToName($path)
        ));

        //Libelle du fieldset conteneur
        $libelle = !empty($datadef) && count(explode(' ', $datadef)) <= 3
            ? $datadef
            : $definition['name'];
        $legend = "<i class='icon-collapse-alt icone-plier'></i>&nbsp;$libelle (<span class='nbSedaObjects'>0</span>)";

        echo $this->Html->tag('legend', $legend, array(
            'title' => "$name \n" . self::superTrim($documentation['Definition']),
            'style' => 'cursor:pointer;',
            'onclick' => 'collapse(this)'
        ));

        $multiple = isset($child['@definition']['maxOccurs']) && $child['@definition']['maxOccurs'] == 'unbounded';
        $maxone = !isset($child['@definition']['maxOccurs']) || $child['@definition']['maxOccurs'] == '1';

        echo $this->Html->tag('input', '', array(
            'type' => 'hidden',
            'class' => $maxone ? 'count maxone' : 'count',
            'value' => '0',
            'disabled' => true
        ));

        echo $this->Html->tag('div', null, array('class' => 'seda-container sedaobjects'));

        $cpt = 0;
        if (!empty($data)) {
            if ($this->isAssoc($data)) {
                $this->parseSedaObject($uid, $data, $path . '.' . $cpt);
            } else {
                foreach ($data as $obj) {
                    $this->parseSedaObject($uid, $obj, $path . '.' . $cpt);
                    $cpt++;
                }
            }
        }
        echo $this->Html->tag('/div');
        // bouton cloner
        $options = array(
            'type' => 'button',
            'class' => 'addSedaObject cloneSedaObject add' . $definition['name'] . $documentation['ObjectClassTerm'],
            'title' => 'Ajouter un nouveau bloc ' . $documentation['DictionaryEntryName'],
            'style' => 'vertical-align: top; margin: 0; padding: 4px;',
            'onClick' => "cloneSedaParentObject(this);",
            'data-title' => $definition['name']
        );
        if (!$multiple && !empty($data))
            $options['disabled'] = true;
        echo $this->Form->button('Ajouter ' . $definition['name'], $options);
        echo $this->Html->tag('/fieldset');
    }

    /**
     * Convertit un chemin en name pour formulaire
     * Exemple : seda02.Organization.BusinessType => data[seda02][Organization][BusinessType]
     * @param string $path
     * @return string
     */
    private function pathToName($path) {
        $name = 'data';
        foreach (explode('.', $path) as $entity) {
            $name .= "[$entity]";
        }
        return $name;
    }

    /**
     * Replace Last Occurence of a String in a String
     * @param $search
     * @param $replace
     * @param $subject
     * @return mixed
     */
    protected function str_lreplace($search, $replace, $subject) {
        $pos = strrpos($subject, $search);

        if ($pos !== false) {
            $subject = substr_replace($subject, $replace, $pos, strlen($search));
        }

        return $subject;
    }

    /**
     * La variable $arr est elle un tableau associatif ou indicé
     * @param array $arr
     * @return bool true si associatif
     */
    public function isAssoc($arr) {
        return array_keys($arr) !== range(0, count($arr) - 1);
    }

}
