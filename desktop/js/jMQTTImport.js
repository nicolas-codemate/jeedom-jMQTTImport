/* This file is part of Jeedom.
*
* Jeedom is free software: you can redistribute it and/or modify
* it under the terms of the GNU General Public License as published by
* the Free Software Foundation, either version 3 of the License, or
* (at your option) any later version.
*
* Jeedom is distributed in the hope that it will be useful,
* but WITHOUT ANY WARRANTY; without even the implied warranty of
* MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
* GNU General Public License for more details.
*
* You should have received a copy of the GNU General Public License
* along with Jeedom. If not, see <http://www.gnu.org/licenses/>.
*/

callPluginAjax = function (_params) {
    $.ajax({
        async: _params.async == undefined ? true : _params.async,
        global: false,
        type: "POST",
        url: "plugins/jMQTTImport/core/ajax/jMQTTImport.ajax.php",
        data: _params.data,
        dataType: 'json',
        error: function (request, status, error) {
            handleAjaxError(request, status, error);
        },
        success: function (data) {
            if (data.state != 'ok') {
                $.fn.showAlert({message: data.result, level: 'danger'});
            } else {
                if (typeof _params.success === 'function') {
                    _params.success(data.result);
                }
            }
        }
    });
}


/* Permet la réorganisation des commandes dans l'équipement */
$("#table_cmd").sortable({
    axis: "y",
    cursor: "move",
    items: ".cmd",
    placeholder: "ui-state-highlight",
    tolerance: "intersect",
    forcePlaceholderSize: true
})

/* Fonction permettant l'affichage des commandes dans l'équipement */
function addCmdToTable(_cmd) {
    if (!isset(_cmd)) {
        var _cmd = {configuration: {}}
    }
    if (!isset(_cmd.configuration)) {
        _cmd.configuration = {}
    }
    var tr = '<tr class="cmd" data-cmd_id="' + init(_cmd.id) + '">'
    tr += '<td class="hidden-xs">'
    tr += '<span class="cmdAttr" data-l1key="id"></span>'
    tr += '</td>'
    tr += '<td>'
    tr += '<div class="input-group">'
    tr += '<input class="cmdAttr form-control input-sm roundedLeft" data-l1key="name" placeholder="{{Nom de la commande}}">'
    tr += '<span class="input-group-btn"><a class="cmdAction btn btn-sm btn-default" data-l1key="chooseIcon" title="{{Choisir une icône}}"><i class="fas fa-icons"></i></a></span>'
    tr += '<span class="cmdAttr input-group-addon roundedRight" data-l1key="display" data-l2key="icon" style="font-size:19px;padding:0 5px 0 0!important;"></span>'
    tr += '</div>'
    tr += '<select class="cmdAttr form-control input-sm" data-l1key="value" style="display:none;margin-top:5px;" title="{{Commande info liée}}">'
    tr += '<option value="">{{Aucune}}</option>'
    tr += '</select>'
    tr += '</td>'
    tr += '<td>'
    tr += '<span class="type" type="' + init(_cmd.type) + '">' + jeedom.cmd.availableType() + '</span>'
    tr += '<span class="subType" subType="' + init(_cmd.subType) + '"></span>'
    tr += '</td>'
    tr += '<td>'
    tr += '<label class="checkbox-inline"><input type="checkbox" class="cmdAttr" data-l1key="isVisible" checked/>{{Afficher}}</label> '
    tr += '<label class="checkbox-inline"><input type="checkbox" class="cmdAttr" data-l1key="isHistorized" checked/>{{Historiser}}</label> '
    tr += '<label class="checkbox-inline"><input type="checkbox" class="cmdAttr" data-l1key="display" data-l2key="invertBinary"/>{{Inverser}}</label> '
    tr += '<div style="margin-top:7px;">'
    tr += '<input class="tooltips cmdAttr form-control input-sm" data-l1key="configuration" data-l2key="minValue" placeholder="{{Min}}" title="{{Min}}" style="width:30%;max-width:80px;display:inline-block;margin-right:2px;">'
    tr += '<input class="tooltips cmdAttr form-control input-sm" data-l1key="configuration" data-l2key="maxValue" placeholder="{{Max}}" title="{{Max}}" style="width:30%;max-width:80px;display:inline-block;margin-right:2px;">'
    tr += '<input class="tooltips cmdAttr form-control input-sm" data-l1key="unite" placeholder="Unité" title="{{Unité}}" style="width:30%;max-width:80px;display:inline-block;margin-right:2px;">'
    tr += '</div>'
    tr += '</td>'
    tr += '<td>';
    tr += '<span class="cmdAttr" data-l1key="htmlstate"></span>';
    tr += '</td>';
    tr += '<td>'
    if (is_numeric(_cmd.id)) {
        tr += '<a class="btn btn-default btn-xs cmdAction" data-action="configure"><i class="fas fa-cogs"></i></a> '
        tr += '<a class="btn btn-default btn-xs cmdAction" data-action="test"><i class="fas fa-rss"></i> {{Tester}}</a>'
    }
    tr += '<i class="fas fa-minus-circle pull-right cmdAction cursor" data-action="remove" title="{{Supprimer la commande}}"></i></td>'
    tr += '</tr>'
    $('#table_cmd tbody').append(tr)
    var tr = $('#table_cmd tbody tr').last()
    jeedom.eqLogic.buildSelectCmd({
        id: $('.eqLogicAttr[data-l1key=id]').value(),
        filter: {type: 'info'},
        error: function (error) {
            $('#div_alert').showAlert({message: error.message, level: 'danger'})
        },
        success: function (result) {
            tr.find('.cmdAttr[data-l1key=value]').append(result)
            tr.setValues(_cmd, '.cmdAttr')
            jeedom.cmd.changeType(tr, init(_cmd.subType))
        }
    })
}


$('.eqLogicAction[data-action=importEq]').off('click').on('click', function () {
    let dialog_message = '';
    dialog_message += '<form id="ajaxForm">'
    dialog_message += '<label class="control-label">{{Importer les équipements depuis un csv: }}</label> ';
    dialog_message += '<input type="file" name="csvFile" accept=".csv,text/csv" style="display : inline-block;width:100%;">';
    dialog_message += '<br/><br/>';
    dialog_message += '<label class="control-label">{{Objet parent :}}</label> ';
    dialog_message += '<select class="bootbox-input bootbox-input-select form-control" name="parentObject">';
    $.each(jeeObjects, function (key, name) {
        dialog_message += '<option value="' + key + '">' + name + '</option>';
    });
    dialog_message += '</select><br/>';
    dialog_message += '<label class="control-label">{{Broker utilisé :}}</label> ';
    dialog_message += '<select class="bootbox-input bootbox-input-select form-control" name="broker">';
    $.each(jmqtt_globals.eqBrokers, function (key, name) {
        dialog_message += '<option value="' + key + '">' + name + '</option>';
    });
    dialog_message += '</select><br/>';
    dialog_message += '<div class="form-group">'
    dialog_message += '<label class="control-label">{{Nom de la colonne servant pour le nom de l\'équipement: }}</label> ';
    dialog_message += '<input class="bootbox-input bootbox-input-text form-control" autocomplete="nope" type="text" name="columnForEqName">';
    dialog_message += '</div>'
    dialog_message += '<label class="control-label">{{Utiliser un template :}}</label> ';
    dialog_message += '<select class="bootbox-input bootbox-input-select form-control" name="template" id="jmqttTemplateSelector">';
    dialog_message += '</select><br/>';
    dialog_message += '<div id="jmqttTemplateFromGroup" style="display:none;" class="form-group">';
    dialog_message += '<label class="control-label">{{Saisissez le Topic de base :}}</label> ';
    dialog_message += '<input class="bootbox-input bootbox-input-text form-control" autocomplete="nope" type="text" name="topic"><br/>';
    dialog_message += '<small id="passwordHelpBlock" class="form-text text-muted">Il est possible ici d\'utiliser du templating.<br/> Exemple mon_topic_\{\{le nom d\'une colonne dans mon fichier\}\}</small>';
    dialog_message += '</div>';
    dialog_message += '</form>'
    bootbox.confirm({
        title: "{{Importer des équipements}}",
        message: dialog_message,
        callback: function (result) {
            if (result) {

                let form = document.forms['ajaxForm'];

                if (!form['csvFile'].files[0]) {
                    $.fn.showAlert({message: "{{Fichier invalide !}}", level: 'warning'});
                    return false;
                }

                if (!form['parentObject'].value) {
                    $.fn.showAlert({message: "{{Objet invalide !}}", level: 'warning'});
                    return false;
                }

                if (!form['broker'].value) {
                    $.fn.showAlert({message: "{{Broker invalide !}}", level: 'warning'});
                    return false;
                }

                if (!form['columnForEqName'].value) {
                    $.fn.showAlert({message: "{{Nom de l'équipement requis}}", level: 'warning'});
                    return false;
                }

                if (form['template'].value != '' && form['topic'].value == '') {
                    $.fn.showAlert({
                        message: "{{Si vous souhaitez appliquer un template, le Topic de base ne peut pas être vide !}}",
                        level: 'warning'
                    });
                    return false;
                }

                const formData = new FormData(document.forms['ajaxForm']);

                formData.append('action', 'importCsv');

                $.ajax({
                    url: 'plugins/jMQTTImport/core/ajax/jMQTTImport.ajax.php',
                    type: 'POST',
                    data: formData,
                    async: false,
                    success: function () {
                        $.fn.showAlert({message: 'Import terminé', level: 'success'});
                    },
                    cache: false,
                    contentType: false,
                    processData: false
                });
            }
        }
    });

    $('#jmqttTemplateSelector').on('change', function () {
        if ($(this).val() == '') {
            $('#jmqttTemplateFromGroup').hide();
        } else {
            $('#jmqttTemplateFromGroup').show();
        }
    });

    // TODO don't use ajax call, instanciate the template list in the page and send to vars to js
    jmqtt.callPluginAjax({
        data: {
            action: "getTemplateList",
        },
        error: function (error) {
        },
        success: function (dataresult) {
            opts = '<option value="">{{Aucun}}</option>';
            for (var i in dataresult)
                opts += '<option value="' + dataresult[i][0] + '">' + dataresult[i][0] + '</option>';
            $('#jmqttTemplateSelector').html(opts);
        }
    });

    // $('#jmqttImportCsv').fileupload({
    //   dataType: 'json',
    //   replaceFileInput: false,
    //   done: function (e, data) {
    //     if (data.result.state != 'ok') {
    //       $.fn.showAlert({message: data.result.result, level: 'danger'});
    //     } else {
    //       $.fn.showAlert({message: 'Fichier importé avec succès', level: 'success'});
    //     }
    //   }
    // });
});

