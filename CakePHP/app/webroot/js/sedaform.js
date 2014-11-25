/**
 * Créé par Florian Ajir <florian.ajir@adullact.org> le 12/05/14.
 */

/**
 * Script de chargement de la page
 */
$(document).ready(function () {
    //Mise a jour des compteurs
    $('.sedaform .innerFieldset').each(function () {
        var count = $(this).children('.sedaobjects').children('.sedafieldset:not(.sedatemplate)').length;
        $(this).children('legend').children('.nbSedaObjects').first().text(count);
        $(this).children('.count').val(count);
    });
    $('.sedaform .sedafield-container-multiple').each(function () {
        var count = $(this).children('.sedafield-multiple').length;
        $(this).children('.count').val(count);
    });
    //Afficher les boutons de suppression d'objet
    $('.sedaform .deleteSedaObject:not(.disabled)').show();
    //Désactiver champs appartenant aux templates
    $('.sedafieldset.sedatemplate').find('input[type="text"], input[type="number"], select, textarea').prop('disabled', true);
    //Autocomplétion sur les élements select visibles
    autocomplete($('.sedaform .sedafieldset:not(.sedatemplate) select:not(":disabled")'));
});

/**
 * Transformation select -> select2 (plugin autocompletion jquery)
 * @param $nodes
 */
function autocomplete($nodes) {
    $nodes.select2({
        width: 'resolve',
        allowClear: true,
        placeholder: 'Aucune sélection',
        formatResult: function (result, container, query, escapeMarkup) {
            var markup = [],
                element = result.element;

            markMatch(result.text, query.term, markup, escapeMarkup);

            if ($(element).data('title'))
                return '<div title="' + $(element).data('title') + '">' + markup.join("") + '</div>';
            else
                return markup.join("");
        }
    }).focus(function () {
        $(this).select2('focus');
        $("html, body").animate({scrollTop: $(this).parent().offset().top}, 0);
    });
}

/**
 * Fonction copiée de select2 pour l'affichage des résultats de recherche dans autocomplete
 * permet ainsi de combiner a fonction perso pour afficher des titres sur les options du select
 * @param text
 * @param term
 * @param markup
 * @param escapeMarkup
 */
function markMatch(text, term, markup, escapeMarkup) {
    var match = text.toUpperCase().indexOf(term.toUpperCase()),
        tl = term.length;

    if (match < 0) {
        markup.push(escapeMarkup(text));
        return;
    }

    markup.push(escapeMarkup(text.substring(0, match)));
    markup.push("<span class='select2-match'>");
    markup.push(escapeMarkup(text.substring(match, match + tl)));
    markup.push("</span>");
    markup.push(escapeMarkup(text.substring(match + tl, text.length)));
}

/**
 * plier/déplier un bloc
 * @param legend
 */
function collapse(legend) {
    //Init DOM node
    var $fieldset = $(legend).closest('fieldset');
    var $icon = $(legend).find('i');

    if ($fieldset.hasClass('expanded')) {
        //Etat: déplié; Action: plier;
        $(legend).css('font-style', 'italic');
        $icon
            .removeClass('icon-expand-alt')
            .addClass('icon-collapse-alt');
        $fieldset
            .removeClass('expanded')
            .addClass('collapsed');
    }
    else if ($fieldset.hasClass('collapsed')) {
        //Etat: plié; Action: déplier;
        $(legend).css('font-style', 'normal');
        $icon
            .removeClass('icon-collapse-alt')
            .addClass('icon-expand-alt');
        $fieldset
            .removeClass('collapsed')
            .addClass('expanded');
    }
    //Animation slide
    $fieldset.children('.seda-container').first().slideToggle(400, function () {
        //Si action = déplier, descendre la page au niveau du bloc
        if ($(this).is(':visible'))
            $("html, body").animate({scrollTop: $(this).parent().offset().top});
    });
}

/**
 * Basculer affichage attributs d'un champ
 * @param obj
 */
function toggleAttributes(obj) {
    // Animation avec effet slide
    $(obj).parent().next('.attributs').slideToggle(400, function () {
        //Si action = afficher, descendre la page au niveau du bloc
        if ($(this).is(':visible'))
            $("html, body").animate({scrollTop: $(this).parent().offset().top});
    });
}

/**
 * clone un objet depuis un template
 * @param obj
 * @param max
 * @param auto bool
 * @returns {boolean}
 */
function addSedaObject(obj, max, auto, disableCompletion) {
    var $innerFieldset = $(obj).closest('.innerFieldset');
    //Déplier le bloc si plié
    if ($innerFieldset.hasClass('collapsed'))
        collapse($innerFieldset.children('legend').first());

    var count = parseInt($innerFieldset.children('.count').first().val());
    //cloner le bloc template
    var $template = $innerFieldset.children('div.sedaobjects').first().children('fieldset.sedatemplate').first().clone();
    $template.removeClass('sedatemplate');

    $template.find('.sedafieldset:not(".sedatemplate") > .sedafields > .sedafield-container')
        .find('input[type="text"], input[type="number"], select, textarea')
        .removeAttr('disabled');
    //pas plus d'une occurence
    if (max == 1) {
        $(obj).prop('disabled', true);
        if ($innerFieldset.find('> .sedaobjects > .sedafieldset:not(".sedatemplate")').length == max)
            return false;
    }
    //mise a jour du input compteur
    $innerFieldset.children('.count').first().val(count + 1);
    //Changement de name des inputs du template
    var toChangeName = $template.find('div.sedafields > div.sedafield-container').find('input[type="text"], input[type="number"], select, textarea');
    changeTemplateInputsNameAndId(toChangeName, count, max);
    $template.find('select').val('');
    if ($template.attr('data-name')) {
        var oldtemplatename = $template.attr('data-name');
        if (max == 'unbounded' || max > 1)
            $template.attr('data-name', $template.attr('data-name').replace(/template/, count));
        else
            $template.attr('data-name', $template.attr('data-name').replace(/\[template\]/, ''));
        var newtemplatename = $template.attr('data-name');
        changeInnerDataName($template, oldtemplatename, newtemplatename);
    }

    //insert avant bouton
    $innerFieldset.children('div.sedaobjects').append($template);

    //Mise à jour nombre de bloc de ce type
    var nbSedaObject = $(obj).closest('.innerFieldset').children('.sedaobjects').first().children('fieldset.sedafieldset').not('.sedatemplate').length;
    $innerFieldset.children('legend').first().find('.nbSedaObjects').text(nbSedaObject);

    if (auto) {
        $template.show();
        if (!disableCompletion)
            autocomplete($template.find('select').not('.sedatemplate select'));
        $template.find('.deleteSedaObject').show();
    } else {
        // Affichage avec effet slide down
        $template.slideDown(400, function () {
            if (!disableCompletion)
                autocomplete($template.find('select').not('.sedatemplate select'));
            if ($(this).is(':visible'))
                $("html, body").animate({scrollTop: $template.offset().top - 50});
            $template.find('.deleteSedaObject').fadeIn();
        });
    }
}

/**
 * Fonction récursive qui change l'attribut data-name de tous les sedafieldset et innerFieldset
 * @param $fieldset source
 * @param oldtemplatename ancien name
 * @param newtemplatename nouveau name
 */
function changeInnerDataName($fieldset, oldtemplatename, newtemplatename) {
    if ($fieldset.hasClass('innerFieldset')) {
        $fieldset.find('>.sedaobjects>.sedafieldset').each(function () {
            var thisoldname = $(this).attr('data-name');
            $(this).attr('data-name', $(this).attr('data-name').replace(oldtemplatename, newtemplatename));
            var thisnewname = $(this).attr('data-name');
            changeInnerDataName($(this), thisoldname, thisnewname);
        });
    }
    else if ($fieldset.hasClass('sedafieldset')) {
        $fieldset.find('>.sedafields>.innerFieldset').each(function () {
            var thisoldname = $(this).attr('data-name');
            $(this).attr('data-name', $(this).attr('data-name').replace(oldtemplatename, newtemplatename));
            var thisnewname = $(this).attr('data-name');
            changeInnerDataName($(this), thisoldname, thisnewname);
        });
    }
}

/**
 * clone un objet depuis le parent
 * @param obj
 * @returns {boolean}
 */
function cloneSedaParentObject(obj) {
    var $innerFieldset = $(obj).closest('.innerFieldset'),
        max = $innerFieldset.attr('data-max');
    //Déplier le bloc si plié
    if ($innerFieldset.hasClass('collapsed')) {
        collapse($innerFieldset.children('legend').first());
    }
    var count = parseInt($innerFieldset.children('.count').first().val()),
        $template = $innerFieldset.closest('fieldset.sedafieldset').first().clone(); //cloner le bloc template

    //pas plus d'une occurence
    if (max == 1) {
        $(obj).prop('disabled', true);
        if (count)
            return false;
    }
    //mise a jour du input compteur
    $innerFieldset.children('.count').first().val(count + 1);

    var inputs = $template.find('div.sedafields > div.sedafield-container').find('select, input[type="text"], input[type="number"], textarea'),
        oldname = $template.attr('data-name'),
        newname = $innerFieldset.attr('data-name') + '[' + count + ']';

    $template.attr('data-name', newname);
    $template.find('.innerFieldset .sedafieldset:not(.sedatemplate)').remove();
    $template.find('.innerFieldset .count').val(0);

    //Changement de name des inputs du template
    changeCloneTemplateInputsName(inputs, oldname, newname);
    inputs.val('');
    inputs.filter('textarea').empty();

    if ($template.attr('data-name')) {
        var oldtemplatename = $template.attr('data-name');
        if (count != null)
            $template.attr('data-name', $template.attr('data-name').replace(/template/, count));
        else
            $template.attr('data-name', $template.attr('data-name').replace(/\[template\]/, ''));
        var newtemplatename = $template.attr('data-name');
        changeInnerDataName($template, oldtemplatename, newtemplatename);
    }

    $template.removeAttr('data-id');
    $template.find('[data-templateid]').removeAttr('data-templateid');
    //insert avant bouton
    $innerFieldset.children('div.sedaobjects').append($template);

    //Mise à jour nombre de bloc de ce type
    var nbSedaObject = $innerFieldset.children('.sedaobjects').first().children('fieldset.sedafieldset').not('.sedatemplate').length;
    $innerFieldset.children('legend').first().find('.nbSedaObjects').text(nbSedaObject);
    $template.find('input[type="text"], input[type="number"], select, textarea').filter(':not([readonly])').val('');
//    $template.find('input[type="text"], input[type="number"], select, textarea').filter('[data-default]').val($template.find('input[type="text"], input[type="number"], select, textarea').attr('data-default'));
    // Affichage avec effet slide down
    $template.slideDown(400, function () {
        autocomplete($template.find('div.sedafields > div.sedafield-container select').filter(':visible'));
        autocomplete($template.find('div.sedafields > div.sedafield-container select').filter(':visible').find('.attributs select'));
        if ($(this).is(':visible'))
            $("html, body").animate({scrollTop: $template.offset().top - 50});
        $template.find('.deleteSedaObject').fadeIn();
    });
}

/**
 * Modification des attributs name des champs à copier
 * ainsi que les id et label[for}
 * @param $inputs
 * @param count
 * @param max
 */
function changeTemplateInputsNameAndId($inputs, count, max) {
    //Changement de name des inputs des objets inclus dans le template
    $inputs.each(function () {
            if (max == 'unbounded' || max > 1) {
                if ($(this).attr('name'))
                    $(this).attr('name', $(this).attr('name').replace(/template/, count));
                $(this).attr('id', $(this).attr('id').replace(/template/, count));
            } else {
                if ($(this).attr('name'))
                    $(this).attr('name', $(this).attr('name').replace(/\[template\]/, ''));
                $(this).attr('id', $(this).attr('id').replace(/\-template/, ''));
            }
        $(this).prev('label').attr('for', $(this).attr('id'));
        if (!$(this).closest('.sedafieldset').hasClass('sedatemplate'))
            $(this).removeAttr('disabled');
    });
}

/**
 * Modification attribut name
 * @param container
 * @param count
 */
function changeMultipleInputsName(container, count) {
    //Changement de name des inputs des objets inclus dans le template
    var inputs = container.find('input[type="text"], input[type="number"], select, textarea');
    inputs.each(function () {
        $(this).attr('name', $(this).attr('name').replace(/0([^0]*)$/, count + '$1'));
        $(this).attr('id', $(this).attr('id').replace(/0([^0]*)$/, count + '$1'));
    });
    var label = container.find('label');
    label.each(function () {
        $(this).attr('for', $(this).attr('for').replace(/0([^0]*)$/, count + '$1'))
    });
}

/**
 * Modification des attributs name des champs à copier
 * @param $inputs
 * @param oldname
 * @param newname
 */
function changeCloneTemplateInputsName($inputs, oldname, newname) {
    //Changement de name des inputs des objets inclus dans le template
    $inputs.each(function () {
        if ($(this).attr('name'))
            $(this).attr('name', $(this).attr('name').replace(oldname, newname));
        if (!$(this).closest('.sedafieldset').hasClass('sedatemplate'))
            $(this).removeAttr('disabled');
    });
}

/**
 * Suppression d'un bloc objet
 * @param obj
 * @param auto
 */
function deleteSedaObject(obj, auto) {
    if (!auto) {
        if (!confirm('Etes-vous sûr de vouloir supprimer cet élément ?'))
            return false
    }
    // remise à zéro du compteur si max one pour permettre l'ajout d'un nouveau
    var $counter = $(obj).closest('.innerFieldset').find('.count');
    if ($counter.hasClass('maxone')) $counter.val(0);

    var nbSedaObject = $(obj).closest('.sedaobjects').children('.sedafieldset:not(.sedatemplate)').length - 1;
    // disparition du bloc
    if (auto) {
        $(obj).closest('.innerFieldset').find('.nbSedaObjects').first().text(nbSedaObject);
        $(obj).closest('.sedafieldset').closest('.innerFieldset').children('.addSedaObject').removeAttr('disabled');
        $(obj).closest('.sedafieldset').remove();
    }
    else {
        $(obj).fadeOut(200, function () {
            $(obj).closest('.innerFieldset').find('.nbSedaObjects').first().text(nbSedaObject);
            $(obj).closest('.sedafieldset').slideUp(400, function () {
                $(this).closest('.innerFieldset').children('.addSedaObject').removeAttr('disabled');
                $(this).remove();
            });
        });
    }
    return nbSedaObject;
}

/**
 * Ajout d'un champ seda multiple
 * @param obj
 */
function addSedaField(obj) {
    var count = parseInt($(obj).closest('.sedafield-container-multiple').children('.count').first().val());
    //select2 destroy (obligatoire avant clone() pour re-init)
    $(obj).closest('.sedafield-multiple').find('select').select2('destroy');
    $(obj).closest('.sedafield-multiple').find('.datepicker, .datetimepicker').datetimepicker('destroy');
    var $newField = $(obj).closest('.sedafield-multiple').clone();

    if ($newField.find('.addSedaField').attr('data-title') == 'Attachment')
        return false;

    autocomplete($(obj).closest('.sedafield-container').find('select'));
    //DateTime picker
    setUpDatePicker($(obj).closest('.sedafield-container-multiple').find('.datepicker'));
    setUpDateTimePicker($(obj).closest('.sedafield-container-multiple').find('.datetimepicker'));

    //reinit select2
    $newField.hide();
    //bouton add
    $newField.find('.addSedaField')
        .text('-')
        .attr('onclick', 'deleteSedaField(this)')
        .attr('class', 'removeSedaField')
        .attr('title', 'Supprimer cette propriété');

    $newField.find('input[type="text"], input[type="number"], select, textarea').each(function () {
        if ($(this).attr('data-default'))
            $(this).val($(this).attr('data-default'));
        else
            $(this).val('');

    });

    changeMultipleInputsName($newField, count);

    $(obj).closest('.sedafield-container-multiple').append($newField);
    $newField.find('.attributs').hide();
    count++;
    $(obj).closest('.sedafield-container-multiple').children('.count').first().val(count);

    //DateTime picker
    setUpDatePicker($newField.find('.datepicker'));
    setUpDateTimePicker($newField.find('.datetimepicker'));

    autocomplete($newField.find('select'));
    //Affichage en fondu
    $newField.slideToggle(400);
}

/**
 * Activation du plugin datetimepicker sur les elements du set
 * @param elements
 */
function setUpDateTimePicker(elements) {
    $(elements).each(function () {
        $(this).datetimepicker({
            language: 'fr',
            pick12HourFormat: false
        });
    });
}

/**
 * Activation du plugin datepicker sur les elements du set
 * @param elements
 */
function setUpDatePicker(elements) {
    //DateTime picker
    $(elements).each(function () {
        $(this).datetimepicker({
            language: 'fr',
            pickTime: false
        }).on('changeDate', function (e) {
            //Fermer le picker après la selection
            $(this).datetimepicker('hide');
        });
    });
}

/**
 * Supprime un seda field de type multiple
 * @param obj bouton du champ à supprimer
 */
function deleteSedaField(obj) {
    $(obj).closest('.sedafield-multiple').slideToggle(400, function () {
        $(this).remove();
    });
}

/**
 * Recopie le paramétrage d'un sedaform vers celui d'une autre version
 * @param from version seda source
 * @param to version seda destination
 */
function copySeda(from, to) {
    if (!confirm('Attention, cette opération écrase la valeur de tous les champs. Confirmer ?')) return false;
    $('.jGrowl-closer').click();
    resetForm($('#sedaform-' + to));
    var sedafieldsetsource = $('#sedaform-' + from + ' > .sedafieldset'),
        sedafieldsetdestination = $('#sedaform-' + to + ' > .sedafieldset');
    copySedaObjectFields(sedafieldsetsource, sedafieldsetdestination);
    $.jGrowl("Copie du paramétrage terminé.");
    $.jGrowl("<strong>Attention :</strong> certains champs sont spécifiques à la version, vérifiez les données.", {
        header: 'Copie incomplète',
        sticky: true
    });
}

/**
 * Copie d'un maximum de valeurs d'un fieldset à un autre
 * ou d'une version seda à une autre en cherchant un maximum d'équivalences
 * @param sedafieldsetsource
 * @param sedafieldsetdestination
 */
function copySedaObjectFields(sedafieldsetsource, sedafieldsetdestination) {
    var $fieldssource = sedafieldsetsource.find('> .sedafields > .sedafield-container'),
        $fieldsdestination = sedafieldsetdestination.find('> .sedafields > .sedafield-container');

    $fieldssource.each(function () {
        var $field = $(this).find('.sedafield').find('input[type="text"], input[type="number"], textarea, select').first(),
            dataname = $field.attr('data-name'),
            value = $field.val(),
            $attributes = $(this).find('.attributs').find('input[type="text"], input[type="number"], textarea, select'),
            $destination = $fieldsdestination.find("[data-name='" + dataname + "']");

        if (dataname && $destination.length) {
            //Recopie valeur du champ
            $destination.val(value);

            //Recopie valeur attributs
            var attrsDest = $destination.closest('.sedafield-container').find('> .attributs');
            $attributes.each(function () {
                attrsDest.find("[data-name='" + $(this).attr('data-name') + "']").val($(this).val());
            });
        }

    });

    //objets contenus
    var $innerFieldsetsSource = sedafieldsetsource.find('> .sedafields > .innerFieldset'),
        $innerFieldsetsDestination = sedafieldsetdestination.find('> .sedafields > .innerFieldset');

    $innerFieldsetsSource.each(function () {
        var count = $(this).find('> .count').val(),
            entryname = $(this).attr('data-entryname');

        if (count > 0) {
            var $innerDestination = $innerFieldsetsDestination.filter('[data-entryname="' + entryname + '"]');

            //Ajout des objets de ce type
            for (var i = 0; i < count; i++)
                addSedaObject($innerDestination.find(' > .addSedaObject'), $innerDestination.attr('data-max'), true);

            var sedafieldsetsource = $(this).find('> .sedaobjects > .sedafieldset:not(.sedatemplate)'),
                sedafieldsetdestination = $innerDestination.find('> .sedaobjects > .sedafieldset:not(.sedatemplate)');

            sedafieldsetsource.each(function (i) {
                if (sedafieldsetdestination.get(i))
                    copySedaObjectFields($(this), $(sedafieldsetdestination.get(i)));
            });
        }
    });
}

/**
 * RAZ d'un sedaform
 * @param sedaform
 */
function resetForm(sedaform) {
    $(sedaform).find('.sedafieldset:not(.sedatemplate) > .deleteSedaObject:not(.disabled)').each(function () {
        deleteSedaObject($(this), true);
    });
}