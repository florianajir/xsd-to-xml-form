/**
 * Créé par Florian Ajir <florian.ajir@adullact.org> le 05/06/14.
 */

var seed;
var max_lenght = 60;

$(document).ready(function () {
    seed = 0;
    $('body').on('click', 'a.disabled', function (event) {
        event.preventDefault();
        return false;
    });
    $('select').select2('destroy');
    //Navigation en arbre
    sedaFormToTree();
    searchListener();

    $('table#upload td.name')
        .css('width', '400px')
        .css('max-width', '400px');
    $('table#upload td.name a').css('word-wrap', 'break-word');

    //Evenement clic bouton Gestion des pièces jointes
    $('a#PjModalBtn').on('click', checkUsedFiles);

    $('form').on('submit', function (event) {
        if ($('#ArchivetransferArchiveTransferId').val() == 0) {
            $('#browser').jstree(true).clear_state();
            return true;
        }

        //Champ requis non rempli ?
        var required = $('.sedafieldset:not(.sedatemplate) .sedafield.required')
            .find('select, input[type="text"], input[type="number"], input[type="hidden"], textarea')
            .filter(':enabled');

        required.each(function () {
            if (!$(this).closest('.sedatemplate').length && $(this).val() == ''
                && $(this).closest('.sedafieldset').attr('data-id')) {
                var num = $(this).closest('.sedafieldset').attr('data-id');
                $('#browser').jstree(true).deselect_all();
                $('#browser').jstree(true).select_node(num);
                editSedaObject(num, false);
                $.jGrowl("<div>Champ requis : " + $(this).attr('data-name') + "</div>", {header: 'Erreur : Champ obligatoire'});
                event.preventDefault();
                return false;
            }
        });

        $('.sedafieldset[data-title="Keyword"]:not(.sedatemplate) [data-sedaname="KeywordType"]').each(function () {
            if (!$(this).find('[data-name="KeywordType"]').val())
                $(this).find('[data-name="listVersionID"]').attr('disabled', 'disabled');
        });
        return true;
    });

    //resizable
    $("#bdxNavigation")
        .resizable({handles: "e"})
        .bind("resize", function () {
            calculateSize();
        });
});

function calculateSize() {
    var left = $("#bdxNavigation"),
        right = $("#bdxAffichage"),
        container = $("#bdxContainer"),
        browser = $("#browser"),
        diff = right.outerWidth(true) - right.width() + 20,
        rightWidth = container.width() - left.outerWidth(true);
    right.width(rightWidth - diff);
    browser.width(left.width() - left.css('padding-right'));
}

$(window).resize(calculateSize());

/**
 * Construction de l'arbre de navifation à partir du formulaire seda caché
 * Construit la liste initilise le plugin jstree
 */
function sedaFormToTree() {
    //préparer la liste conteneur
    var browser = $('#browser'),
        atr_id = $("#ArchivetransferArchiveTransferId").val();

    browser
        .html('<ul>' + recursiveSedaFormToList($('div.sedaform > fieldset.sedafieldset')) + '</ul>')
        .jstree({
            "core": {
                "multiple": false,
                "check_callback": true
            },
            "types": {
                "folder": {
                    "icon": "icon-folder-open"
                },
                "editobject": {
                    "icon": "icon-pencil"
                },
                "draft": {
                    "icon": "icon-plus-sign-alt"
                },
                "incomplete": {
                    "icon": "icon-exclamation-sign"
                },
                "complete": {
                    "icon": "icon-ok"
                },
                "facultatif": {
                    //"icon": "icon-folder-open-alt"
                    "icon": "icon-check-empty"
                },
                "facultatifcomplete": {
                    "icon": "icon-check"
                },
            },
            "contextmenu": {
                "items": clicDroitContextMenu
            },
            "search": {
                "fuzzy": false,
                "show_only_matches": true
            },
            "state": {
                "key": atr_id > 0
                    ? "edit" + atr_id
                    : "add" + Math.random().toString(36).substring(5)
            },
            "plugins": [
                "types",
                "contextmenu",
                "search",
                "state"
            ]
        });
    browser.find('.jstree-icon').first().remove();
    browser.find('.jstree-node').first().find('>ul.jstree-children').attr('style', 'position: relative; right: 24px;');
    return browser;
}

/**
 * Fonction de recherche dans l'arbre
 */
function searchListener() {
    var to = false;
    $('#searchnode').keyup(function () {
        if (to)
            clearTimeout(to);

        to = setTimeout(function () {
            var v = $('#searchnode').val();
            $('#browser').jstree(true).search(v);
        }, 250);
    });
}

/**
 * Termine l'édition d'un objet seda
 * Vide le panneau principal et réactive la navigation
 */
function stopEdit() {
    $('#saveSedaFormBtn').removeAttr('disabled');
    $('#PjModalBtn').removeClass('disabled');
    $.jGrowl("close");
    $('#bdxContent').slideUp('400', function () {
        $(this).empty();
    }).find('.datepicker .datetimepicker').datetimepicker('destroy');
    stopOverlay();
    $('#bdxAffichage').css('background-color', '#eee');
}

/**
 * Menu clic droit seda tree
 * @param node
 */
function clicDroitContextMenu(node) {

    var tree = $('#browser').jstree(true),
        link = $('#' + node.id).find('> a').first(),
        onclick = link.attr('onclick'),
        max = link.attr('data-max'),
        min = link.attr('data-min'),
        selected_children = $("#" + tree.get_selected()).find('>ul>li').length,
        type = tree.get_type(node.id),
        items;

    switch (type) {
        case 'facultatif':
        case 'folder':
            items = {
                "add": {
                    "label": "Nouveau",
                    "action": function () {
                        newSedaObject(node.id);
                    },
                    "icon": "icon-plus"
                }
            };
            if (selected_children >= max)
                delete items.add;
            break;

        default:
            items = {
                "edit": {
                    "label": "Modifier",
                    "action": function () {
                        eval(onclick);
                    },
                    "icon": "icon-edit"
                },
                "delete": {
                    "label": "Supprimer",
                    "icon": "icon-trash",
                    "action": function () {
                        removeSedaObject(node.id);
                    }
                }
            };

            if (tree.get_parent(node.id) != '#')
                var parent_children = $("#" + tree.get_parent(node.id)).find('>ul>li').length;

            if (min == max == parent_children == undefined || min == parent_children || min == max == '1')
                delete items.delete;
    }
    return items;
}

/**
 * Action ajouter (clic droit arbre)
 * @param nodeid
 * @returns {boolean}
 */
function newSedaObject(nodeid) {
    //Action cliquer pour ajouter un objet
    var inner = $('fieldset.innerFieldset[data-templateid="' + nodeid + '"]');

    //si max atteint, quitter
    if ($(inner).find('>.sedaobjects>.sedafieldset').not('.sedatemplate').length >= $(inner).attr('data-max'))
        return false;

    $.jGrowl("<div>" + $(inner).attr('data-title') + "</div>", {header: 'Mode : Création objet SEDA', sticky: true});

    var addBtn = $('.addSedaObject[data-templateid="' + nodeid + '"]');
    console.log(addBtn);
    if (!$(inner).hasClass('sedaclone'))
        addSedaObject(addBtn, $(inner).attr('data-max'), true, true);
    else
        cloneSedaParentObject(addBtn);

    newSedaTreeObject($('#' + nodeid));
    return true;
}

/**
 * Créer un noeud dans l'arbre
 * @param tree jstree instance
 * @param parent node
 * @param name nom du nouveau noeud
 * @param type type du nouveau noeud
 * @param id id du nouveau noeud
 * @returns {*|String}
 */
function createNode(tree, parent, name, type, id) {
    return tree.create_node(parent, {
        "text": name,
        "type": type,
        "id": id
    });
}

function removeSedaObject(num) {
    if (confirm('Etes-vous sûr de vouloir supprimer cet objet ?')) {
        var title = $('#' + num + '> a').attr('data-title');
        deleteSedaObject($('.sedaform .sedafieldset[data-id="' + num + '"]').find('>.deleteSedaObject'), true);
        reloadTree();
        $.jGrowl("<div>L'objet SEDA \"" + title + "\" a été supprimé !</div>", {header: 'Objet SEDA supprimé'});
    }
}

/**
 * Ajouter un noeud objet à l'arbre
 * @param parent
 */
function newSedaTreeObject(parent) {
    var id = uniqId(),
        $a = parent.children('a').first(),
        tree = $('#browser').jstree(true),
        addButton = $('.addSedaObject[data-templateid="' + $a.attr('data-id') + '"]'),
        sedaobjects = $('.innerFieldset[data-templateid="' + $a.attr('data-id') + '"] > .sedaobjects'),
        newfieldset = sedaobjects.find('> .sedafieldset:not(".sedatemplate"):not([data-id])'),
        dataTitle = addButton.closest('.innerFieldset').attr('data-title'),
        libelle = dataTitle,
        definition = newfieldset.attr('data-definition');

    newfieldset.attr('data-id', id);
    newfieldset.find('select').select2('destroy');

    if (definition && definition.split(' ').length <= 3)
        libelle = definition;

    //Créer le noeud dans jstree
    var newNodeId = createNode(tree, parent, libelle, 'draft', id);
    //Ouvrir le noeud selectionné
    tree.select_node(newNodeId);
    //Attributs et evenement sur le nouveau noeud

    $("#" + newNodeId).addClass('draft');
    var newLink = $('li#' + newNodeId + ' > a');
    newLink
        .attr('data-id', id)
        .attr('title', libelle != definition ? definition : dataTitle)
        .attr('data-title', dataTitle)
        .attr('data-name', newfieldset.attr('data-name'))
        .click(function () {
            editSedaObject(id, false);
        });

    if ($('.innerFieldset[data-templateid="' + $a.attr('data-id') + '"]').attr('data-max') != 1) {
        //Renommer le noeud parent (update nombre enfants)
        libelle = $a.text().substr(0, $a.text().indexOf('('));
        var count = sedaobjects.find('> .sedafieldset:not(".sedatemplate")').length;
        libelle += '(' + count + ')';
        tree.rename_node(parent, libelle);
    }

    //Editer les informations du nouveau noeud
    editSedaObject(id, true);
    $("html, body").animate({scrollTop: $('#bdxAffichage').offset().top});
}

/**
 * Affiche dans le panneau principal les champs de description d'un objet
 * @param num
 * @param creation si true ne pas afficher growl mode edition
 * @returns {boolean}
 */
function editSedaObject(num, creation) {
    if (!num) return false;
    var tree = $('#browser').jstree(true);
    tree.deselect_all();
    tree.select_node(num);
    $('#saveSedaFormBtn').prop('disabled', true);
    $('#PjModalBtn').addClass('disabled');
    overlayNav();
    var $content = $('#bdxContent'),
        $a = $('a[data-id="' + num + '"]'),
        $fieldset = $('.sedaform').find('.sedafieldset[data-id="' + num + '"]'),
        $source = $fieldset.find('> .seda-container > .sedafield-container'),
        $clone = $source.clone(),
        type = tree.get_type(num),
        waitForShow = false;

    //Changement icone
    if (type != 'draft')
        tree.set_type(num, 'editobject');
    $content.hide();
    $content.html('');

    if ($a.attr('data-title')) {
        //Edition d'un objet seda Organization/Agency : Affichage liste selection Agence
        if ($a.attr('data-title').indexOf("Agency") >= 0 || $a.attr('data-title') == "Repository") {
            var selected = $fieldset.find('.model_id').val();
            $clone = cloneAgency($a.attr('data-title'), $a.attr('data-name') + '[id]', selected);
        }
        //Edition ArchiveTransfer : Ajout option "Identifiant calculé au verrouillage"
        if ($a.attr('data-title') == "ArchiveTransfer" && !$clone.find('#ArchiveTransferTransferIdentifierAuto').length) {
            var calc_auto_ckb = $('<input/>').attr('id', 'ArchiveTransferTransferIdentifierAuto')
                .attr('type', 'checkbox')
                .attr('onchange', 'calcTransferIdentifierAuto(this)')
                .css('margin-right', '5px');

            var label_ckb = $('<label/>').attr('for', 'ArchiveTransferTransferIdentifierAuto')
                .text('Calculé lors du verrouillage')
                .css('display', 'inline')
                .css('font-weight', 'normal')
                .css('padding-right', '0');

            var $transferIdentifier = $clone.find('[data-name="TransferIdentifier"]');
            $transferIdentifier.before(calc_auto_ckb).before(label_ckb).before('<br/>');
            if ($transferIdentifier.val() == "#A_CALCULER_LORS_DU_VERROUILLAGE#"
                || $transferIdentifier.val() == '') {
                $clone.find('#ArchiveTransferTransferIdentifierAuto').prop('checked', true).change();
            }
        }
        //Edition de l'objet seda Document/RelatedData : Formulaire upload file ajax et mise à jour attributs Attachment/Data et size
        if ($a.attr('data-title') == "Document"
            || $a.attr('data-title').indexOf("RelatedData") >= 0
        ) {
            if (!$clone.find('.sedafield').length) {
                cancelEditSedaObject(num);
                $.jGrowl("close");
                $.jGrowl("<div class='erreur'>Impossible de créer un document avant l'enregistrement du transfert d'archive.</div>", {header: 'Erreur :'});
                return false;
            } else {
                var target = 'input.DocumentAttachment';
                if ($a.attr('data-title').indexOf("RelatedData") >= 0)
                    target = 'input.RelatedDataTypeData';
                var $attchmtInput = $clone.find(target).get(0),
                    newselect = $('<select/>').css('width', '90%');
                if ($attchmtInput) {
                    $.jGrowl("<div>Veuillez patienter quelques instant...</div>", {header: 'Chargement des fichiers'});
                    var filepath = $($attchmtInput).val();
                    $clone.find(target).prop('readonly', true);
                    waitForShow = true;
                    $(newselect)
                        .append($('<option/>').attr('value', ''));
                    $.ajax({
                        url: $('#docUploadForm').fileupload('option', 'url'),
                        context: $('#docUploadForm')[0],
                        type: 'GET',
                        dataType: 'json',
                        error: function () {
                            $.jGrowl("<div class='erreur'>Erreur : Votre session a expiré. Veuillez vous reconnecter.<br><em>Astuce: Pour ne pas perdre votre travail en cours, reconnectez vous depuis un autre onglet.</em></div>", {
                                header: "Problème de connexion",
                                life: 30000
                            });
                            cancelEditSedaObject($('#browser').jstree(true).get_selected());
                        }
                    }).done(function (result) {
                        $clone.find(target).prop('readonly', false);
                        //Collection des autres champs select de fichier (Document & RelatedData)
                        var selects = $('.DocumentAttachment, .RelatedDataTypeData').not(':disabled').filter(function () {
                            return ($(this).val() != '' && $(this).attr('name') != $($attchmtInput).attr('name'));
                        });
                        //Tableau de valeurs (documents selectionnés)
                        var docs_selected = [];
                        selects.each(function () {
                            docs_selected.push($(this).val());
                        });
                        //Ajout des fichiers au select
                        $.each(result.files, function (i, file) {
                            if ($.inArray(file.name, docs_selected) == -1) {
                                $(newselect).append(
                                    $('<option/>').text(file.name)
                                        .attr('value', file.name)
                                        .attr('data-title', file.name)
                                        .attr('data-format', file.format)
                                        .attr('data-mimecode', file.mimecode)
                                        .attr('data-size', file.size)
                                        .attr('data-integrity', file.integrity)
                                        .attr('data-algorithme', file.algorithme)
                                );
                                $('button.delete[data-uri="' + file.path + '"]').show();
                                $('input.deleteckb[data-uri="' + file.path + '"]').show();
                            } else {
                                $('button.delete[data-uri="' + file.path + '"]').hide();
                                $('input.deleteckb[data-uri="' + file.path + '"]').hide();
                            }
                        });
                        //Recopie des attributs du champ input
                        $.each($attchmtInput.attributes, function (i, attrib) {
                            // set each attribute to the specific value
                            $(newselect).attr(attrib.name, attrib.value);
                        });
                        //Remplacement du champ input par le nouveau select
                        $clone.find(target).replaceWith(newselect);
                        //Selection de la valeur
                        console.log(filepath);
                        if (filepath)
                            $(newselect).val(filepath);
                        //select change event
                        $(newselect).change(function () {
                            var container = $(this).closest('.sedafield-container');
                            container.find('select').not(this).select2('destroy');
                            var option = $('option:selected', this);

                            container.find('.seda-attribute').val('');
                            container.find('.seda-attribute.filename').val(option.text()).prop('readonly', true);

                            if (option.attr('data-format'))
                                container.find('.seda-attribute.format').val(option.attr('data-format'));

                            if (option.attr('data-mimecode'))
                                container.find('.seda-attribute.mimeCode').val(option.attr('data-mimecode'));

                            container.parent().find('[data-name="Size"]').val('').prop('readonly', true);
                            if (option.attr('data-size'))
                                container.parent().find('[data-name="Size"]').val(option.attr('data-size'));

                            container.parent().find('[data-sedaname="Size"] [data-name="unitCode"]').select2('destroy').val('').prop('readonly', true);
                            var bytecode = container.parent().find('[data-sedaname="Size"] [data-name="unitCode"]').find('option[data-title^="byte -"]');
                            if (bytecode.length)
                                container.parent().find('[data-sedaname="Size"] [data-name="unitCode"]').val(bytecode.val());

                            container.parent().find('[data-name="Integrity"]').val('').prop('readonly', true).attr('style', 'font-family: monospace; font-size: 11px; width: 450px;');
                            if (option.attr('data-integrity'))
                                container.parent().find('[data-name="Integrity"]').val(option.attr('data-integrity'));

                            container.parent().find('[data-sedaname="Integrity"] [data-name="algorithme"]').val('').prop('readonly', true);
                            if (option.attr('data-algorithme'))
                                container.parent().find('[data-sedaname="Integrity"] [data-name="algorithme"]').val(option.attr('data-algorithme'));

                            autocomplete(container.parent().find('[data-sedaname="Size"] [data-name="unitCode"]'));
                            container.parent().find('[data-sedaname="Size"] select[data-name="unitCode"]').select2("readonly", true);
                            container.parent().find('[data-sedaname="Size"] .attributs .downloadCompleteSedaList').hide();
                            autocomplete(container.find('select').not(this));
                            if ($(this).val() != '')
                                $('#nouveauFichier').hide();
                            else
                                $('#nouveauFichier').show();
                        }).change();
                        $('#singleDocUploadBtn').fileupload({
                            url: $('#singleDocUploadForm').attr('action'),
                            dataType: 'json',
                            context: $('#singleDocUploadBtn')[0],
                            autoUpload: true,
                            done: function (e, data) {
                                var file = data.result.files[0];
                                $(newselect).append(
                                    $('<option/>').text(file.name)
                                        .attr('value', file.name)
                                        .attr('data-format', file.format)
                                        .attr('data-mimecode', file.mimecode)
                                        .attr('data-size', file.size)
                                        .attr('data-integrity', file.integrity)
                                        .attr('data-algorithme', file.algorithme)
                                );
                                $(newselect).select2('val', file.name).trigger("change");
                                initDocUpload();
                            }
                        }).on('fileuploadadd', function () {
                            $('.singleUploadBtnText').text('Envoi en cours...');
                            $('.iconSingleUploadBtn')
                                .removeClass('icon-upload-alt')
                                .addClass('icon-time');
                            $('#saveSedaObject').prop('disabled', true);
                        }).on('fileuploadalways', function () {
                            $('.singleUploadBtnText').text('Nouveau fichier');
                            $('.iconSingleUploadBtn')
                                .addClass('icon-upload-alt')
                                .removeClass('icon-time');
                            $('#saveSedaObject').removeAttr('disabled');
                        });

                        var uploadfile = $('<span/>')
                            .attr('id', 'nouveauFichier')
                            .append(
                            $('<span/>')
                                .attr('class', 'btn btn-success fileinput-button')
                                .attr('title', 'Choisir un fichier depuis votre poste de travail')
                                .attr('onclick', "$('#singleDocUploadBtn').trigger('click');")
                                .html('<i class="icon-upload-alt iconSingleUploadBtn"></i> <span class="singleUploadBtnText">Nouveau fichier</span>'))
                            .append($('<p/>').css('clear', 'both'));

                        $(newselect).parent().find('>label').first().after(uploadfile);

                        //Autocomplétion sur le select
                        autocomplete(newselect);
                        //Afficher Panneau principal
                        $content.slideDown();
                        $.jGrowl("close");
                        if ($(newselect).val() != '') {
                            uploadfile.hide();
                            $.jGrowl("<div>Document : " + $(newselect).val() + "</div>", {
                                header: 'Mode : Édition objet SEDA',
                                sticky: true
                            });
                        } else {
                            $.jGrowl("<div>Document</div>", {header: 'Mode : Création objet SEDA', sticky: true});
                        }
                    });
                }
            }
        }
    }

    // input[xsd:duration] => select[xsd:duration]
    var duration = $clone.find('input[data-base="xsd:duration"]');
    if (duration.length) {
        $(duration).each(function () {
            var selectduration = $('#selectDuration > select').clone().removeAttr('disabled').show();
            //Recopie des attributs du champ input
            $.each(this.attributes, function (i, attrib) {
                // set each attribute to the specific value
                $(selectduration).attr(attrib.name, attrib.value);
            });
            //Selection de la valeur
            if ($(this).val()) {
                $(selectduration).val($(this).val());
            }
            //Remplacement du champ input par le nouveau select
            $(this).replaceWith($(selectduration));
        });
    }

    $clone.find('select').select2('destroy');
    //Copie valeur select clones
    $source.find('select').each(function (i) {
        $clone.find("select").eq(i).val($(this).val());
    });

    //Copier les champs dans l'éditeur (champs requis en premier)
    $clone.find('.sedafield.required').closest('.sedafield-container').each(function () {
        $content.append($(this));
    });
    $clone.find('.sedafield:not(.required)').closest('.sedafield-container').each(function () {
        $content.append($(this));
    });

    if ($a.attr('data-title') == "Keyword"
        || $a.attr('data-title') == "ContentDescriptive") {

        $content.find('.sedafield-container').not('.keyword-auto, .keyword-type_saisie').addClass('keyword-manual').hide();

        if (!$content.find('.keyword-type_saisie').length) {
            var type_saisie = $('<div/>').attr('class', 'keyword-type_saisie sedafield-container')
                .append($('<div/>').attr('class', 'input select required sedafield')
                    .append($('<label/>').attr('for', 'type_saisie').text('Choisissez le type de saisie du mot clé'))
                    .append($('<select/>').attr('id', 'type_saisie').attr('data-name', "Type de saisie du mot-clé")
                        .append($('<option/>').attr('value', ''))
                        .append($('<option/>').attr('value', 'free').text('Saisie libre'))
                        .append($('<option/>').attr('value', 'controled').text('Saisie contrôlée : choix parmi une liste de mots clés'))
                )
            );
            $content.prepend(type_saisie);

            if ($content.find('[data-name="KeywordContent"]').val())
                $content.find('#type_saisie').val('free');
        }

        $content.find('select#type_saisie').change(function () {
            onChangeTypeSaisieMotCles($(this));
        }).change();

        if (!$content.find('.keyword-lists').length) {
            //div
            var select_keyword_list = $('#selectKeywordList').clone();
            $(select_keyword_list)
                .removeAttr('id')
                .addClass('keyword-auto')
                .addClass('keyword-lists');
            //select
            $(select_keyword_list).find('select')
                .attr('disabled', 'disabled')
                .addClass('select-keyword-list')
                .removeAttr('name');

            $content.find('.keyword-type_saisie').after(select_keyword_list);
        }
        $content.find('select.select-keyword-list').change(function () {
            onChangeKeywordLists($(this));
        });

        if (!$content.find('.keyword-keywordslist').length) {
            $content.find('.keyword-lists').after($('<div/>')
                .attr('class', 'sedafield-container keyword-auto keyword-keywordslist')
                .attr('data-name', $($fieldset).attr('data-name')));
        }
    }

    if ($content.find('select[data-codes="LanguageCodeType"]')
        && $content.find('select[data-codes="LanguageCodeType"]').val() == '') {
        if ($content.find('select[data-codes="LanguageCodeType"] option[value="fr"]').length)
            $content.find('select[data-codes="LanguageCodeType"]').val('fr');
        else if ($content.find('select[data-codes="LanguageCodeType"] option[value="fra"]').length)
            $content.find('select[data-codes="LanguageCodeType"]').val('fra');
    }

    var select, $input;

    if ($content.find('input[data-name="ArchivalAgreement"]')) {
        select = $('#selectArchivalAgreement select').removeAttr('disabled');
        $input = $content.find('input[data-name="ArchivalAgreement"]');
        $($input).parent().find('.toggleAttributes').remove();
        $($input).closest('.sedafield-container').find('fieldset.attributs').remove();

        $($input).each(function () {
            //Recopie des attributs du champ input
            $.each(this.attributes, function (i, attrib) {
                // set each attribute to the specific value
                $(select).attr(attrib.name, attrib.value);
            });
            //Selection de la valeur
            if ($(this).val()) {
                $(select).val($(this).val());
            }
            //Remplacement du champ input par le nouveau select
            $(this).replaceWith($(select));
        });
    }

    if ($content.find('input[data-name="ArchivalProfile"]')) {
        select = $('#selectArchivalProfile select').removeAttr('disabled');
        $input = $content.find('input[data-name="ArchivalProfile"]');
        $($input).parent().find('.toggleAttributes').remove();
        $($input).closest('.sedafield-container').find('fieldset.attributs').remove();

        $($input).each(function () {
            //Recopie des attributs du champ input
            $.each(this.attributes, function (i, attrib) {
                // set each attribute to the specific value
                $(select).attr(attrib.name, attrib.value);
            });
            //Selection de la valeur
            if ($(this).val()) {
                $(select).val($(this).val());
            }
            //Remplacement du champ input par le nouveau select
            $(this).replaceWith($(select));
        });
    }
    //Autocomplétion
    autocomplete($content.find('select'));

    $content.append($('<hr/>'));
    var actions = $('<div id="actions">');
    var cancelbtn = $('<input/>')
        .attr('id', 'cancelEditSedaObject')
        .attr('class', 'btn')
        .attr('type', "button")
        .css('margin-right', "10px")
        .click(function () {
            cancelEditSedaObject(num)
        })
        .val("Annuler");
    actions.append(cancelbtn);
    var savebtn = $('<input/>')
        .attr('id', 'saveSedaObject')
        .attr('class', 'btn btn-primary')
        .attr('type', "button")
        .click(function () {
            saveSedaObject(num)
        })
        .val("Enregistrer");
    actions.append(savebtn);
    $content.append(actions);
    if (!waitForShow)
        $content.slideDown();

    $("html, body").animate({scrollTop: $('#bdxContainer').offset().top - 20});

    //FIXME offset (init after?)
    //DateTime picker
    $content.find('.datepicker').each(function () {
        $(this).datetimepicker({
            language: 'fr',
            pickTime: false
        }).on('changeDate', function (e) {
            //Fermer le picker après la selection
            $(this).datetimepicker('hide');
        });
    });

    $content.find('.datetimepicker').each(function () {
        $(this).datetimepicker({
            language: 'fr',
            pick12HourFormat: false
        });
    });

    if ($content.find('input.ArchiveTransferDate').length && $content.find('input.ArchiveTransferDate').val() == '') {
        $content.find('input.ArchiveTransferDate').val(getCurrentDateTime());
    }
    if (!creation)
        $.jGrowl("<div>" + $a.text() + "</div>", {header: 'Mode : Édition objet SEDA', sticky: true});

    $('#bdxAffichage').css('background-color', 'white');
    calculateSize();
    return true;
}

function onChangeTypeSaisieMotCles(element) {
    var manuals = $(element).closest('.sedaform').find('.keyword-manual'),
        autos = $(element).closest('.sedaform').find('.keyword-auto'),
        all = $(element).closest('.sedaform').find('.keyword-manual, .keyword-auto');
    $(all).hide();
    $(all).find('input, select, textarea').attr('disabled', 'disabled');
    if ($(element).val() == 'free') {
        $(manuals).show()
            .find('input, select, textarea').removeAttr('disabled');
    }
    else if ($(element).val() == 'controled') {
        $(autos).show()
            .find('input, select, textarea').removeAttr('disabled');
    }
}

/**
 * Fonction chargement
 * @param elem
 */
function onChangeKeywordLists(elem) {
    var keywordslist = $(elem).closest('.sedaform').find('.keyword-keywordslist'),
        selectedOption = $(elem).val();
    //Vider et cacher liste mots clés
    $(keywordslist).empty().hide();
    if (selectedOption === '')
        return;
    var ajaxUrl = $(elem).closest('form').attr('data-keywords-url') + '/' + selectedOption + '/id/1';
    $.ajax({
        url: ajaxUrl,
        dataType: 'json',
        beforeSend: function () {
            $.jGrowl("<div>Téléchargement de la liste des mots-clés en cours...</div>", {header: "Veuillez patienter"});
        },
        complete: function () {
            $.jGrowl("<div>Téléchargement de la liste terminé !</div>", {header: "Téléchargement terminé"});
        },
        success: function (result) {
            var container = $('<div class="input select required sedafield">')
                .append($('<label/>').attr('for', 'select-keyword').text('Choisissez un mot clé'))
                .append($('<select/>').attr('id', 'select-keyword')
                    .attr('name', $(keywordslist).attr('data-name') + '[id]')
                    .attr('data-name', 'KeywordContent')
                    .append($('<option/>').attr('value', ''))
            );

            $.each(result, function (key, infos) {
                $(container).find('#select-keyword').append(
                    $('<option/>').text(infos).attr('value', key)
                );
            });
            autocomplete($(keywordslist).html(container).show().find('#select-keyword'));
        },
        error: function (XMLHttpRequest, textStatus, errorThrown) {
            $.jGrowl("<div class='erreur'>Une erreur s'est produite : " + errorThrown + "<br/>Veuillez vous reconnecter</div>", {header: textStatus});
        }
    });
}

/**
 * Retourne la date/heure courante au format yyyy-MM-dd hh:mm:ss
 * @returns {string} au format yyyy-MM-dd hh:mm:ss
 */
function getCurrentDateTime() {
    var fullDate = new Date(),
        year = fullDate.getFullYear(),
        month = fullDate.getMonth() + 1,
        day = fullDate.getDate(),
        hour = fullDate.getHours(),
        minute = fullDate.getMinutes(),
        second = fullDate.getSeconds();
    return year + '-'
    + (month < 10 ? '0' : '') + month + '-'
    + (day < 10 ? '0' : '') + day + ' '
    + (hour < 10 ? '0' : '') + hour + ':'
    + (minute < 10 ? '0' : '') + minute + ':'
    + (second < 10 ? '0' : '') + second;
}
/**
 * Clone select organization (service)
 * @param type
 * @param name
 * @param selected agence selectionnée
 * @returns {*|jQuery}
 */
function cloneAgency(type, name, selected) {
    var $clone = $('#select' + type).clone();

    $clone
        .show()
        .removeAttr('id');

    $clone.find('select')
        .attr('name', name)
        .removeAttr('disabled');

    if (selected)
        $clone.find('select').val(selected);

    return $clone;
}

/**
 * Termine l'édition d'un objet seda
 * enregistre l'arbre
 * @param id
 */
function saveSedaObject(id) {
    $('#bdxContent').find('.datepicker .datetimepicker').datetimepicker('destroy');
    if (updateForm(id)) {
        if ($('#ArchivetransferArchiveTransferId').length && $('#ArchivetransferArchiveTransferId').val() > 0) {
            $('#fileUploadBloc').hide();
            reloadTree();
            $.jGrowl("<div>Pensez à sauvegarder le bordereau.</div>", {
                header: "Objet SEDA enregistré !",
                life: "3000"
            });
        }
        else {
            stopEdit();
            $('#saveSedaFormBtn').closest('form').trigger('submit');
        }
    }
}
function reloadTree() {
    var state = $('#browser').jstree(true).get_state();
    $('#browser').jstree(true).destroy();
    sedaFormToTree();
    stopEdit();
    $('#browser').jstree(true).set_state(state);
}
/**
 * Recopie les valeurs des champs si formulaire conforme
 * @param id
 * @returns {boolean}
 */
function updateForm(id) {
    var editeur_containers = $('#bdxContent > .sedafield-container'),
        required = editeur_containers.find('.sedafield.required').find('input[type="text"], input[type="number"], select, textarea').filter('.sedafield-input:enabled'),
        conform = true;
    //Parcours champs requis
    required.each(function () {
        if ($(this).val() == '' && $(this).attr('data-name')) {
            conform = false;
            $.jGrowl("<div class='erreur'>Champ requis : " + $(this).attr('data-name') + "</div>", {header: 'Erreur :'});
        }
    });
    //Formulaire invalide
    if (!conform) {
        $("html, body").animate({
            scrollTop: required.filter(function () {
                return $(this).val() == "";
            }).first().parent().offset().top
        });
        return false;
    }
    //Sinon : conforme
    //Masquer blocs attributs dépliés
    editeur_containers.find('.attributs:visible').hide();
    //Détruit instances select2
    editeur_containers.find('select').select2('destroy');
    // Seda fields cachés (formulaire template)
    var form_fields = $('.sedafieldset[data-id="' + id + '"] > .sedafields');

    // Cas particuliers : objets Document et RelatedData
    var $a = $('a[data-id="' + id + '"]');
    if ($a.attr('data-title').indexOf("RelatedData") >= 0
        || $a.attr('data-title') == "Document") {
        var target = 'select.RelatedDataTypeData';
        if ($a.attr('data-title') == "Document")
            target = 'select.DocumentAttachment';
        var select = $(editeur_containers).find(target),
            $input = $('<input/>').attr('type', 'text');
        $(select).parent().find('span.fileinput-button').remove();
        $(select).each(function () {
            //Recopie des attributs du champ input
            $.each(this.attributes, function (i, attrib) {
                // set each attribute to the specific value
                $($input).attr(attrib.name, attrib.value);
            });
            //Selection de la valeur
            if ($(this).val()) {
                $($input).val($(this).val());
            }
            //Remplacement du champ input par le nouveau select
            $(this).replaceWith($($input));
        });
    } else if ($a.attr('data-title').indexOf("Keyword") >= 0
        || $a.attr('data-title') == "ContentDescriptive") {
        form_fields.find('>.sedafield-container').remove();
        if (!editeur_containers.find('[data-name="KeywordType"]').val())
            editeur_containers.find('[data-name="listVersionID"]').attr('disabled', 'disabled');
        else
            editeur_containers.find('[data-name="listVersionID"]').removeAttr('disabled');

        editeur_containers.find('textarea').each(function () {
            if ($(this).val())
                $(this).text($(this).val());
        });
        editeur_containers.detach().prependTo(form_fields);
        return true;
    }

    // Remplacer blocs du formulaire (caché) par ceux correspondant dans l'éditeur interactif
    editeur_containers.each(function () {
        var name = $(this).attr('data-sedaname'),
            dest_container = form_fields.find('[data-sedaname="' + name + '"]').first();
        if (dest_container.length) {
            //Recopie html de la valeur des textareas
            $(this).find('textarea').each(function () {
                $(this).text($(this).val());
            });
            dest_container.replaceWith($(this).detach());
        } else { // Templates select bdd (Agency, ...)
            // FIXME si plusieurs champs dans un linked object
            form_fields.empty();
            $(this).detach().appendTo(form_fields);
        }
    });
    return true;
}

/**
 * Annule l'ajout d'un objet seda
 */
function cancelEditSedaObject(id) {
    var tree = $('#browser').jstree(true),
        type = tree.get_type(id),
        inner = $('.sedafieldset[data-id=' + id + ']').closest('.innerFieldset'),
        min = inner.attr('data-min'),
        max = inner.attr('data-max');
    if (!tree.get_selected().length) {
        tree.deselect_all();
        tree.select_node(id);
    }
    //Si etat brouillon
    if (type == 'draft') {
        var parent = tree.get_parent(id),
            count = deleteSedaObject($('.sedafieldset[data-id="' + id + '"] > .deleteSedaObject'), true);
        //Renommer le noeud parent (update nombre enfants)
        tree.delete_node(id);
        tree.select_node(parent);

        if ($('#' + parent).length && max != 1) {
            var $parentlink = $('#' + parent + ' > a');
            var libelle = $parentlink.text().substr(0, $parentlink.text().indexOf('('))
                + '(' + count + ')';
            tree.rename_node(parent, libelle);
        }
    }
    else {
        type = hasIncompleteRequired(id) ? 'incomplete'
            : min == undefined || min == 1 ? 'complete' : 'facultatifcomplete';
        tree.set_type(id, type);
    }
    //reprendre état initial
    stopEdit();
}

/**
 * Génère un identifiant unique
 * @returns {string}
 */
function uniqId() {
    seed++;
    return 'treeitem' + seed;
}

/**
 * Ajouter une masque (overlay) au panneau de navigation le rendant innaccessible
 */
function overlayNav() {
    $("<div/>").attr('id', 'overlayNav').css({
        position: "absolute",
        width: "100%",
        height: "100%",
        left: 0,
        top: 0,
        zIndex: 1000,
        backgroundColor: "#ccc",
        opacity: "0.4",
        cursor: "not-allowed",
        display: "none"
    }).appendTo($("#bdxNavigation").css("position", "relative"));
    $('#overlayNav').fadeIn();
    $('#bdxNavigation').css('overflow', 'hidden');
}

/**
 * Supprimer l'overlay pour permettre d'utilisateur le panneau de navigation
 */
function stopOverlay() {
    $('#overlayNav').fadeOut('400', function () {
        $(this).remove();
        $('#bdxNavigation').css('overflow', 'auto');
    });
}
/**
 *
 * @param sedafieldset_id
 * @returns {boolean}
 */
function hasIncompleteRequired(sedafieldset_id) {
    var incomplete = $('.sedafieldset[data-id="' + sedafieldset_id + '"]')
        .find('> .sedafields > .sedafield-container .sedafield.required')
        .children('input, textarea, select')
        .not(':disabled')
        .filter(function () {
            return $(this).val() == "";
        });
    return incomplete.length > 0;
}

/**
 * Construction html du menu de navigation (arbre du panneau latéral gauche)
 * @param sedafieldset
 * @returns {string} liste HTML correspondant aux fieldsets
 */
function recursiveSedaFormToList(sedafieldset) {
    var id = sedafieldset.attr("data-id") ? sedafieldset.attr("data-id") : uniqId();
    sedafieldset.attr("data-id", id);
    var name = '',
        object = sedafieldset.attr('data-title');
    if (object)
        switch (object) {
            case 'ArchiveTransfer':
                name = sedafieldset.find('[data-name="Comment"]').first().val();
                break;
            case 'Archive' :
                name = sedafieldset.find('[data-name="Name"]').first().val();
                break;
            case 'ArchiveObject' :
                name = sedafieldset.find('[data-name="Name"]').first().val();
                break;
            case 'Document' :
                name = sedafieldset.find('[data-name="Attachment"]').first().val();
                break;
            case 'AccessRestrictionRules' :
            case 'AccessRestrictionRule' :
                //name = sedafieldset.find('[data-name="Code"]').first().val();
                name = sedafieldset.find('[data-name="Code"]').first().find('option:selected').text();
                break;
            case 'AppraisalRules' :
            case 'AppraisalRule' :
                if (sedafieldset.find('[data-name="Duration"]').first().val()) {
                    var duration = sedafieldset.find('[data-name="Duration"]').first().val().match(/P([0-9]+)Y/)[1];
                    name = sedafieldset.find('[data-name="Code"]').first().val() + ' - ' + duration + ' an';
                    if (duration > 1) name += 's';
                }
                break;
            case 'Keyword' :
                var keywordInput = sedafieldset.find('[data-name="KeywordContent"]').not(':disabled').first();
                if (keywordInput.length) {
                    if (keywordInput.get(0).tagName == 'SELECT')
                        name = keywordInput.find('option:selected').text();
                    else
                        name = keywordInput.val();
                }
                break;
            default :
                if (object.indexOf('Agency') !== false
                    || object == 'Repository') {
                    name = $('#select' + object).find('option[value="' + sedafieldset.find('[data-name="' + object + '"]').first().val() + '"]').text();
                }
                break;
        }

    var min = sedafieldset.closest('.innerFieldset').attr('data-min'),
        type = hasIncompleteRequired(id) ? 'incomplete'
            : min == undefined || min == 1 ? 'complete'
            : 'facultatifcomplete';

    var code = '<li id="' + id + '" data-jstree=\'{"type":"' + type + '"}\'';

    if (!sedafieldset.closest('.innerFieldset').length) //root element
        code += ' class="jstree-open"';
    code += '>';

    if (!sedafieldset.closest('.innerFieldset').length) { //root element
        code += '<a href="#" ' +
        'data-id="' + id + '" ' +
        'title="' + sedafieldset.attr("data-title") + '" ' +
        'data-title="' + sedafieldset.attr("data-title") + '" ' +
        'data-name="' + sedafieldset.attr("data-name") + '" ' +
        'onclick="editSedaObject(\'' + id + '\')">';
        code += $.trim(sedafieldset.children('legend').first().text());
    } else {
        var $innerParent = sedafieldset.closest('.innerFieldset'),
            definition = '',
            libelle = sedafieldset.attr("data-title");

        if (sedafieldset.attr('data-definition')) {
            definition = sedafieldset.attr('data-definition');
            if (definition.split(' ').length <= 3)
                libelle = definition;
        }

        code += '<a href="#" ' +
        'onclick="editSedaObject(\'' + id + '\')" ' +
        'data-id="' + id + '" ' +
        'data-min="' + $innerParent.attr("data-min") + '" ' +
        'data-max="' + $innerParent.attr("data-max") + '" ' +
        'data-name="' + $innerParent.attr("data-name") + '" ' +
        'data-entryname="' + $innerParent.attr("data-entryname") + '" ' +
        'data-title="' + sedafieldset.attr("data-title") + '" ' +
        'data-definition="' + definition + '" ' +
        'title="' + sedafieldset.attr("data-title") + " : \n" + sedafieldset.attr('data-definition') + '">';
        code += libelle;
    }

    if (name) {
        if (name.length > max_lenght)
            name = name.substr(0, max_lenght) + '<span style="display: none">' + name.substr(max_lenght) + '</span>...';
        if (name.length)
            code += ' : ' + name;
    }
    code += '</a>';

    var $children = sedafieldset.find('> .seda-container.sedafields > .innerFieldset');
    if ($children) {
        code += ' <ul>';
        $children.each(function () {
            var $child = $(this).find('> .sedaobjects > .sedafieldset').not('.sedatemplate');

            if ($child.length == $(this).attr("data-max") == 1)
                code += recursiveSedaFormToList($child);
            else {
                var childId = $(this).attr("data-templateid") ? $(this).attr("data-templateid") : uniqId(),
                    $sedatemplate = $(this).find('> .sedaobjects > .sedafieldset.sedatemplate'),
                    $addButton = $(this).find('> .addSedaObject'),
                    title = $(this).attr('data-title');

                definition = '';

                $(this).attr("data-templateid", childId);
                $addButton.attr("data-templateid", childId);
                $sedatemplate.attr("data-templateid", childId);
                if ($(this).attr('data-max') == 1)
                    code += '<li id="' + childId + '" data-jstree=\'{"type":"facultatif"}\'>';
                else
                    code += '<li id="' + childId + '" data-jstree=\'{"type":"folder"}\'>';

                if ($(this).attr('data-definition') && $(this).attr('data-definition') != $(this).attr('data-title')) {
                    definition = $(this).attr('data-definition').replace('"', "&quot;");
                    title += " : \n" + definition;
                }
                code += '<a href="#" ' +
                'title="' + title + '" ' +
                'data-id="' + childId + '" ' +
                'data-title="' + $(this).attr('data-title') + '" ' +
                'data-name="' + $(this).attr('data-name') + '" ' +
                'data-definition="' + definition + '" ' +
                'data-min="' + $(this).attr('data-min') + '" ' +
                'data-max="' + $(this).attr('data-max') + '" ';
                //if ($(this).attr('data-min') == '0' && $(this).attr('data-max') == '1' && !$child.length)
                if ($(this).attr('data-max') == 'unbounded' || $child.length <= $(this).attr('data-max'))
                    code += 'onclick="newSedaObject(\'' + childId + '\')"';
                code += '>';

                if ($(this).attr('data-definition') && definition.split(' ').length <= 3)
                    code += $(this).attr('data-definition');
                else
                    code += $(this).attr('data-title');
                if ($(this).attr('data-max') != 1)
                    code += ' (' + $(this).find('> .sedaobjects > .sedafieldset:not(.sedatemplate)').length + ')';
                code += '</a>';
                code += ' <ul>';
                $child.each(function () {
                    if (!$(this).hasClass('sedatemplate'))
                        code += recursiveSedaFormToList($(this));
                });
                code += '</ul>';
                code += '</li>'
            }
        });
        code += '</ul>';
    }
    code += '</li>';
    return code;
}

/**
 * Remplace le select par la liste complete des options
 * @param element
 * @param code (nom du fichier contenant les codes)
 */
function downloadCompleteSedaList(element, code) {
    if (!confirm("Confirmer le téléchargement de la liste complète des options ?")) return false;
    $.jGrowl("<div>Téléchargement de la liste complète en cours...</div>", {header: "Veuillez patienter"});
    var sedaprefix = $('#ArchivetransferVersionSeda').val();
    $.ajax({
        url: $(element).closest('form').attr('data-url') + '/getCompleteSedaListAjax/' + sedaprefix + '/' + code,
        type: 'GET',
        dataType: 'json',
        error: function (xOptions, textStatus) {
            $.jGrowl("<div>Votre session a expiré. Veuillez vous reconnecter.<br><em>Astuce: Pour ne pas perdre votre travail en cours, reconnectez vous depuis un autre onglet.</em></div>", {
                header: textStatus,
                life: 30000
            });
        }
    }).done(function (result) {
        var container = $(element).parent();
        $(container).find('select').select2('destroy');
        $(container).find('select').empty()
            .append($('<option/>').attr('value', ''));
        //Ajout des fichiers au select
        $.each(result, function (key, infos) {
            var text = key;
            $.each(infos, function (i, val) {
                text += ' - ' + val;
            });
            $(container).find('select').append(
                $('<option/>').text(text).attr('value', key)
            );
        });
        autocomplete($(container).find('select'));
        $(element).remove();
        $.jGrowl("<div>Téléchargement de la liste terminé !</div>", {header: "Téléchargement terminé"});
    });
}

/**
 * Changement valeur identifiant
 * @param element
 */
function calcTransferIdentifierAuto(element) {
    var checked = $(element).prop('checked'),
        input = $(element).parent().find('.ArchiveTransferTransferIdentifier');
    if (checked) {
        $(input).val('#A_CALCULER_LORS_DU_VERROUILLAGE#').prop('readonly', true);
    } else {
        $(input).val('').prop('readonly', false);
    }
}
