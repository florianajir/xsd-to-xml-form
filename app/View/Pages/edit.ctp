<?php
//pour le datetime-picker
echo $this->Html->css('bootstrap-datetimepicker.min.css');
echo $this->Html->script('bootstrap-datetimepicker.min.js');
echo $this->Html->script('bootstrap-datetimepicker.fr.js');
//pour l'arbre
echo $this->Html->css('jstree/style.min.css');
echo $this->Html->script('jstree/jstree.min.js');
//pour le resizable
echo $this->Html->css('jquery-ui.min.css');
echo $this->Html->script('jquery-ui.min.js');
//scripts persos
echo $this->Html->script('onglets.js');
$this->Seda->initForm();
echo $this->Html->script('editor.js');

echo $this->Html->tag('h1', $titre);

echo <<<EOF
<div id="PJmodal" class="modal hide fade" style="margin-left: -400px; width: 800px;">
    <div class="modal-header">
        <button type="button" class="close" data-dismiss="modal" aria-hidden="true">&times;</button>
        <h3>Gestion des pièces jointes</h3>
    </div>
    <div class="modal-body">
        {$this->element('docUpload')}
    </div>
    <div class="modal-footer">
        <a href="#" data-dismiss="modal" class="btn btn-primary">Terminer</a>
    </div>
</div>
EOF;

//Formulaire selection un fichier
echo $this->Form->create(null, array('url' => array('action' => 'docUploadAjax'), 'type' => 'file', 'id' => 'singleDocUploadForm'));
echo $this->Form->hidden('typeFichier', array('value' => 'FICHIERS'));
echo $this->Html->tag('span', null, array('id' => 'singleFileUploadContainer', 'style' => 'display: none;', 'class' => 'btn btn-success fileinput-button input text required'));
echo $this->Html->tag('span', '<i class="icon-upload-alt"></i> Nouveau fichier...');
echo $this->Form->input('Archivetransfer.fichiers', array(
    'id' => 'singleDocUploadBtn',
    'label' => false,
    'type' => 'file',
    'multiple' => false
));
echo $this->Html->tag('/span');
echo $this->Form->end();
$params = Router::getParams();
echo $this->Form->create('Archivetransfer', array(
    'action' => 'saveSedaForm',
    'data-url' => Router::url(array('controller' => $params['controller'], 'action' => '/')),
    'data-keywords-url' => Router::url(array('controller' => 'keywords', 'action' => 'getKeywordList'))
));
?>
    <!--Container principal-->
    <div id="bdxContainer">
        <!-- Panneau gauche (navigation) -->
        <div id="bdxNavigation" style="height: 100%;">
            <input id='searchnode' class='search-tree' placeholder='Recherche'/>

            <div id="browser" class='filetree treeview' data-role='listview'></div>
        </div>
        <!-- Panneau droite (editeur) -->
        <div id="bdxAffichage" style="background-color: #eee">
            <div id="bdxContent" class="sedaform"></div>
        </div>
    </div>
    <div class="clearfix"></div>
<?php
//Templates
echo $this->Html->tag('div', null, array('id' => 'templates', 'style' => 'display:none;'));
echo $this->Html->tag('div', $this->Seda->form($sedatree, $this->request->data, 'seda'), array('id' => 'sedaform-template'));
echo $this->Html->tag('div', null, array('id' => 'selectRepository', 'class' => 'sedafield-container', 'style' => 'display:none;'));
echo $this->Form->input('repository_id', array(
    'options' => $services,
    'label' => "Repository",
    'data-name' => "Repository",
    'empty' => true,
    'div' => array('class' => 'input select required sedafield')
));
echo $this->Html->tag('/div');

echo $this->Html->tag('div', null, array('id' => 'selectArchivalAgency', 'class' => 'sedafield-container', 'style' => 'display:none;'));
echo $this->Form->input('archival_agency_id', array(
    'options' => $servicesArchive,
    'label' => "Service d'archives",
    'data-name' => "ArchivalAgency",
    'disabled' => true,
    'empty' => true,
    'div' => array('class' => 'input select required sedafield')
));
echo $this->Html->tag('/div');

echo $this->Html->tag('div', null, array('id' => 'selectTransferringAgency', 'class' => 'sedafield-container', 'style' => 'display:none;'));
echo $this->Form->input('transferring_agency_id', array(
    'options' => $servicesVersant,
    'label' => "Service versant",
    'data-name' => "TransferringAgency",
    'disabled' => true,
    'empty' => true,
    'div' => array('class' => 'input select required sedafield')
));
echo $this->Html->tag('/div');

echo $this->Html->tag('div', null, array('id' => 'selectOriginatingAgency', 'class' => 'sedafield-container', 'style' => 'display:none;'));
echo $this->Form->input('originating_agency_id', array(
    'options' => $servicesProducteur,
    'label' => "Service producteur",
    'data-name' => "OriginatingAgency",
    'disabled' => true,
    'empty' => true,
    'div' => array('class' => 'input select required sedafield')
));
echo $this->Html->tag('/div');

echo $this->Html->tag('div', null, array('id' => 'selectKeywordList', 'class' => 'sedafield-container', 'style' => 'display:none;'));
echo $this->Form->input('keyword_list', array(
    'options' => $keywordLists,
    'label' => "Choisissez la liste de mots clés à utiliser",
    'data-name' => "Liste de mots-clés",
    'disabled' => true,
    'empty' => true,
    'div' => array('class' => 'input select required sedafield')
));
echo $this->Html->tag('/div');

echo $this->Html->tag('div', null, array('id' => 'selectArchivalAgreement', 'class' => 'sedafield-container', 'style' => 'display:none;'));
echo $this->Form->input('archival_agreement_id', array(
    'options' => $accords,
    'data-name' => "ArchivalAgreement",
    'label' => false,
    'disabled' => true,
    'empty' => true,
    'div' => false
));
echo $this->Html->tag('/div');

echo $this->Html->tag('div', null, array('id' => 'selectArchivalProfile', 'class' => 'sedafield-container', 'style' => 'display:none;'));
echo $this->Form->input('archival_profile_id', array(
    'options' => $profils,
    'data-name' => "ArchivalProfile",
    'label' => false,
    'disabled' => true,
    'empty' => true,
    'div' => false
));
echo $this->Html->tag('/div');

echo $this->Html->tag('div', null, array('id' => 'selectDuration', 'class' => 'sedafield-container', 'style' => 'display:none;'));
echo $this->Form->input('duration', array('options' => $durations, 'label' => false, 'disabled' => true, 'empty' => true, 'div' => false));
echo $this->Html->tag('/div');

// id du transfert
echo $this->Form->hidden('archiveTransferId', array('value' => $archiveTransferId));
echo $this->Form->hidden('version_seda', array('value' => $version_seda));
echo $this->Html->tag('/div');
echo $this->Html->tag('/div');

//Panneau du bas (boutons)
echo $this->Html->tag('div', null, array('class' => 'submit'));
echo $this->Html->link('<i class="icon-undo"></i> '.__('Retour à la liste des transferts'), 'javascript:void(0)', array(
    'escape' => false,
    'onclick' => "if (confirm('Si vous quittez cette page vous perdrez les données saisies.')) location.href='{$this->Html->url(array('action' => $actionRetour))}'",
    'class' => 'btn btn-danger'
));
echo $this->Html->tag('div', null, array('style' => 'float:right'));
if (!empty($archiveTransferId))
    echo $this->Html->tag('a', '<i class="icon-folder-open"></i> Gestion des pièces jointes', array(
        'href' => '#PJmodal',
        'id' => 'PjModalBtn',
        'role' => 'button',
        'class' => 'btn btn-info',
        'data-toggle' => "modal",
        'style' => 'margin-right: 10px'
    ));
    echo '<button type="submit" id="saveSedaFormBtn" class="btn btn-success"><i class="icon-save"></i> Sauvegarder</button>';
echo $this->Html->tag('/div');
echo $this->Html->tag('/div');

echo $this->Form->end();
//Action : création
if (empty($archiveTransferId)) {
    echo <<<EOF
    <script type="text/javascript">
    $(document).ready(function () {
        var id = $('#browser a[data-title="ArchiveTransfer"]').attr('data-id');
        $("#browser").jstree(true).open_node(id);
        editSedaObject(id);
        $('#cancelEditSedaObject').prop('disabled',true);
        $('#saveSedaObject').attendable({
            message: 'Veuillez patienter, chargement en cours.',
            loaderImgSrc: '{$this->Html->webroot('img/ajax-loader.gif')}'
		});
    });
    </script>
EOF;
}