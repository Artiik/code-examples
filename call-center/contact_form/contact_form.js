var bind_inputs = {};
var $contact_form;
var $contact_data_form;
var managerTags = [];

var form_action_call_validate = {
    rules: {
        schedule_date: {dateFormat: true, required: true},
        call_order_token: {required: false}
    },
    messages: {
        schedule_date: {
            dateFormat: "Дата введена не верно",
            required: "Заполните дату"
        }
    },
    submitHandler: function (form) {
        var contact_id = $('#contact-id').val();
        if (!contact_id) {
            showNotification($contact_form, 'Контакт еще не создан', 'top', 'danger', 'bar');
            return false;
        }
        $('<input />').attr('type', 'hidden')
            .attr('name', "id")
            .attr('value', $('#contact-id').val())
            .appendTo(form);
        $('<input />').attr('type', 'hidden')
            .attr('name', "_csrf")
            .attr('value', _csrf)
            .appendTo(form);
        var data = $(form).serialize();
        $.post($(form).attr('action'), data, function (response) {
            $(form).find('input[name="id"]').remove();
            $(form).find('input[name="_csrf"]').remove();
            var result = $.parseJSON(response);
            if (result.status === 200) {
                if ($(form).hasClass('ring-round')) {
                    $contact_form.modal('hide');
                    tagContactsdataTable.columns(0).search($('#contacts_list').val()).draw();
                    contactsModaldataTable.columns().search('').draw();
                    $contact_form.find('.history_content').append("<div class='ring_round' id='ring_round_call_id-" + result.data.id + "'>" + result.data.system_date + " - " + result.data.history + "</div>");
                } else {
                    resetActionForm(form);
                    if ($(form).find('.google-cal-show').is(':checked')) {
                        var event = createGEventData('action_call', $(form).find('input[name="schedule_date"]').val());
                        createGCalEvent(event);
                    }
                    $contact_form.find('.history_content').append("<div class='scheduled_call' id='shedule_call_id-" + result.data.id + "'>" + result.data.system_date + " - " + result.data.history + "</div>");
                }
            }
        });
    }
};

var form_action_email_validate = {
    rules: {
        schedule_date: {dateFormat: true, required: true}
    },
    messages: {
        schedule_date: {
            dateFormat: "Дата введена не верно",
            required: "Заполните дату"
        }
    },
    submitHandler: function (form) {
        var contact_id = $('#contact-id').val();
        if (!contact_id) {
            showNotification($contact_form, 'Контакт еще не создан', 'top', 'danger', 'bar');
            return false;
        }
        $('<input />').attr('type', 'hidden')
            .attr('name', "id")
            .attr('value', $('#contact-id').val())
            .appendTo(form);
        $('<input />').attr('type', 'hidden')
            .attr('name', "_csrf")
            .attr('value', _csrf)
            .appendTo(form);
        var data = $(form).serialize();
        $.post($(form).attr('action'), data, function (response) {
            $(form).find('input[name="id"]').remove();
            $(form).find('input[name="_csrf"]').remove();
            var result = $.parseJSON(response);
            if (result.status === 200) {
                resetActionForm(form);
                if ($(form).find('.google-cal-show').is(':checked')) {
                    var event = createGEventData('action_email', $(form).find('input[name="schedule_date"]').val());
                    createGCalEvent(event);
                }
                $contact_form.find('.history_content').append("<div class='scheduled_email' id='shedule_email_id-" + result.data.id + "'>" + result.data.system_date + " - " + result.data.history + "</div>");
            }
        });
    }
};

$(function() {
    $('#action_call #form_action_call').validate(form_action_call_validate);
    // $('#action_email #form_action_email_now').validate(action_email_now_form_validate);
    $('#action_email #form_action_email').validate(form_action_email_validate);

    $contact_form = $('#modalAddContact');
    $contact_data_form = $contact_form.find('.contact-data');

    $('input[type=text], input[type=email]', $contact_data_form).on('blur', function () {
        $(this).data('value', $(this).val());
        checkChanges($(this).attr('name'), $(this).data('value'), $contact_form);
    });

    $('#contact_tags', $contact_data_form).on('beforeItemRemove', function (event) {
        var tag = event.item;
        switch (userRole) {
            case 'operator':
                event.cancel = true;
                break;
            case 'manager':
                if (managerTags.indexOf(tag.text) === -1) {
                    event.cancel = true;
                }
                break;
        }
    });
    $('#contact_tags', $contact_data_form).on('itemAdded, itemRemoved', function () {
        $(this).data('value', $(this).val());
        bind_inputs['tags_str'] = $(this).val();
        editContact($contact_form);
    });

    //add new comment for contact
    $('#add-comment').on('click', function (e) {
        var id = $contact_form.find('#contact-id').val();
        addComment(id, $contact_form);
    });

    $('#contact-action').on('change', function (e) {
        var action = $(this).val(),
            $forms = $('#contact-actions').find('form');
        $.each($forms, function(i, form) {
            resetActionForm(form);
        });
        changeActionsForm(action);
    });

    $('input[name="schedule_date"]').on('blur', function (e) {
        var $form = $(this).closest('form');
        if ($form.valid()) {
            $(this).removeClass('error');
            $(this).next('.error').remove();
        }
    });

    $('.action_send_now').on('change', function() {
        var $form = $(this).closest('form'),
            opts = $form.attr('id') == 'form_action_call' ? form_action_call_validate : form_action_email_validate;
        // $(this).parents('.panel').
        if ($(this).is(':checked')) {
            changeActionSendNow($form, 'enable', opts);
        } else {
            changeActionSendNow($form, 'disable', opts);
        }
    });

    var date = new Date();
    var today = new Date(date.getFullYear(), date.getMonth(), date.getDate());

    $('.datepicker').datetimepicker({
        locale: 'ru'
        //minDate: today
    });

    $('.google-cal-show').on('change', function() {
        if ($(this).is(':checked')) {
            processGApiAuth();
        }
    });

    var $tagsInput = $('#contact_tags');
    $tagsInput.tagsinput({
        itemValue: 'text',
        itemText: 'text',
        tagClass: function(item) {
            if (userRole == 'admin') {
                return 'label label-success';
            } else if (managerTags.indexOf(item.text) === -1) {
                return 'label';
            } else {
                return 'label label-success';
            }
        }
    });
});

function changeActionSendNow($form, action, opts) {
    var state = action == 'enable';
    $form.find('input[name="schedule_date"]').val('').attr('disabled', state);
    $form.find('.google-cal-show').attr('checked', false).attr('disabled', state);
    if (state) {
        $form.find('.attitude').show();
    } else {
        $form.find('.attitude').hide();
    }
    changeValidationRequired(opts, state);
}

function resetActionForm(form) {
    $(form).trigger('reset');
    $(form).find('input:not(:radio)').val('').attr('disabled', false);
    $(form).find('textarea').val('').attr('disabled', false);
    $(form).find('input[type="checkbox"]').prop('checked', false);
    if ($(form).attr('id') == 'form_action_call') {
        $(form).find('.attitude').hide();
    }
}

function initCallNow(phone) {
    $('.contact-actions .cs-options li[data-value="call"]').click();
    $('#action_send_now_phone').click();
    $.post('asterisk/send-incoming-call', {phone: phone, _csrf: _csrf}, function (response) {
        var result = $.parseJSON(response);
        if (result.status === 200) {
            $('#form_action_call .call_order_token').val(result.data.call_order_token);
        } else {
            console.log('incoming call not done');
        }
    });
}

function initRingRound($tagForm) {
    var tagDescrVal = $tagForm.find('#tag_description').val(),
        tagScriptVal = $tagForm.find('#tag_script').val(),
        $actionCallForm = $('#form_action_call'),
        ringRoundActionUrl = '/contacts/ring-round';

    $contact_form.find('.script_content').html(tagScriptVal);
    $contact_form.find('.contact-history').find('.script-tab').show().find('a').trigger('click');
    $contact_form.find('#action_tag_description').val(tagDescrVal).attr('disabled', true).parent().show();
    $actionCallForm.addClass('ring-round');
    $actionCallForm.attr('action', ringRoundActionUrl);
    $actionCallForm.find('.action-title').hide();
    $actionCallForm.find('input[name="schedule_date"]').parents('.form-group').hide();
    $actionCallForm.find('#google_cal_show_call').parents('.form-group').hide();
}

function changeValidationRequired(options, state) {
    options.rules.schedule_date.required = !state;
    if (options.rules.call_order_token !== undefined) {
        options.rules.call_order_token.required = state;
    }
}


function openContactForm(id) {
    buildContactForm(id, $contact_form, function () {
        bindLiveChange($contact_data_form);
    });
    $contact_form.modal({backdrop: false});
}

function openNewContactForm() {
    clearContactForm($contact_form);
    $contact_form.modal({});
    bindLiveChange($contact_data_form);
}

function openNewContactFormWithPhone(phone) {
    clearContactForm($contact_form);
    $contact_form.modal({});
    bindLiveChange($contact_data_form);
    $contact_data_form.find('#contact_phones').val(phone);
}

function clearContactForm($form) {
    $form.find('#contact-id').val('');
    $form.find('.contact-title').text('Новый контакт');
    $form.find('.history_content').empty();
    $form.find('.script_content').empty();
    $form.find('input').val('');
    $form.find('#contact_manager_name').text('');
    $form.find('.contact-manager-name-cont').hide();
    hideNotifications($form);
}

function buildContactForm(id, $form, callback) {
    $form.find('.contact-history .history-header li').removeClass('active');
    $form.find('.contact-history').find('.script-tab').hide();
    $form.find('.contact-history').find('.history-tab, #history').addClass('active');
    hideNotifications($form);
    getHistory(id, $form);
    $form.find('#contact-id').val(id);
    var $actionForms = $form.find('#contact-actions').find('form');
    $.each($actionForms, function(i, form) {
        resetActionForm(form);
    });
    $.getJSON('/contacts/view', {id: id}, function (response) {
        if (response.status === 200) {
            var data = response.data;

            fillContactData(data, $form);

            manageContactFormPermissions(userRole);

            callback();
        }
    });
}

function manageContactFormPermissions(userRole) {
    var inputsToHide;
    switch (userRole) {
        case 'operator':
            inputsToHide = [
                $('#contact_surname'),
                $('#contact_name'),
                $('#contact_middle_name'),
                $('#contact_phones')
            ];
            $.each(inputsToHide, function(index, input) {
                $(input).val('');
                $(input).on('focus', function() {
                    $(input).val($(input).data('value'));
                });
            });
            break;
    }
}

function fillContactData(data, $form) {
    $.each(data, function(key, value) {
        switch (key) {
            case 'int_id':
                $form.find('.contact-title').text('Контакт №' + value);
                break;
            case 'is_deleted':
                value == 1 ? $('.contact-deleted').show() : $('.contact-deleted').hide();
                break;
            case 'manager_name':
                $('#contact_manager_name').text(value);
                $('.contact-manager-name-cont').show();
                break;
            case 'tags':
                var $tagsInput = $('#contact_tags'),
                    tags_str = [];
                if (data.manager_tags) {
                    managerTags = data.manager_tags;
                }
                // var tags = [];
                $tagsInput.tagsinput('removeAll');
                $.each(value, function(tag_key, tag_value) {
                    $tagsInput.tagsinput('add', { id: tag_value.id, text: tag_value.name });
                    tags_str.push(tag_value.name);
                    // tags.push({ id: tag_value.id, text: tag_value.name });
                });
                $tagsInput.attr('data-value', tags_str.join(','));
                // $tagsInput.tagsinput('add', tags);
                break;
            default:
                if (value) {
                    $form.find('#contact_' + key).val(value).attr('data-value', value);
                } else {
                    $form.find('#contact_' + key).val('').attr('data-value', '');
                }
        }
    });
}


function checkChanges(name, value, $form) {
    if (bind_inputs[name] !== value) {
        bind_inputs[name] = value;
        editContact($form, name, value);
    }
}

function editContact($form, name, value) {
    var data = {};
    $.each(bind_inputs, function (key, value) {
        data[key] = value;
    });
    data['_csrf'] = _csrf;
    if (bind_inputs['name'] && bind_inputs['surname'] && bind_inputs['phones']) {
        $.post('/contacts/edit', data, function (response) {
            $form.find('label.error').remove();
            $form.find('.error').removeClass('error');
            var result = $.parseJSON(response);
            if (result.status == 200) {
                bind_inputs['id'] = result.data.id;
                if (name && value) {
                    bind_inputs[name] = value;
                }
                $form.find('#contact-id').val(result.data.id);
                if (typeof dataTable !== 'undefined') { dataTable.draw(false); }
                if (typeof tagContactsdataTable !== 'undefined') { tagContactsdataTable.columns(0).search($('#contacts_list').val()).draw(); }
                if (typeof contactsModaldataTable !== 'undefined') { contactsModaldataTable.columns().search('').draw(); }
                if (!data['id']) {
                    getHistory(result.data.id, $form);
                }
            }
            if (result.status == 415) {
                $.each(result.errors, function (name, errors) {
                    addError($form, name, errors);
                });

            }
            if (result.status == 412) {
                //alert(result.errors);
                showNotification('#modalAddContact', result.errors, 'top', 'danger', 'bar');
            }
            if (result.status == 403) {
                showNotification('#modalAddContact', result.errors, 'top', 'danger', 'bar', 5000);
            }
        });
    }

}

function changeActionsForm(action) {
    switch (action) {
        case "call":
            $contact_form.find('#contact-actions .contact-action').hide();
            $contact_form.find('#contact-actions #action_call').show();
            $contact_form.find('#contact-actions #action_schedule_date').show();
            break;
        case "email":
            $contact_form.find('#contact-actions .contact-action').hide();
            $contact_form.find('#contact-actions #action_email').show();
            $contact_form.find('#contact-actions #action_schedule_date').show();
            break;
        case "0":
            $contact_form.find('#contact-actions .contact-action').hide();
            $contact_form.find('#contact-actions #action_schedule_date').hide();
            break;
    }
}

function bindLiveChange($form) {
    $.each($('input[type=text],input[type=email]', $form), function (i, input) {
        var name = $(input).attr('name');
        if (name) {
            bind_inputs[name] = $(input).data('value') + '';
        }
    });
    bind_inputs['id'] = $('#contact-id').val();
}


function getHistory(id, $form) {
    $form.find('.history_loader').show();
    var $content = $form.find('.history_content');
    $content.empty();
    $.getJSON('/contacts/history', {id: id}, function (response) {
        if (response.status === 200) {
            $.each(response.data, function (i, val) {
                var $item = $('<div/>');
                switch (val.type) {
                    case "scheduled_call":
                        $item.addClass('scheduled_call');
                        break;
                    case "scheduled_email":
                        $item.addClass('scheduled_email');
                        break;
                    case 'ring_round':
                        $item.addClass('ring_round');
                        break;
                }
                $item.append(val.datetime + " - " + val.text);
                $content.append($item);
            });
            $form.find('.history_loader').hide();
        }
    });
}



function addComment(id, $form) {
    if (!id) {
        var message = 'Контакт еще не добавлен';
        showNotification('#modalAddContact', message, 'top', 'danger', 'bar');
        //showNotification('body',message, 'top', 'danger', 'bar');
        return false;
    }
    var $contact_comment = $form.find('#contact-comment');
    var comment_text = $contact_comment.val();
    var data = {
        id: id,
        _csrf: _csrf,
        comment: comment_text
    };
    $.post('/contacts/addcomment', data, function (response) {
        var result = $.parseJSON(response);
        if (result.status === 200) {
            $form.find('.history_content').append("<div>" + result.data.datetime + " - " + result.data.text + "</div>");
            $contact_comment.val('');
        }
    });
}

function addError($form, name, errors) {
    var $field = $form.find('[name="' + name + '"]');
    $.each(errors, function (i, error) {
        $field.addClass('error');
        $field.after("<label class='error'>" + error + "</lable>");
    });
}