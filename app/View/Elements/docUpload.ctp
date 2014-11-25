<?php
/**
 * CSS Fileupload
 */

echo $this->Html->css('jquery.fileupload-ui');
echo $this->Html->css('bootstrap.min');
echo $this->Html->css('fix-bootstrap');

/**
 * Scripts Fileupload
 */
echo $this->Html->script('/js/jquery-fileupload/jquery.ui.widget.js', true);
echo $this->Html->script('/js/jquery-fileupload/tmpl.min.js', true);
echo $this->Html->script('/js/bootstrap.min.js', true);
echo $this->Html->script('/js/jquery-fileupload/jquery.iframe-transport.js', true);
echo $this->Html->script('/js/jquery-fileupload/jquery.fileupload.js', true);
echo $this->Html->script('/js/jquery-fileupload/jquery.fileupload-process.js', true);
echo $this->Html->script('/js/jquery-fileupload/jquery.fileupload-validate.js', true);
echo $this->Html->script('/js/jquery-fileupload/jquery.fileupload-ui.js', true);
echo $this->Html->script('attendable', true);
?>
    <script type="text/javascript">
        function onChangeFichiersJointsType(fichierJointsType) {
            $('#boutonValider').show();
            $('#divFichiers').show();

            if (fichierJointsType === 'TARGZ') {
                $('#ArchivetransferFichiers').removeAttr('multiple');
                $('#joindre_txt').text('Joindre le fichier tar.gz');
                //Accepte uniquement les fichiers *.tar.gz et *.tgz
                $('#docUploadForm').fileupload('option', 'acceptFileTypes', /(\.|\/)(tar.gz|tgz)$/i);
                $('#ArchivetransferFichiers').attr('accept', 'application/x-gzip');
            } else if (fichierJointsType === 'ZIP') {
                $('#ArchivetransferFichiers').removeAttr('multiple');
                $('#joindre_txt').text('Joindre le fichier zip');
                //Accepte uniquement les fichiers *.zip
                $('#docUploadForm').fileupload('option', 'acceptFileTypes', /(\.|\/)(zip)$/i);
                $('#ArchivetransferFichiers').attr('accept', 'application/zip');
            } else if (fichierJointsType === 'FICHIERS') {
                $('#ArchivetransferFichiers').attr('multiple', 'multiple');
                $('#joindre_txt').text('Joindre les fichiers');
                //Accepte tout type de fichier
                $('#docUploadForm').fileupload('option', 'acceptFileTypes', /(.*)$/i);
                $('#ArchivetransferFichiers').removeAttr('accept');
            }
            // Suppression du premier element vide
            if (fichierJointsType != '' && $("#ArchivetransferFichiersJointsType option:first").val() == '') {
                $("#ArchivetransferFichiersJointsType option:first").remove();
                $('#docUploadForm').fileupload('option', 'dropZone', $('#uploads'));
            }
        }

        function setAffichage() {
            var valSelect = $('#ArchivetransferFichiersJointsType').val();
            if (valSelect != '') onChangeFichiersJointsType(valSelect);
        }

        /**
         * ecouteurs d'évènements su les cases a cocher
         */
        $(document).on('change', '#docUploadForm .files .deleteckb', ckbChange);
        $(document).on('change', '#masterCheckbox', function () {
            $('#docUploadForm .files .deleteckb').filter('[data-used="0"]').prop("checked", $(this).prop('checked'));
            ckbChange();
        });

        /**
         * trigger change sur checkboxes
         */
        function ckbChange() {
            $('#masterCheckbox').prop("checked", $('#docUploadForm tbody.files .deleteckb:not(:checked)').filter('[data-used="0"]').length === 0);
            //Rendre visible le bouton supprimer si au moins une case est cochée
            if ($('#docUploadForm tbody.files .deleteckb:checked').filter('[data-used="0"]').length > 0)
                $('.fileupload-buttonbar.delete-bar').css('visibility', 'visible');
            else
                $('.fileupload-buttonbar.delete-bar').css('visibility', 'hidden');
        }

        /**
         * @param {boolean} actif
         * Rend disponible ou non les champs du formulaire d'upload
         */
        function activationForm(actif) {
            if (actif === false) {
                $('#ArchivetransferFichiersJointsType').prop("disabled", true);
                $('#ArchivetransferFichiers')
                    .prop("disabled", true)
                    .addClass("disabled");
                $('#divFichiers').addClass("disabled");
                $('#fileupload').fileupload('disable');
            } else {
                $('#ArchivetransferFichiersJointsType').prop("disabled", false);
                $('#ArchivetransferFichiers')
                    .prop("disabled", false)
                    .removeClass("disabled");
                $('#divFichiers').removeClass("disabled");
                $('#fileupload').fileupload('enable');
            }
        }

        var suppressionParLot;

        function initDocUpload() {
            suppressionParLot = false;
            //Suppression par lot
            $('#deleteSelected').on('click', function (e) {
                if (!$('#deleteSelected').hasClass('disabled') && $('#deleteSelected').css('visibility') != 'hidden') {
                    if (confirm('Etes vous sur de vouloir supprimer ces fichiers ?\n\nAttention : Cette opération est irréversible.')) {
                        suppressionParLot = true;
                        $(this).addClass('disabled');
                        $(this).attendable({
                            message: "Veuillez patienter pendant la suppression des fichiers",
                            loaderImgSrc: '<?php echo $this->Html->webroot('img/ajax-loader.gif'); ?>'
                        });
                        $(this).attendableAffiche();
                    } else {
                        $('#uploads input[type="checkbox"]').removeAttr('checked');
                        $('.fileupload-buttonbar.delete-bar').css('visibility', 'hidden');
                        e.preventDefault();
                        return false;
                    }
                }
            });

            //Initialisation plugin jQuery file upload
            $('#docUploadForm').fileupload({
                url: "<?php echo $this->Html->url(array('action' => 'docUploadAjax', $archiveTransferId)); ?>",
                previewAsCanvas: false,
                autoUpload: true,
                fileInput: $('#docUploadForm').find('input:file'),
                dataType: 'json',
                destroy: function (e, data) {
                    if (suppressionParLot
                        || confirm('Confirmer la suppression de cette pièce jointe ?\n\nAttention : Cette opération est irréversible.')) {
                        $.blueimp.fileupload.prototype.options.destroy.call(this, e, data);
                    }
                },
                start: function () {
                    activationForm(false);
                },
                stop: function () {
                    activationForm(true);
                },
                fail: function () {
                    $.jGrowl("<div>Votre session a expiré. Veuillez vous reconnecter.<br><em>Astuce: Pour ne pas perdre votre travail en cours, reconnectez vous depuis un autre onglet.</em></div>", {
                        header: "Erreur de connexion",
                        life: 30000
                    });
                },
                complete: function () {
                    //Rendre à nouveau disponible le formulaire d'upload
                    activationForm(true);
                }
            });

            // Load existing files:
            $.ajax({
                url: $('#docUploadForm').fileupload('option', 'url'),
                dataType: 'json',
                context: $('#docUploadForm')[0]
            }).done(function (result) {
                $("#docUploadForm").find(".files").empty();
                $(this).fileupload('option', 'done').call(this, null, {result: result});
                $("div.jGrowl").jGrowl("close");
            });

            //Bug fix double clic sous MSIE
            if (navigator.userAgent.indexOf("MSIE") > 0)
                $("#ArchivetransferFichiers").mousedown(function () {
                    $(this).trigger('click');
                });

            //Gestion des évènements du plugin
            $('#docUploadForm').bind('fileuploaddone', function () { //upload terminé
                var filetype = $('select#ArchivetransferFichiersJointsType').val();
                if (filetype == 'TARGZ' || filetype == 'ZIP') {
                    $.jGrowl("<div>Veuillez patienter quelques instants. La liste des fichiers va être mise à jour.</div>", {header: "Décompression de l'archive en cours"});
                    $('#docUploadForm').fileupload('destroy');
                    initDocUpload();
                }
            });

            $('#docUploadForm').bind('fileuploaddestroyed', function () {
                if ($('td input[type="checkbox"]:visible:checked').length == 0) {
                    afterDelete();
                }
            });
            $("#masterCheckbox").prop('checked', false);
        }

        function afterDelete() {
            suppressionParLot = false;
            $('.fileupload-buttonbar.delete-bar').css('visibility', 'hidden');
            $('#deleteSelected').removeClass('disabled');
            $('#masterCheckbox').removeAttr('checked');
            $('#deleteSelected').attendableRemove();
        }

        function checkUsedFiles(){
            $('table .files td.name a').each(function () {
                var file = $(this).text();
                var selected = $('.DocumentAttachment, .RelatedDataTypeData').not(':disabled').filter(function () {
                    return $(this).val() == file;
                });
                if (selected.length) {
                    $('button.delete[data-name="' + file + '"]').hide();
                    $('input.deleteckb[data-name="' + file + '"]').attr('data-used', '1').hide();
                } else {
                    $('button.delete[data-name="' + file + '"]').show();
                    $('input.deleteckb[data-name="' + file + '"]').attr('data-used', '0').show();
                }
            });
        }

        $(function () {
            initDocUpload();
            setAffichage();
            //allow specific drop zones but disable the default browser action for file drops on the document
            $(document).bind('drop dragover', function (e) {
                e.preventDefault();
            });
        });
    </script>
<?php

echo $this->Html->tag('h2', __('Pièces jointes à télécharger'));
echo $this->Form->create(null, array('url' => array('action' => 'docUpload', $archiveTransferId), 'type' => 'file', 'id' => 'docUploadForm'));

echo $this->Form->input('fichiersJointsType', array(
    'label' => false,
    'empty' => __('(sélectionner le type des fichiers joints)'),
    'div' => array(
        'class' => 'input select required',
        'style' => 'float:left; margin-right: 10px;'
    ),
    'onChange' => "onChangeFichiersJointsType(this.value);"));
echo $this->Html->tag('span', null, array('id' => 'divFichiers', 'style' => 'display: none;', 'class' => 'btn btn-success fileinput-button input text required'));
echo $this->Html->tag('span', '<i class="icon-upload-alt"></i> <span id="joindre_txt">Joindre des fichiers</span>');
echo $this->Form->input('Archivetransfer.fichiers', array(
    'label' => false,
    'type' => 'file',
    'multiple' => true,
    'size' => '100'));
echo $this->Html->tag('/span');
?>
    <!-- The global progress information -->
    <div class="span4 fileupload-progress fade">
        <!-- The global progress bar -->
        <div class="progress progress-success progress-striped active" role="progressbar" aria-valuemin="0"
             aria-valuemax="100">
            <div class="bar" style="width:0;"></div>
        </div>
        <!-- The extended global progress information -->
        <div class="progress-extended">&nbsp;</div>
    </div>
    <!-- The loading indicator is shown during file processing -->
    <div class="fileupload-loading"></div>

<?php
echo $this->Html->tag('div', '', array('class' => 'clearfix'));
echo $this->Html->tag('h2', __('Pièces jointes téléchargées'));
?>
    <table role="presentation" class="table table-striped" id="uploads">
        <thead>
        <tr>
            <th style="height: 30px"><input type="checkbox" id="masterCheckbox"></th>
            <th class="name">Nom</th>
            <th class="size" style="min-width: 80px">Taille</th>
            <th colspan="3" class="deleteAll" id="deleteAllCell" style="padding: 8px;">
				<span class='fileupload-buttonbar delete-bar'>
					<button class='delete btn btn-danger deleteAll' id='deleteSelected'
                            title="Supprimer tous les fichiers sélectionnés"
                            style="margin-bottom: 0; font-weight: bold;">
                        <i class='icon-trash'></i> <span>Supprimer la sélection</span>
                    </button>
				</span>
            </th>
        </tr>
        </thead>
        <tbody class="files" data-toggle="modal-gallery" data-target="#modal-gallery"></tbody>
    </table>
<?php
echo $this->element('fileupload-template');
echo $this->Form->end();
?>