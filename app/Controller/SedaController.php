<?php
class SedaController extends AppController {

    public $helpers = array('Seda');

    /**
     * Liste de tous les transferts conformes
     */
    public function index() {
	    //To customize
    }


    /**
     * Ajout d'un bordereau
     */
    public function addInteractif($version_seda = null) {
        // intialiations
        if (empty($version_seda) || !in_array($version_seda, array(VERSION_SEDA_V0_2, VERSION_SEDA_V1_0))) {
            // affichage du message d'erreur
            $this->Session->setFlash('Erreur de version SEDA', 'growl', array('type' => 'erreur'));
            return $this->redirect(array('action' => 'sortants_index'));
        }

        //init editeur interractif
        App::import('Lib', 'SedaSchemaParser');
        App::import('Lib', 'AppTools');
        $duration_list = array();
        for ($i = 1; $i <= 100; $i++) {
            $duration = 'P' . $i . 'Y';
            $duration_list[$duration] = AppTools::durationToString($duration);
        }
        $this->set('durations', $duration_list);
        $this->pageTitle = Configure::read('appName') . ' : ' . __('Transferts d\'archives') . ' : ' . __('ajout interactif');
        $this->request->data = array('ArchiveTransfer' => array());
        $this->set('archiveTransferId', 0);
        $titre = __('Ajout d\'un transfert en mode interactif');
        $titre .= ' : SEDA v' . $version_seda;
        $this->set('titre', $titre);
        $this->set('actionRetour', 'sortants_index');

        //Parser schema SEDA
        $blacklist = array('Organization', 'Document', 'ContentDescription', 'AccessRestrictionRule', 'AppraisalRule', 'Archiveobject');
        $sedatree = SedaSchemaParser::schemaToTree($version_seda, 'ArchiveTransfer', null, $blacklist);
        $this->set('sedatree', $sedatree);
        $this->set('version_seda', $version_seda);

        //Services
        $org_id = $this->Session->read("Auth.User.organizationIds");
        $servicesArchive = $this->{$this->modelClass}->ServiceArchive->listeActeurTypeSelonAppartenance('A', $org_id);
        $this->set('servicesArchive', $servicesArchive);
        $servicesVersant = $this->{$this->modelClass}->ServiceArchive->listeActeurTypeSelonAppartenance('V', $org_id);
        $this->set('servicesVersant', $servicesVersant);
        $servicesProducteur = $this->{$this->modelClass}->ServiceArchive->listeActeurTypeSelonAppartenance('P', $org_id);
        $this->set('servicesProducteur', $servicesProducteur);
        $services = $servicesArchive + $servicesVersant + $servicesProducteur;
        ksort($services);
        $this->set('services', $services);

        $this->set('fichiersJointsTypes', array(
            'TARGZ' => __('Un fichier tar.gz regroupant les pièces jointes'),
            'ZIP' => __('Un fichier zip regroupant les pièces jointes'),
            'FICHIERS' => __('Une ou plusieurs pièce(s) jointe(s)')));

        // liste des listes de mots clés
        $this->loadModel('Keywordlist');
        $this->set('keywordLists', $this->Keywordlist->listFields(array('conditions' => array('active' => true))));

        // liste des accords de versements
        $this->set('accords', $this->{$this->modelClass}->Accord->listeAccordsConcernant($this->Session->read("Auth.User.organizationIds")));
        // liste des profils d'archives
        $this->set('profils', $this->{$this->modelClass}->Accord->Profil->listFields(array(
            'conditions' => array(
                'actif' => true,
                'identifiant !=' => 'JNLEVT_ASALAE'
            ))));

        $this->render('editInteractif');
    }

    /**
     * edition d'un bordereau
     * @param integer $id identifiant du transfert sortant
     */
    function editInteractif($id) {
        // initialisations
        $sortie = false;
        App::import('Lib', 'SedaSchemaParser');
        App::import('Lib', 'Array2XML');

        // édition d'un transfert existant
        $this->request->data = $this->{$this->modelClass}->find('first', array(
            'conditions' => array('id' => $id),
            'recursive' => -1
        ));
        if (empty($this->request->data)) {
            $this->Session->setFlash(__('Invalide id pour le') . ' ' . __('transfert') . ' : ' . __('traitement impossible.'), 'growl', array('type' => 'important'));
            $sortie = true;
        } elseif (!$this->{$this->modelClass}->isEditable($id)) {
            $this->Session->setFlash(__('Seuls les transferts en cours d\'édition peuvent être édités') . ' : ' . __('traitement impossible.'), 'growl', array('type' => 'important'));
            $sortie = true;
        } elseif (!is_file($this->request->data[$this->modelClass]['fichier_message'])) {
            $this->Session->setFlash(__('Le fichier du bordereau est introuvable') . ' : ' . __('traitement impossible.'), 'growl', array('type' => 'important'));
            $sortie = true;
        }

        if ($sortie)
            $this->redirect($this->referer());
        else {
            $this->pageTitle = Configure::read('appName') . ' : ' . __('Transferts d\'archives') . ' : ' . __('édition interactive');

            App::import('Lib', 'AppTools');
            $duration_list = array();
            for ($i = 1; $i <= 100; $i++) {
                $duration = 'P' . $i . 'Y';
                $duration_list[$duration] = AppTools::durationToString($duration);
            }

            $this->set('durations', $duration_list);

            App::uses('File', 'Utility');
            $fichier = new File($this->request->data['Archivetransfer']['fichier_message']);
            $this->request->data['seda'] = SedaSchemaParser::xmlToArray($fichier->read());
            //Parser schema SEDA
            $blacklist = array('Organization');
            $sedatree = SedaSchemaParser::schemaToTree($this->request->data[$this->modelClass]['version_seda'], 'ArchiveTransfer', array(), $blacklist);
            $this->set('sedatree', $sedatree);
            ////Retrouver id des organizations
            if (!empty($this->request->data['seda']['ArchiveTransfer']['ArchivalAgency']['Identification'])) {
                $archivalAgency = $this->Archivetransfer->ServiceArchive->find('first', array(
                    'conditions' => array('ServiceArchive.identification' => $this->request->data['seda']['ArchiveTransfer']['ArchivalAgency']['Identification']),
                    'recursive' => -1,
                    'fields' => array('ServiceArchive.id')
                ));
                if (!empty($archivalAgency['ServiceArchive']['id']))
                    $this->request->data['seda']['ArchiveTransfer']['ArchivalAgency']['id'] = $archivalAgency['ServiceArchive']['id'];
            }
            if (!empty($this->request->data['seda']['ArchiveTransfer']['TransferringAgency']['Identification'])) {
                $transferringAgency = $this->Archivetransfer->ServiceVersant->find('first', array(
                    'conditions' => array('identification' => $this->request->data['seda']['ArchiveTransfer']['TransferringAgency']['Identification']),
                    'recursive' => -1,
                    'fields' => array('id')
                ));
                if (!empty($transferringAgency['ServiceVersant']['id']))
                    $this->request->data['seda']['ArchiveTransfer']['TransferringAgency']['id'] = $transferringAgency['ServiceVersant']['id'];
            }

            $containerName = $this->request->data['Archivetransfer']['version_seda'] == VERSION_SEDA_V0_2
                ? 'Contains'
                : 'Archive';
            $subcontainerName = $containerName == 'Archive' ? 'ArchiveObject' : $containerName;
            if (!empty($this->request->data['seda']['ArchiveTransfer'][$containerName])) {
                if (Array2XML::isAssoc($this->request->data['seda']['ArchiveTransfer'][$containerName])) {
                    $this->request->data['seda']['ArchiveTransfer'][$containerName] = $this->_recursiveSedaContainsDataToForm($this->request->data['seda']['ArchiveTransfer'][$containerName], $subcontainerName);
                } else {
                    //Pour chaque archive
                    foreach ($this->request->data['seda']['ArchiveTransfer'][$containerName] as $i => $container) {
                        $this->request->data['seda']['ArchiveTransfer'][$containerName][$i] = $this->_recursiveSedaContainsDataToForm($container, $subcontainerName);
                    }
                }
            }
            if (!empty($this->request->data['seda']['ArchiveTransfer']['Date']))
                $this->request->data['seda']['ArchiveTransfer']['Date'] = AppTools::decodeDate($this->request->data['seda']['ArchiveTransfer']['Date']);


            $this->set('archiveTransferId', $id);
            $titre = __('Edition du transfert ');
            $titre .= ' SEDA v' . $this->request->data[$this->modelClass]['version_seda'];
            if ($this->request->data['Archivetransfer']['transfer_identifier'] != '#A_CALCULER_LORS_DU_VERROUILLAGE#')
                $titre .= ' : ' . $this->request->data['Archivetransfer']['transfer_identifier'];
            $this->set('titre', $titre);

            $this->set('version_seda', $this->request->data[$this->modelClass]['version_seda']);

            //Services
            $org_id = $this->Session->read("Auth.User.organizationIds");
            $servicesArchive = $this->{$this->modelClass}->ServiceArchive->listeActeurTypeSelonAppartenance('A', $org_id);
            $this->set('servicesArchive', $servicesArchive);
            $servicesVersant = $this->{$this->modelClass}->ServiceArchive->listeActeurTypeSelonAppartenance('V', $org_id);
            $this->set('servicesVersant', $servicesVersant);
            $servicesProducteur = $this->{$this->modelClass}->ServiceArchive->listeActeurTypeSelonAppartenance('P', $org_id);
            $this->set('servicesProducteur', $servicesProducteur);
            $services = $servicesArchive + $servicesVersant + $servicesProducteur;
            ksort($services);
            $this->set('services', $services);

            if ($this->request->data[$this->modelClass]['sens'] == SORTANT)
                $this->set('actionRetour', 'sortants_index');
            elseif ($this->request->data[$this->modelClass]['statut'] == EN_COURS_TRAITEMENT)
                $this->set('actionRetour', 'atraiter');
            else
                $this->set('actionRetour', 'nonconformes');

            $this->set('fichiersJointsTypes', array(
                'TARGZ' => __('Un fichier tar.gz regroupant les pièces jointes'),
                'ZIP' => __('Un fichier zip regroupant les pièces jointes'),
                'FICHIERS' => __('Une ou plusieurs pièce(s) jointe(s)')));

            // liste des listes de mots clés
            $this->loadModel('Keywordlist');
            $this->set('keywordLists', $this->Keywordlist->listFields(array('conditions' => array('active' => true))));

            // liste des accords de versements
            $this->set('accords', $this->{$this->modelClass}->Accord->listeAccordsConcernant($this->Session->read("Auth.User.organizationIds")));
            // liste des profils d'archives
            $this->set('profils', $this->{$this->modelClass}->Accord->Profil->listFields(array(
                'conditions' => array(
                    'actif' => true,
                    'identifiant !=' => 'JNLEVT_ASALAE'
                ))));
            // inialisation de la liste des fichiers
            if ($this->request->data['Archivetransfer']['sens'] == SORTANT)
                $repDoc = $this->Gestdir->initRepertoire(array(configure::read('Repertoire.sortie'), 'ArchiveTransfer', $id, 'documents'));
            else
                $repDoc = $this->Gestdir->initRepertoire(array(configure::read('Repertoire.entree'), 'ArchiveTransfer', $id, 'documents'));
            $files = $this->Gestdir->listeRepertoire($repDoc, true);
            $this->FichierSeda->load($this->request->data['Archivetransfer']['fichier_message']);
            $docs = $this->FichierSeda->listeDocuments();
            foreach ($docs as &$doc) {
                $doc = str_replace(array('\\', '/'), DS, $doc);
                if ($doc{0} == DS) $doc = substr($doc, 1);
            }
            $ret = array();
            foreach ($files as &$file) {
                //Mise en forme JSON pour le plugin
                $file['url'] = "/archivetransfers/afficherPieceJointe/" . $id . "/" . $file['nom'];
                $file['name'] = $file['nom'];
                $file['size'] = $file['bytesize'];
                //Droit de supprimer?
                if (!in_array($file['name'], $docs)) {
                    $file['delete_url'] = Router::url(array('action' => 'docUploadAjax', $id, base64_encode($file['name'])), true);
                    $file['delete_type'] = 'DELETE';
                    $file['delete_ckb'] = '1';
                }
                array_push($ret, $file);
            }
            $this->set('files', array("files" => $ret));
        }
    }

    /**
     * @param $container
     * @param $containerName
     * @return mixed
     */
    function _recursiveSedaContainsDataToForm($container, $containerName) {
        App::import('Lib', 'Array2XML');
        App::import('Lib', 'AppTools');
        if (!empty($container['ArchivalAgreement'])) {
            $identifiant = '';
            if (is_string($container['ArchivalAgreement']))
                $identifiant = $container['ArchivalAgreement'];
            elseif (!empty($container['ArchivalAgreement']['@']))
                $identifiant = $container['ArchivalAgreement']['@'];
            if (!empty($identifiant)) {
                $archivalAgreementId = $this->{$this->modelClass}->Accord->field('id', array('identifiant' => $identifiant));
                if (!empty($archivalAgreementId))
                    $container['ArchivalAgreement'] = array('@' => $archivalAgreementId);
            }
        }
        if (!empty($container['ArchivalProfile'])) {
            $identifiant = '';
            if (is_string($container['ArchivalProfile']))
                $identifiant = $container['ArchivalProfile'];
            elseif (!empty($container['ArchivalProfile']['@']))
                $identifiant = $container['ArchivalProfile']['@'];
            if (!empty($identifiant)) {
                $archivalProfileId = $this->{$this->modelClass}->Accord->Profil->field('id', array('identifiant' => $identifiant));
                if (!empty($archivalProfileId))
                    $container['ArchivalProfile'] = array('@' => $archivalProfileId);
            }
        }
        if (!empty($container['ContentDescription'])) {
            if (!empty($container['ContentDescription']['OriginatingAgency'])) {
                if (!Array2XML::isAssoc($container['ContentDescription']['OriginatingAgency'])) {
                    foreach ($container['ContentDescription']['OriginatingAgency'] as $i => $serviceProducteur) {
                        if (empty($serviceProducteur['Identification'])) continue;
                        $identification = $serviceProducteur['Identification'];
                        if (is_array($identification)) {
                            if (!empty($identification['@']))
                                $identification = $identification['@'];
                            elseif (!empty($identification['value']))
                                $identification = $identification['value'];
                        }
                        //Chercher l'id du service producteur de l'archive
                        $originatingAgency = $this->Archivetransfer->ServiceProducteur->find('first', array(
                            'conditions' => array('identification' => $identification),
                            'recursive' => -1,
                            'fields' => array('id')
                        ));
                        $container['ContentDescription']['OriginatingAgency'][$i]['id'] = $originatingAgency['ServiceProducteur']['id'];
                    }
                } else {
                    if (!empty($container['ContentDescription']['OriginatingAgency']['Identification'])) {
                        $identification = $container['ContentDescription']['OriginatingAgency']['Identification'];
                        if (is_array($identification)) {
                            if (!empty($identification['@']))
                                $identification = $identification['@'];
                            elseif (!empty($identification['value']))
                                $identification = $identification['value'];
                        }
                        //Chercher l'id du service producteur de l'archive
                        $originatingAgency = $this->Archivetransfer->ServiceProducteur->find('first', array(
                            'conditions' => array('identification' => $identification),
                            'recursive' => -1,
                            'fields' => array('id')
                        ));
                        $container['ContentDescription']['OriginatingAgency']['id'] = $originatingAgency['ServiceProducteur']['id'];
                    }
                }
            }

            if (!empty($container['ContentDescription']['Repository'])) {
                if (!empty($container['ContentDescription']['Repository']['Identification'])) {
                    $identification = $container['ContentDescription']['Repository']['Identification'];
                    if (is_array($identification)) {
                        if (!empty($identification['@']))
                            $identification = $identification['@'];
                        elseif (!empty($identification['value']))
                            $identification = $identification['value'];
                    }
                    //Chercher l'id du repository de l'archive
                    $agency = $this->Archivetransfer->ServiceProducteur->find('first', array(
                        'conditions' => array('identification' => $identification),
                        'recursive' => -1,
                        'fields' => array('id')
                    ));
                    $container['ContentDescription']['Repository']['id'] = $agency['ServiceProducteur']['id'];
                }
            }
        }

        //Chargement filepath document
        if (!empty($container['Document'])) {
            if (!Array2XML::isAssoc($container['Document'])) {
                foreach ($container['Document'] as $i => $document) {
                    $container['Document'][$i] = $this->_prepareDocumentToForm($document);
                }
            } else {
                if (!empty($container['Document']['Attachment']['filename'])) {
                    $container['Document']['Attachment']['@'] = $container['Document']['Attachment']['filename'];
                }
                $container['Document'] = $this->_prepareDocumentToForm($container['Document']);
            }
        }

        //Appel recursif de la fonction si enfant(s)
        if (!empty($container[$containerName]))
            if (!Array2XML::isAssoc($container[$containerName]))
                foreach ($container[$containerName] as $i => $contain)
                    $container[$containerName][$i] = $this->_recursiveSedaContainsDataToForm($container[$containerName][$i], $containerName);
            else
                $container[$containerName] = $this->_recursiveSedaContainsDataToForm($container[$containerName], $containerName);

        return $container;
    }

    /**
     * Prépare les données associées aux Document provenant du formulaire d'édition intéractive
     * @param array $document
     * @return array
     */
    private function _prepareDocumentToData($document) {
        $document = $this->_recursiveSedaRelatedFormToData($document);
        //RAZ valeur Attachment (filename préféré)
        if (!empty($document['Attachment']['@']))
            $document['Attachment']['@'] = '';
        //Encodage des dates
        if (!empty($document['Creation']['@']))
            $document['Creation']['@'] = AppTools::encodeDate($document['Creation']['@']);
        if (!empty($document['Issue']['@']))
            $document['Issue']['@'] = AppTools::encodeDate($document['Issue']['@']);
        if (!empty($document['Receipt']['@']))
            $document['Receipt']['@'] = AppTools::encodeDate($document['Receipt']['@']);
        if (!empty($document['Response']['@']))
            $document['Response'] = AppTools::encodeDate($document['Response']['@']);
        if (!empty($document['Submission']['@']))
            $document['Submission']['@'] = AppTools::encodeDate($document['Submission']['@']);

        //fix seda v0.2 Attachment multiple
        if (!empty($document['Attachment'][0]['@']))
            $document['Attachment'][0]['@'] = '';
        return $document;
    }

    /**
     * Prépare les données associées aux Document provenant d'un xml (converti en array) pour l'affichage en formulaire d'édition intéractive
     * @param array $document
     * @return array
     */
    private function _prepareDocumentToForm($document) {
        if (!empty($document['Attachment']['@filename'])) {
            $document['Attachment']['@'] = $document['Attachment']['@filename'];
        }
        $document = $this->_recursiveSedaRelatedDataToForm($document);

        //Décodage des dates
        if (!empty($document['Creation']))
            $document['Creation'] = AppTools::decodeDate($document['Creation']);
        if (!empty($document['Issue']))
            $document['Issue'] = AppTools::decodeDate($document['Issue']);
        if (!empty($document['Receipt']))
            $document['Receipt'] = AppTools::decodeDate($document['Receipt']);
        if (!empty($document['Response']))
            $document['Response'] = AppTools::decodeDate($document['Response']);
        if (!empty($document['Submission']))
            $document['Submission'] = AppTools::decodeDate($document['Submission']);

        return $document;
    }

    /**
     * @param $document
     * @return mixed
     */
    function _recursiveSedaRelatedDataToForm($document) {
        if (!empty($document['RelatedData'])) {
            if (!Array2XML::isAssoc($document['RelatedData'])) {
                foreach ($document['RelatedData'] as $i => $related) {
                    if (!empty($related['Data']['@filename']))
                        $document['RelatedData'][$i]['Data']['@'] = $related['Data']['@filename'];
                    if (!empty($related['RelatedData']))
                        $document['RelatedData'][$i] = $this->_recursiveSedaRelatedDataToForm($related);
                }
            } else {
                if (!empty($document['RelatedData']['Data']['@filename']))
                    $document['RelatedData']['Data']['@'] = $document['RelatedData']['Data']['@filename'];

                if (!empty($document['RelatedData']))
                    $document['RelatedData'] = $this->_recursiveSedaRelatedDataToForm($document['RelatedData']);
            }
        }
        return $document;
    }

    /**
     * Sauvegarde des données de l'éditeur intéractif de bordereau (add/edit)
     * @return mixed
     */
    function saveSedaForm() {
        if (empty($this->request->data)) {
            $this->Session->setFlash(__('Une erreur s\'est produite.'), 'growl', array('type' => 'important'));
            return $this->redirect($this->referer());
        }
        //Init
        $success = true;
        $this->_prepareSedaData();
        $xml = SedaSchemaParser::arrayToXml($this->request->data['seda']); //XML
        if ($this->request->data['Archivetransfer']['version_seda'] == VERSION_SEDA_V0_2)
            $this->request->data['Archivetransfer']['comment'] = $this->request->data['seda']['ArchiveTransfer']['Comment']['@'];
        elseif ($this->request->data['Archivetransfer']['version_seda'] == VERSION_SEDA_V1_0) {
            //Garder en bdd le premier commentaire non vide
            foreach ($this->request->data['seda']['ArchiveTransfer']['Comment'] as $comment)
                if (!empty($comment['@'])) {
                    $this->request->data['Archivetransfer']['comment'] = $comment['@'];
                    break;
                }
        }
        $this->request->data['Archivetransfer']['date'] = $this->request->data['seda']['ArchiveTransfer']['Date']['@'];
        $this->request->data['Archivetransfer']['transfer_identifier'] = $this->request->data['seda']['ArchiveTransfer']['TransferIdentifier']['@'];
        $this->request->data['Archivetransfer']['transfer_request_reply_identifier'] = $this->request->data['seda']['ArchiveTransfer']['TransferRequestReplyIdentifier']['@'];
        $this->request->data['Archivetransfer']['related_transfer_reference'] = $this->request->data['seda']['ArchiveTransfer']['RelatedTransferReference']['@'];
        $this->request->data['Archivetransfer']['modified_user_id'] = $this->Auth->user('id');

        //Edition
        if (!empty($this->request->data['Archivetransfer']['archiveTransferId'])) {
            $id = $this->request->data['Archivetransfer']['archiveTransferId'];
            $this->{$this->modelClass}->id = $id;
            $this->request->data['Archivetransfer']['fichier_message'] = $this->Archivetransfer->field('fichier_message');
            $this->Session->setFlash(__('Transfert modifié'), 'growl', array('type' => 'important'));
        } else { //Ajout
            $this->{$this->modelClass}->create();
            $this->request->data['Archivetransfer']['sens'] = SORTANT;
            $this->request->data['Archivetransfer']['origine'] = INTERACTIF;
            $this->request->data['Archivetransfer']['statut'] = EDITION;
            $this->request->data['Archivetransfer']['created_user_id'] = $this->Auth->user('id');
            $success &= $this->{$this->modelClass}->save($this->request->data);
            // récupération de l'id du nouvel enregistrement créé en base pour les sauvegardes suivantes
            $id = $this->{$this->modelClass}->id;
            $this->Session->setFlash(__('Transfert créé'), 'growl', array('type' => 'important'));
        }

        // création du répertoire de sortie
        $repDest = $this->Gestdir->initRepertoire(array(Configure::read('Repertoire.sortie'), 'ArchiveTransfer', $id));
        $this->request->data['Archivetransfer']['fichier_message'] = $repDest . 'noname.xml';
        $this->request->data['Archivetransfer']['repertoire_documents'] = $this->Gestdir->initRepertoire(array($repDest, 'documents'));

        // création du bordereau
        $fichier = new File($this->request->data['Archivetransfer']['fichier_message'], true);
        $fichier->write($xml);
        $fichier->close();
        $size = 0;
        foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($this->request->data['Archivetransfer']['repertoire_documents'])) as $file) {
            $size += $file->getSize();
        }
        $this->request->data['Archivetransfer']['taille_documents_octets'] = $size;

        $success &= $this->{$this->modelClass}->save($this->request->data);

        if ($success) {
            // Enregistrement dans le journal des évènements
            $evtJnl = array('Journal' => array(
                'foreign_key' => $id
            ));
            if (empty($this->request->data['Archivetransfer']['archiveTransferId'])) {
                // Enregistrement dans le journal des évènements
                $evtJnl['Journal']['evenement_nom'] = 'Transferts : ajout d\'un transfert d\'archives en mode interactif';
                $evtJnl['Journal']['message'] = 'Modification d\'un transfert d\'archive par formulaire interactif (id: \'' . $id . '\') par ' . $this->Auth->user('toString');
            } else {
                // Enregistrement dans le journal des évènements
                $evtJnl['Journal']['evenement_nom'] = 'Transferts : edition d\'un transfert d\'archives en mode interactif';
                $evtJnl['Journal']['message'] = 'Ajout d\'un transfert d\'archive par formulaire interactif (id: \'' . $id . '\') par ' . $this->Auth->user('toString');
            }

            $this->Journalisation->ajoutEvtJournal($evtJnl, $this);

            if (empty($this->request->data['Archivetransfer']['archiveTransferId'])) {
                $this->Session->setFlash(__('Editeur interactif de transfert d\'archive'), 'growl');
                $this->redirect(array('action' => 'editInteractif', $this->{$this->modelClass}->id));
            } else
                $this->Session->setFlash(__('Transfert d\'archive sauvegardé'), 'growl');
            $this->redirect(array('action' => 'sortants_index'));
        } else {
            $this->Session->setFlash(__('Une erreur est survenue'), 'growl', array('type' => 'erreur'));
            $this->redirect($this->referer());
        }
    }

    /**
     * Prépare les données en provenance du formulaire d'éditeur de transfert avant enregistrement bordereau
     */
    function _prepareSedaData() {
        App::import('Lib', 'SedaSchemaParser');
        App::import('Lib', 'AppTools');
        $containerName = $this->request->data['Archivetransfer']['version_seda'] == VERSION_SEDA_V0_2
            ? 'Contains'
            : 'Archive';
        $sedafield = $this->request->data['Archivetransfer']['version_seda'] == VERSION_SEDA_V1_0
            ? 'seda10'
            : 'seda02';
        //Chercher la description xml du service d'archive
        if (!empty($this->request->data['seda']['ArchiveTransfer']['ArchivalAgency']['id'])) {
            $archivalAgencyId = $this->request->data['seda']['ArchiveTransfer']['ArchivalAgency']['id'];
            $archivalAgency = $this->Archivetransfer->ServiceArchive->find('first', array(
                'conditions' => array('id' => $archivalAgencyId),
                'recursive' => -1,
                'fields' => array($sedafield)
            ));
            $archivalAgencyArray = array();
            if (!empty($archivalAgency['ServiceArchive'][$sedafield])) {
                $archivalAgencyXml = $archivalAgency['ServiceArchive'][$sedafield];
                $archivalAgencyArray = SedaSchemaParser::xmlToArray($archivalAgencyXml);
            }
            if (!empty($archivalAgencyArray['Organization']))
                $this->request->data['seda']['ArchiveTransfer']['ArchivalAgency'] = $archivalAgencyArray['Organization'];
            else
                unset($this->request->data['seda']['ArchiveTransfer']['ArchivalAgency']);

            $this->request->data['Archivetransfer']['archival_agency_id'] = $archivalAgencyId;
        } elseif (isset($this->request->data['seda']['ArchiveTransfer']['ArchivalAgency'])) {
            unset($this->request->data['seda']['ArchiveTransfer']['ArchivalAgency']);
        }

        //Chercher la description xml du service versant
        if (!empty($this->request->data['seda']['ArchiveTransfer']['TransferringAgency']['id'])) {
            $transferringAgencyId = $this->request->data['seda']['ArchiveTransfer']['TransferringAgency']['id'];
            $transferringAgency = $this->Archivetransfer->ServiceVersant->find('first', array(
                'conditions' => array('id' => $transferringAgencyId),
                'recursive' => -1,
                'fields' => array($sedafield)
            ));
            if (!empty($transferringAgency['ServiceVersant'][$sedafield])) {
                $transferringAgencyXml = $transferringAgency['ServiceVersant'][$sedafield];
                $transferringAgencyArray = SedaSchemaParser::xmlToArray($transferringAgencyXml);
            }
            if (!empty($transferringAgencyArray['Organization']))
                $this->request->data['seda']['ArchiveTransfer']['TransferringAgency'] = $transferringAgencyArray['Organization'];
            else
                unset($this->request->data['seda']['ArchiveTransfer']['ArchivalAgency']);
            $this->request->data['Archivetransfer']['transferring_agency_id'] = $transferringAgencyId;
        } elseif (isset($this->request->data['seda']['ArchiveTransfer']['TransferringAgency'])) {
            unset($this->request->data['seda']['ArchiveTransfer']['TransferringAgency']);
        }

        $subcontainerName = $containerName == 'Archive' ? 'ArchiveObject' : $containerName;

        if (!empty($this->request->data['seda']['ArchiveTransfer']['Date']['@']))
            $this->request->data['seda']['ArchiveTransfer']['Date']['@'] = AppTools::encodeDate($this->request->data['seda']['ArchiveTransfer']['Date']['@']);

        //Pour chaque archive
        foreach ($this->request->data['seda']['ArchiveTransfer'][$containerName] as $i => $container) {
            $this->request->data['seda']['ArchiveTransfer'][$containerName][$i] = $this->_recursiveSedaContainsFormToData($container, $subcontainerName, $sedafield);
        }

        //Ajout de l'attribut xmlns au bordereau
        $this->request->data['seda']['ArchiveTransfer']['@xmlns'] = SedaSchemaParser::$sedaNS[$this->request->data['Archivetransfer']['version_seda']];
    }

    /**
     * Fonction récursive qui parcourt les archives et objets d'archive récursivement
     * Remplace les id d'organization (formulaire) par les valeurs en base pour l'organization (xml)
     * Remplace le chemin des documents par leur contenu
     * @param $container
     * @param $containerName
     * @param $sedafield
     * @return mixed
     */
    function _recursiveSedaContainsFormToData($container, $containerName, $sedafield) {
        App::import('Lib', 'SedaSchemaParser');
        App::import('Lib', 'Array2XML');
        App::import('Lib', 'AppTools');
        $this->loadModel('Keyword');
        $this->loadModel('Keywordlist');
        $this->Keyword->Behaviors->attach('Containable');

        //ArchivalAgreement
        if (!empty($container['ArchivalAgreement']['@'])) {
            $archivalAgreementXml = $this->{$this->modelClass}->Accord->field($sedafield, array('id' => $container['ArchivalAgreement']['@']));
            $archivalAgreementArray = SedaSchemaParser::xmlToArray($archivalAgreementXml);
            if (!empty($archivalAgreementArray['ArchivalAgreement']))
                $container['ArchivalAgreement'] = $archivalAgreementArray['ArchivalAgreement'];
        }
        //ArchivalProfile
        if (!empty($container['ArchivalProfile']['@'])) {
            $archivalProfileXml = $this->{$this->modelClass}->Accord->Profil->field($sedafield, array('id' => $container['ArchivalProfile']['@']));
            $archivalProfileArray = SedaSchemaParser::xmlToArray($archivalProfileXml);
            if (!empty($archivalProfileArray['ArchivalProfile']))
                $container['ArchivalProfile'] = $archivalProfileArray['ArchivalProfile'];
        }
        //ContentDescription
        if (!empty($container['ContentDescription'])) {
            //OriginatingAgency
            if (!empty($container['ContentDescription']['OriginatingAgency'])) {
                if (AppTools::isAssoc($container['ContentDescription']['OriginatingAgency'])) {
                    if (!empty($container['ContentDescription']['OriginatingAgency']['id'])) {
                        //Chercher la description xml du service producteur de l'archive
                        $originatingAgency = $this->Archivetransfer->ServiceProducteur->find('first', array(
                            'conditions' => array('id' => $container['ContentDescription']['OriginatingAgency']['id']),
                            'recursive' => -1,
                            'fields' => array($sedafield)
                        ));
                        $originatingAgencyXml = $originatingAgency['ServiceProducteur'][$sedafield];
                        $originatingAgencyArray = SedaSchemaParser::xmlToArray($originatingAgencyXml);
                        $container['ContentDescription']['OriginatingAgency'] = $originatingAgencyArray['Organization'];
                    }
                } else
                    foreach ($container['ContentDescription']['OriginatingAgency'] as $i => $serviceProducteur) {
                        if (empty($serviceProducteur['id'])) continue;
                        //Chercher la description xml du service producteur de l'archive
                        $originatingAgency = $this->Archivetransfer->ServiceProducteur->find('first', array(
                            'conditions' => array('id' => $serviceProducteur['id']),
                            'recursive' => -1,
                            'fields' => array($sedafield)
                        ));
                        $originatingAgencyXml = $originatingAgency['ServiceProducteur'][$sedafield];
                        $originatingAgencyArray = SedaSchemaParser::xmlToArray($originatingAgencyXml);
                        $container['ContentDescription']['OriginatingAgency'][$i] = $originatingAgencyArray['Organization'];
                    }
            }
            //Repository
            if (!empty($container['ContentDescription']['Repository']['id'])) {
                //Chercher la description xml du service producteur de l'archive
                $repository = $this->Archivetransfer->ServiceProducteur->find('first', array(
                    'conditions' => array('id' => $container['ContentDescription']['Repository']['id']),
                    'recursive' => -1,
                    'fields' => array($sedafield)
                ));
                $repositoryXml = $repository['ServiceProducteur'][$sedafield];
                $repositoryArray = SedaSchemaParser::xmlToArray($repositoryXml);
                $container['ContentDescription']['Repository'] = $repositoryArray['Organization'];
            }
            //Keyword
            $keyword_model_name = !empty($container['ContentDescription']['ContentDescriptive']) ? 'ContentDescriptive' : 'Keyword';
            if (!empty($container['ContentDescription'][$keyword_model_name])) {
                foreach ($container['ContentDescription'][$keyword_model_name] as $i => $descriptive) {
                    if (!empty($descriptive['id'])) {
                        $keyword_rec = $this->Keyword->find('first', array(
                            'contain' => array('Keywordlist', 'Keywordlist.Keywordtype'),
                            'conditions' => array('Keyword.id' => $descriptive['id'])
                        ));
                        //TODO évolution : récupérer xml directement et convertir en array
                        $container['ContentDescription'][$keyword_model_name][$i] = array(
                            'KeywordContent' => array(
                                '@' => $keyword_rec['Keyword']['libelle'],
//                              '@attributes' => array('languageID' => '')
                            ),
                            'KeywordReference' => array(
                                '@' => $keyword_rec['Keywordlist']['nom'],
                                    '@schemeID' => $keyword_rec['Keywordlist']['scheme_id'],
                                    '@schemeName' => $keyword_rec['Keywordlist']['scheme_name'],
                                    '@schemeAgencyName' => $keyword_rec['Keywordlist']['scheme_agency_name'],
                                    '@schemeVersionID' => $keyword_rec['Keywordlist']['scheme_version_id'],
                                    '@schemeDataURI' => $keyword_rec['Keywordlist']['scheme_data_uri'],
                                    '@schemeURI' => $keyword_rec['Keywordlist']['scheme_uri']
                            ),
                            'KeywordType' => array(
                                '@' => $keyword_rec['Keywordlist']['Keywordtype']['code'],
                                '@listVersionID' => 'edition 2009'
                            )
                        );
                    }
                }
            }
        }
        //Document & RelatedData (vider les valeurs)
        if (!empty($container['Document'])) {
            foreach ($container['Document'] as $i => $document) {
                $container['Document'][$i] = $this->_prepareDocumentToData($container['Document'][$i]);
            }
        }
        //APPEL RECURSIF
        if (!empty($container[$containerName]))
            foreach ($container[$containerName] as $i => $contain)
                $container[$containerName][$i] = $this->_recursiveSedaContainsFormToData($container[$containerName][$i], $containerName, $sedafield);

        return $container;
    }

    /**
     * @param $document
     * @return mixed
     */
    function _recursiveSedaRelatedFormToData($document) {
        if (!empty($document['RelatedData'])) {
            foreach ($document['RelatedData'] as $i => $related) {
                if (!empty($document['RelatedData'][$i]['Data']['@'])) {
                    $document['RelatedData'][$i]['Data']['@'] = '';
                }
                $document['RelatedData'][$i] = $this->_recursiveSedaRelatedFormToData($document['RelatedData'][$i]);
            }
        }
        return $document;
    }

    /**
     * gestion du téléchargement upload des pièces jointes d'un transfert
     * @param archiveTransferId
     */
    function docUpload($id) {
        $ret = null;
        $sortie = false;
        if (!empty($this->request->data['Archivetransfer']['lienRetour']))
            $lienRetour = $this->request->data['Archivetransfer']['lienRetour'];
        else {
            $referer = $this->referer();
            if (strpos($referer, '/users/login') !== false)
                $lienRetour = '/archivetransfers/sortants_index';
            elseif (strpos($referer, '/archivetransfers/addInteractif') !== false || $referer == '/' || strpos($referer, '/archivetransfers/docUpload') !== false)
                $lienRetour = '/archivetransfers/editInteractif/' . $id;
            else
                $lienRetour = $this->referer();
        }
        // lecture du transfert en base
        $archiveTransfer = $this->{$this->modelClass}->find('first', array(
            'recursive' => -1,
            'fields' => array('id', 'statut', 'sens', 'fichier_message', 'repertoire_documents'),
            'conditions' => array('id' => $id)));
        if (empty($archiveTransfer)) {
            $this->Session->setFlash(__('Invalide id pour le') . ' ' . __('transfert') . ' : ' . __('traitement impossible.'), 'growl', array('type' => 'important'));
            $sortie = true;
        } elseif (!$this->{$this->modelClass}->isEditable($id)) {
            $this->Session->setFlash(__('Cette fonction ne concerne que les transferts en cours d\'édition') . ' : ' . __('traitement impossible.'), 'growl', array('type' => 'important'));
            $sortie = true;
        }
        if ($sortie)
            $this->redirect(array('action' => 'sortants_index'));
        else {
            $this->pageTitle = Configure::read('appName') . ' : ' . __('Transferts') . ' : ' . __('Versement');
            $this->set('archiveTransferId', $id);
            $this->set('fichiersJointsTypes', array(
                'TARGZ' => __('Un fichier tar.gz regroupant les pièces jointes'),
                'ZIP' => __('Un fichier zip regroupant les pièces jointes'),
                'FICHIERS' => __('Une ou plusieurs pièce(s) jointe(s)')));
            $this->set('lienRetour', $lienRetour);
            // inialisation de la liste des fichiers
            if ($archiveTransfer['Archivetransfer']['sens'] == SORTANT)
                $repDoc = $this->Gestdir->initRepertoire(array(configure::read('Repertoire.sortie'), 'ArchiveTransfer', $id, 'documents'));
            else
                $repDoc = $this->Gestdir->initRepertoire(array(configure::read('Repertoire.entree'), 'ArchiveTransfer', $id, 'documents'));
            $files = $this->Gestdir->listeRepertoire($repDoc, true);
            $this->FichierSeda->load($archiveTransfer['Archivetransfer']['fichier_message']);
            $docs = $this->FichierSeda->listeDocuments();
            foreach ($docs as &$doc) {
                $doc = str_replace(array('\\', '/'), DS, $doc);
                if ($doc{0} == DS) $doc = substr($doc, 1);
            }
            $ret = array();
            foreach ($files as &$file) {
                //Mise en forme JSON pour le plugin
                $file['url'] = "/archivetransfers/afficherPieceJointe/" . $id . "/" . $file['nom'];
                $file['name'] = $file['nom'];
                $file['size'] = $file['bytesize'];
                //Droit de supprimer?
                if (!in_array($file['name'], $docs)) {
                    $file['delete_url'] = Router::url(array('action' => 'docUploadAjax', $id, base64_encode($file['name'])), true);
                    $file['delete_type'] = 'DELETE';
                    $file['delete_ckb'] = '1';
                }
                array_push($ret, $file);
            }
            $this->set('files', array("files" => $ret));
        }
    }

    /**
     * gestion du téléchargement upload des pièces jointes d'un transfert
     * Ecoute les requêtes AJAX de jQuery File Upload
     * @param archiveTransferId
     * @param fichier , nom du fichier encodé pour suppression (optionnel)
     * @return string json si method post ou delete, page web si method get
     */
    function docUploadAjax($id, $fichier = null) {
        App::import('Lib', 'Fido');
        App::import('Lib', 'AppGestfichiers');
        unset($this->request->data[$this->modelClass]['confirmDelete']);
        $ret = null;
        // lecture du transfert en base
        $archiveTransfer = $this->{$this->modelClass}->find('first', array(
            'recursive' => -1,
            'fields' => array('id', 'statut', 'sens', 'fichier_message', 'repertoire_documents'),
            'conditions' => array('id' => $id)));
        switch ($_SERVER['REQUEST_METHOD']) {
            case 'GET': //LOAD
                // lecture du transfert en base
                $archiveTransfer = $this->{$this->modelClass}->find('first', array(
                    'recursive' => -1,
                    'fields' => array('id', 'statut', 'sens', 'fichier_message', 'repertoire_documents'),
                    'conditions' => array('id' => $id)));
                if (!empty($archiveTransfer) && $this->{$this->modelClass}->isEditable($id)) {
                    // inialisation de la liste des fichiers
                    if ($archiveTransfer['Archivetransfer']['sens'] == SORTANT)
                        $repDoc = $this->Gestdir->initRepertoire(array(configure::read('Repertoire.sortie'), 'ArchiveTransfer', $id, 'documents'));
                    else
                        $repDoc = $this->Gestdir->initRepertoire(array(configure::read('Repertoire.entree'), 'ArchiveTransfer', $id, 'documents'));

                    $files = $this->Gestdir->listeRepertoire($repDoc, true);
                    $this->FichierSeda->load($archiveTransfer['Archivetransfer']['fichier_message']);
                    $docs = $this->FichierSeda->listeDocuments();
                    foreach ($docs as &$doc) {
                        $doc = str_replace(array('\\', '/'), DS, $doc);
                        if ($doc{0} == DS) $doc = substr($doc, 1);
                    }
                    $ret = array();
                    foreach ($files as &$file) {
                        $valid = true;
                        //Mise en forme JSON pour le plugin
                        $file['url'] = "/archivetransfers/afficherPieceJointe/" . $id . "/" . $file['nom'];
                        $file['name'] = $file['nom'];
                        unset($file['nom']);
                        $file['size'] = $file['bytesize'];
                        unset($file['bytesize']);
                        $file['path'] = $repDoc . $file['name'];
                        //Droit de supprimer?
                        if (!in_array($file['name'], $docs)) {
                            $file['delete_url'] = Router::url(array('action' => $this->request->action, $id, base64_encode($file['name'])), true);
                            $file['delete_type'] = 'DELETE';
                            $file['delete_ckb'] = '1';
                        }
                        $file['format'] = '';
                        $file['mimecode'] = '';
                        $file['integrity'] = AppGestfichiers::calcHashFile($file['path'], 'sha256');
                        $file['algorithme'] = 'http://www.w3.org/2001/04/xmlenc#sha256';
                        // détermination du format de la pièce jointe
                        if (Configure::read("FormatDetector.type") == "FIDO") {
                            // détection du format
                            $fido = Fido::analyzeFile($file['path']);
                            if ($fido['result'] == 'OK') {
                                //mapping des données récoltées par Fido
                                $file['format'] = $fido['puid'];
                                $file['mimecode'] = $fido['mimetype'];
                            } else
                                $valid = false;
                        }

                        if ($valid)
                            array_push($ret, $file);
                    }
                    $ret = array("files" => $ret);
                }
                break;
            case 'PUT':
            case 'POST': //UPLOAD
                $ret = array('files' => array());
                $fichierJoint = $this->request->data[$this->modelClass]['fichiers'];
                if (isset($fichierJoint['error']) && $fichierJoint['error'] != 0) {
                    $ret['files'][0] = array(
                        'name' => $this->request->data[$this->modelClass]['fichiers']['name'],
                        'error' => $this->request->data[$this->modelClass]['fichiers']['error']
                    );
                    break;
                }
                if (empty($fichierJoint['name'])) {
                    $ret['files'][0] = array(
                        'name' => $this->request->data[$this->modelClass]['fichiers']['name'],
                        'error' => 'Server error : nom de fichier inconnu'
                    );
                    break;
                }
                // intialisation
                if ($archiveTransfer['Archivetransfer']['sens'] == SORTANT)
                    $repDoc = $this->Gestdir->initRepertoire(array(configure::read('Repertoire.sortie'), 'ArchiveTransfer', $id, 'documents'));
                else
                    $repDoc = $this->Gestdir->initRepertoire(array(configure::read('Repertoire.entree'), 'ArchiveTransfer', $id, 'documents'));

                $fichier = $repDoc . $fichierJoint['name'];
                if (file_exists($fichier)) {
                    $ret['files'][0] = array(
                        'name' => $this->request->data[$this->modelClass]['fichiers']['name'],
                        'error' => 'Un fichier de même nom existe déjà !'
                    );
                    break;
                }
                move_uploaded_file($fichierJoint['tmp_name'], $fichier);
                if (empty($this->request->data[$this->modelClass]['fichiersJointsType']))
                    $this->request->data[$this->modelClass]['fichiersJointsType'] = 'FICHIERS';
                App::import('Lib', 'AppGestfichiers');
                // Copie des pièces jointes
                switch ($this->request->data[$this->modelClass]['fichiersJointsType']) {
                    case 'ZIP':
                    case 'TARGZ':
                        try {
                            AppGestfichiers::decompresser($fichier, $repDoc);
                            $ret['files'][0] = array(
                                'name' => $fichierJoint['name'],
                                'type' => $fichierJoint['type'],
                                'size' => $fichierJoint['size']
                            );
                        } catch (Exception $e) {
                            // handle errors
                            $ret['files'][0] = array(
                                'name' => $this->request->data[$this->modelClass]['fichiers']['name'],
                                'error' => $e->getMessage()
                            );
                        }
                        @unlink($fichier);
                        break;
                    case 'FICHIERS' :
                    default:
                        $ret['files'][0] = array(
                            'url' => Router::url(array('action' => 'afficherPieceJointe', $id, $fichierJoint['name']), true),
                            'name' => $fichierJoint['name'],
                            'path' => $fichier,
                            'type' => $fichierJoint['type'],
                            'size' => $fichierJoint['size'],
                            'delete_url' => Router::url(array('action' => $this->request->action, $id, base64_encode($fichierJoint['name'])), true),
                            'delete_type' => 'DELETE',
                            'delete_ckb' => '1',
                            'format' => '',
                            'mimecode' => ''
                        );
                        $ret['files'][0]['integrity'] = AppGestfichiers::calcHashFile($fichier, 'sha256');
                        $ret['files'][0]['algorithme'] = 'http://www.w3.org/2001/04/xmlenc#sha256';
                        // détermination du format de la pièce jointe
                        if (Configure::read("FormatDetector.type") == "FIDO") {
                            // détection du format
                            $fido = Fido::analyzeFile($fichier);
                            if ($fido['result'] == 'OK') {
                                //mapping des données récoltées par Fido
                                $ret['files'][0]['format'] = $fido['puid'];
                                $ret['files'][0]['mimecode'] = $fido['mimetype'];
                            } else
                                unset($ret['files'][0]);
                        }
                        if (empty($archiveTransfer['Archivetransfer']['repertoire_documents']))
                            $this->{$this->modelClass}->save(array('id' => $archiveTransfer['Archivetransfer']['id'], 'repertoire_documents' => $repDoc), false);
                }
                break;
            case 'DELETE': //DELETE
                // suppression d'une pièce jointe
                $relativeFileName = base64_decode($fichier);
                $ret = array(
                    "files" => array(
                        $relativeFileName => false
                    )
                );
                $fileUri = $archiveTransfer['Archivetransfer']['repertoire_documents'] . $relativeFileName;
                if (file_exists($fileUri)) {
                    unlink($fileUri);
                    $ret["files"][$relativeFileName] = true;
                }
                break;
            default:
                header('HTTP/1.0 405 Method Not Allowed');
        }
        header('Vary: Accept');

        if (isset($_SERVER['HTTP_ACCEPT']) && (strpos($_SERVER['HTTP_ACCEPT'], 'application/json') !== false)) {
            header('Content-type: application/json');
        } else {
            header('Content-type: text/plain');
        }
        $json = json_encode($ret);
        echo $json;
        exit;
    }

    /**
     * Récupérer liste complete de codes pour éditeur de transfert par ajax
     * @param $version
     * @param $code
     */
    function getCompleteSedaListAjax($version, $code) {
        $path = TMP . 'files' . DS . 'sedacodes' . DS . $version;
        $filepath = $path . DS . $code;

        if (file_exists($filepath)) {
            $json = file_get_contents($filepath);
            if (isset($_SERVER['HTTP_ACCEPT']) && (strpos($_SERVER['HTTP_ACCEPT'], 'application/json') !== false)) {
                header('Content-type: application/json');
            } else {
                header('Content-type: text/plain');
            }
            echo $json;
        }
        exit;
    }
}
