<?php

/**
 * Created on 5 avr. 2011
 *
 * Librairie regroupant des fonctions hors MVC
 * utilisation en CakePhp 1.2 : App::import('Lib', 'AppTools');
 * à revoir lors du passage en cake 1.3
 *
 */
class AppTools {

    /**
     * Ajout ou soustrait un délai (xs:duration) à une date
     * @param string $date date
     * @param string $duration délai sous la forme xs:duration
     * @param string $format format de sortie de la date
     * @param string $operateur 'add' pour l'ajout et 'sub' pour la soustraction du délai
     * @return string résultat formaté ou null en cas d'erreur
     */
    function addSubDurationToDate($date, $duration, $format = 'Y-m-d', $operateur = 'add') {
        // initialisation
        $ret = null;
        try {
            $thisDate = new DateTime($date);
            $thisDuration = new DateInterval($duration);
            if ($operateur == 'add')
                $thisDate->add($thisDuration);
            elseif ($operateur == 'sub')
                $thisDate->sub($thisDuration);
            $ret = $thisDate->format($format);
        } catch (Exception $e) {
            debug('Fonction Asalae::addSubDurationToDate : ' . $e->getMessage());
        }
        return $ret;
    }

    /**
     * formate une date issue de la base de donnée
     * @param string $dateBD date issue de la lecture d'un enregistrement en base de données
     * @param string $format format de sortie utilisée par la fonction date()
     * @return string date mise en forme
     */
    function timeFormat($dateBD, $format = 'Y-m-d') {
        if (empty($dateBD)) return '';
        $dateTime = strtotime($dateBD);
        return date($format, $dateTime);
    }

    /**
     * formate une xs:duration sous forme litérale
     * @param string $duration délai sous la forme xs:duration
     * @param string $defaut valeur par défaut en cas de durée nulle
     * @return string délai mise en forme litérale
     */
    public static function durationToString($duration, $defaut = '') {
        // initialisation
        $ret = array();

        if (empty($duration)) return '';

        $thisDuration = new DateInterval($duration);
        $annees = $thisDuration->y;
        $mois = $thisDuration->m;
        $jours = $thisDuration->d;
        $heures = $thisDuration->h;
        $minutes = $thisDuration->i;
        $secondes = $thisDuration->s;
        if ($annees == 1) $ret[] = '1 an';
        elseif ($annees > 1) $ret[] = $annees . ' ans';
        if ($mois > 0) $ret[] = $mois . ' mois';
        if ($jours == 1) $ret[] = '1 jour';
        elseif ($jours > 1) $ret[] = $jours . ' jours';
        if ($heures == 1) $ret[] = '1 heure';
        elseif ($heures > 1) $ret[] = $heures . ' heures';
        if ($minutes == 1) $ret[] = '1 minute';
        elseif ($minutes > 1) $ret[] = $minutes . ' minutes';
        if ($secondes == 1) $ret[] = '1 seconde';
        elseif ($secondes > 1) $ret[] = $secondes . ' secondes';

        if (empty($ret))
            return $defaut;
        else
            return implode(' et ', $ret);
    }

    /**
     * transforme une xs:duration sous forme de tableau
     * @param string $duration délai sous la forme xs:duration
     * @return array délai sous forme de tableau array('year', 'month', 'day', 'hour', 'minute', 'seconde')
     */
    function durationToArray($duration) {
        // initialisation
        $ret = array('year' => 0, 'month' => 0, 'day' => 0, 'hour' => 0, 'minute' => 0, 'seconde' => 0);

        if (!empty($duration)) {
            $thisDuration = new DateInterval($duration);
            $ret['year'] = $thisDuration->y;
            $ret['month'] = $thisDuration->m;
            $ret['day'] = $thisDuration->d;
            $ret['hour'] = $thisDuration->h;
            $ret['minute'] = $thisDuration->i;
            $ret['seconde'] = $thisDuration->s;
        }

        return $ret;
    }

    /**
     * transforme une tableau (array('year', 'month', ...)) en xs:duration ('D1Y...')
     * @param array $duration délai sous forme de tableau array('year', 'month', 'day', 'hour', 'minute', 'seconde')
     * @return string délai sous la forme xs:duration
     */
    function arrayToDuration($duration) {
        // initialisation
        $ret = $periode = $temps = '';
        $defaut = array('year' => 0, 'month' => 0, 'day' => 0, 'hour' => 0, 'minute' => 0, 'seconde' => 0);

        if (empty($duration) || !is_array($duration))
            return '';

        $duration = array_merge($defaut, $duration);
        if (!empty($duration['year'])) $periode .= $duration['year'] . 'Y';
        if (!empty($duration['month'])) $periode .= $duration['month'] . 'M';
        if (!empty($duration['day'])) $periode .= $duration['day'] . 'D';
        if (!empty($duration['hour'])) $temps .= $duration['hour'] . 'H';
        if (!empty($duration['minute'])) $temps .= $duration['minute'] . 'M';
        if (!empty($duration['seconde'])) $temps .= $duration['seconde'] . 'S';

        if (!empty($periode) || !empty($temps)) {
            $ret = 'P' . $periode;
            if (!empty($temps)) $ret .= 'T' . $temps;
        }

        return $ret;
    }


    /**
     * retourne l'uri w3c correspondant au type d'algorithme de calcul d'empreinte
     * @param string $hashType type du hash 'MD5', 'SHA1', 'SHA256'....
     * @return string url w3c du type d'algo de calcul d'empreinte
     *
     */
    function urlHashIdentifier($hashType) {
        $hashType = strtolower($hashType);
        switch ($hashType) {
            case 'md5' :
                return 'http://www.w3.org/TR/2002/REC-xmldsig-core-20020212/Overview.html#ref-MD5';
                break;
            case 'sha1' :
                return 'http://www.w3.org/2000/09/xmldsig#sha1';
                break;
            case 'sha256' :
                return 'http://www.w3.org/2001/04/xmlenc#sha256';
                break;
            default:
                return '';
        }
    }


    /**
     * retourne la valeur d'un élément dans un tableau structuré en SedaData :
     * array(
     *    'element1'=>'val1',
     *    'element2'=>array('@value'=>'val2', '@attributes'=>array('attr1'=>'valAttr1', 'attr2'=>'valAttr2')))
     * @param array $sedaData tableau de données structurée
     * @param string $xpath chemin xpath de l'élément a lire sous la forme : ele | ele1/ele2/ele3
     * @param mixed $default valeur par défaut
     * @return mixed|null
     */
    function valSedaData(&$sedaData, $xpath, $default = null) {
        // initialisations
        $ret = null;

        $ele = Apptools::eleSedaData($sedaData, $xpath);

        if (!empty($ele)) {
            if (is_array($ele)) {
                if (!empty($ele['@value']))
                    $ret = $ele['@value'];
            } else
                $ret = $ele;
        }

        if (empty($ret))
            $ret = $default;

        return $ret;
    }

    /**
     * retourne l'attribut d'un élément dans un tableau structuré en SedaData :
     * array(
     *    'element1'=>'val1',
     *    'element2'=>array('@value'=>'val2', '@attributes'=>array('attr1'=>'valAttr1', 'attr2'=>'valAttr2')))
     * @param array $sedaData tableau de données structurée
     * @param string $xpath chemin xpath de l'élément a lire sous la forme : ele | ele1/ele2/ele3
     * @param string $attributeName nom de l'attribut a lire
     * @param mixed $default valeur par défaut
     * @return mixed
     */
    function attrSedaData(&$sedaData, $xpath, $attributeName, $default = null) {
        return Apptools::eleSedaData($sedaData, $xpath . '/@attributes/' . $attributeName, array('defaut' => $default));
    }

    /**
     * retourne un élément dans un tableau structuré en SedaData :
     * array(
     *    'element1'=>'val1',
     *    'element2'=>array('@value'=>'val2', '@attributes'=>array('attr1'=>'valAttr1', 'attr2'=>'valAttr2')))
     * @param array $sedaData tableau de données structurée
     * @param string $xpath chemin xpath de l'élément a lire sous la forme : ele | ele1/ele2/ele3
     * @param array $options paramètres optionnels de la fonction :
     *    'defaut' => mixed; valeur par défaut; defaut null
     *    'cardinaliteMax' => string; '1' retourne l'élément, 'n' retourne un tableau avec clés numériques; defaut : '1'
     * @return mixed élément trouvé dans le tableau ou valeur par défaut
     */
    function eleSedaData(&$sedaData, $xpath, $options = array()) {
        // initialisations
        $defaultOptions = array(
            'defaut' => null,
            'cardinaliteMax' => '1');
        $options = array_merge($defaultOptions, $options);
        $ret = $sedaData;

        // recherche de l'élément
        $elePaths = explode('/', $xpath);
        foreach ($elePaths as $elePath) {
            if (is_array($ret) && !empty($ret[$elePath]))
                $ret = & $ret[$elePath];
            else {
                $ret = null;
                break;
            }
        }

        if (empty($ret))
            $ret = $options['defaut'];
        elseif ($options['cardinaliteMax'] == 'n' && is_array($ret) && !is_numeric(key($ret)))
            $ret = array($ret);

        return $ret;
    }

    /**
     * @param int|string $size
     * @param string $unit (GB|MB|KB)
     * @return string
     */
    public static function getReadableSize($size, $unit = "") {
        if ((!$unit && $size >= 1 << 30) || $unit == "GB")
            return number_format($size / (1 << 30), 2) . "GB";
        if ((!$unit && $size >= 1 << 20) || $unit == "MB")
            return number_format($size / (1 << 20), 2) . "MB";
        if ((!$unit && $size >= 1 << 10) || $unit == "KB")
            return number_format($size / (1 << 10), 2) . "KB";
        return number_format($size) . " bytes";
    }

    /**
     * @param string $date date locale (bdd)
     * @param string $input format si omis : 'Y-m-d H:i:s'
     * @return string date au format ISO8601
     */
    public static function encodeDate($date = null, $input = 'Y-m-d H:i:s') {
        if ($date == null) $date = date($input);
        $date = new DateTime($date);
        return $date->format('c');
    }

    /**
     * @param string $date date locale (bdd)
     * @param string $output format si omis : 'Y-m-d H:i:s'
     * @return string date au format ISO8601
     */
    public static function decodeDate($date, $output = 'Y-m-d H:i:s') {
        $date = new DateTime($date);
        return $date->format($output);
    }

    /**
     * @param string $date date
     * @param null|DateTimeZone $timezone
     * @param string $input si omis : 'Y-m-d H:i:s'
     * @param string $output si omis : 'H:i:s d/m/Y'
     * @return string date au format français
     */
    public static function getReadableDate($date, $timezone = null, $input = 'Y-m-d H:i:s', $output = 'H:i:s d/m/Y') {
        $date = DateTime::createFromFormat($input, $date, $timezone);
        return $date->format($output);
    }

    /**
     * La variable $arr est elle un tableau associatif ou indicé
     * @param array $arr
     * @return bool true si associatif
     */
    public static function isAssoc($arr) {
        return array_keys($arr) !== range(0, count($arr) - 1);
    }
}
