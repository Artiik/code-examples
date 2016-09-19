var loaded = false;

$(document).ready(function(){

    $(document).ready(function() {
        $(window).keydown(function(event){
            if(event.keyCode == 13) {
                event.preventDefault();
                return false;
            }
        });
    });

    //�������� �� ������� ���������� ������ �������� � ��������
    $('.btn-add-product').click( function () { AddProduct(); return false; } );
    $('.btn-remove-product').click( function () { RemoveProduct(this); return false; } );

    //�������� �� �������� ����
    $('.product-count').change( function () { updatePriceAndCountRow($(this).parent()); calculateDelivery($('input[name="delivery"]:checked').val()); });
    $('.product-price').change( function () {
        if ($(this).val()!=$(this).data('originalPrice')) {
            $(this).addClass('warning-text');
        } else {
            $(this).removeClass('warning-text');
        }
        updatePriceAndCountRow($(this).parent());
        calculateDelivery($('input[name="delivery"]:checked').val());
    });

    // ��������� ������ ������ ������
    $("form#order2 input[name='phone']").keyup(function() {
        if ($("form#order2 input[name='phone']").val().length > 9)
            clearInputError($("form#order2 input[name='phone']"));
    });
    $("form#order2 input").keyup(function() {
        var formInputs = ['', 'firstname', 'middlename', 'lastname2', 'firstname2', 'middlename2', 'lastname'];
        if ($.inArray($(this).attr('name'),formInputs) && $(this).val().length > 0) {
            clearInputError(this);
        }
    });

    // ������� ������ ��������� � ����������� ����� �� ��� ����������.
    $('.trans_comp').on('change', 'input[name="transport_company"]', function() {
        $('input[name="transport_company_val"]').val($(this).val());
    });

    // ������������ ������ ��/���
    $('#customer_type input[name="customer"]').on("click", function(event){
        selectCustomerType($(this).val());
    });

    $('#customer_type input[name="customer"]:checked').click();

    // ������������ ������ ��������/���������/������������ ��������
    $('#delivery_type input[name="delivery"]').on("click", function(event){
        calculateDelivery($(this).val());
        selectDeliveryType($(this).val());
    });

    var delivery_type = $('#delivery_type input[name="delivery"]:checked').val();
    selectDeliveryType(delivery_type);

    //����� ������� ������
    $('#payment_type input[name="payment"]').on("click", function(event){

        var a = $(this).attr('disabled');
        if (!a) {
            selectPaymentType($(this).val());
        }
    });

    // ����� ������
    setProductAutoComplete();

    // ��������� �����
    $('form#order2').submit(function(){

        var errors = 0;

        if ($('input.product-select').val() == '') {
            setInputError($('input.product-select'));
            errors++;
        }

        //�����
        if ($("form#order2 input[name='phone']").val().length < 10) {
            setInputError($("form#order2 input[name='phone']"));
            errors++;
        }
        if (($("form#order2 input[name='firstname2']").val().length < 1 && $("form#order2 input[name='firstname2']").is(':visible') && ($("form#order2 input[name='lastname2']").val().length < 1 && $("form#order2 input[name='lastname2']").is(':visible')) && ($("form#order2 input[name='middlename2']").val().length < 1 && $("form#order2 input[name='middlename2']").is(':visible')))) {
            setInputError($("form#order2 input[name='firstname2']"));
            setInputError($("form#order2 input[name='lastname2']"));
            setInputError($("form#order2 input[name='middlename2']"));
            errors++;
        }

        //��� ������
        if (($("form#order2 input[name='firstname']").val().length < 1 && $("form#order2 input[name='firstname']").is(':visible') && ($("form#order2 input[name='lastname']").val().length < 1 && $("form#order2 input[name='lastname']").is(':visible')) && ($("form#order2 input[name='middlename']").val().length < 1 && $("form#order2 input[name='middlename']").is(':visible')))) {
            setInputError($("form#order2 input[name='firstname']"));
            setInputError($("form#order2 input[name='lastname']"));
            setInputError($("form#order2 input[name='middlename']"));
            errors++;
        }

        if (!errors) {
            submitOrder();
        }
        return false;
    });

    //�������� ���� ��� ������ ��������������
    calculateDelivery($('input[name="delivery"]:checked').val());

    preventNotNumberQuantity();

    selectPaymentType($('input[name="payment"]:checked').val());

    loaded = true;

    //�������� �� �������� ����
    $('.control-group').on('change', '.product-count', function () {
        updatePriceAndCountRow($(this).parent());
        calculateDelivery($('input[name="delivery"]:checked').val());
        if ($('#delivery_3').is(':checked')) {
            var loc = cit;
            if (loc.indexOf($.trim($('#delivery_city').val())) !== -1) {
                getDeliveryCost($.trim($('#delivery_city').val()));
            } else if ($.trim($('#delivery_city').val()) == '') {
                showError('�� ������ ����� ��������');
            } else {
                showError('�� ��������� ������ ����� ��������');
            }
        }
    });

});

// ������������� ���� ������ ��� �������� �����
function setInputError(element) {
    $(element).parents('.control-group').addClass('error');
}

// ���������� ���� ������ ��� �������� �����
function clearInputError(element) {
    $(element).parents('.control-group').removeClass('error');
}

// ���������� ���� ��� ������ ������
function updatePriceAndCountForElement(element) {
    var priceAndCount = $(element).siblings('.product-price-and-count');
    var item = $(element).data('selected');
    if (item != undefined) {
        priceAndCount.find('.product-price').data('originalPrice',item.price);
        priceAndCount.find('.product-price').val(item.price);
        priceAndCount.find('.product-price').change();
        updatePriceAndCountRow($(element).parent());
        priceAndCount.show();
        //hideShowRowControls();
    } else {
        priceAndCount.hide();
    }
}

// ���������� ����� ��������� ������ � ����������� �� ���� � ����������
function updatePriceAndCountRow(element) {
    var price = $(element).find('.product-price').val();
    var count = $(element).find('.product-count').val();
    if (count == 0) {
        $(element).find('.product-count').addClass('err-text');
    } else {
        $(element).find('.product-count').removeClass('err-text');
    }
    $(element).find('.product-amount').html(price * count);
}

function getProductsArray() {
    var products = new Array();
    $('.product-row').each(function(){
        if ($(this).find('.product-select').data('product_id') && $(this).find('.product-select').data('product_id') !== '0') {
            products.push({
                code: $(this).find('.product-select').data('code'),
                product_id: $(this).find('.product-select').data('product_id'),
                price: $(this).find('.product-price').val(),
                count: $(this).find('.product-count').val()
            });
        }
    });
    return products;
}

// ������� ���������� ��������� �������� � edost.ru
function getDeliveryCost(city, callback) {
    if (city!== '') {
        $('#transComp').removeClass('hide');
        $('.trans_comp').html('');
        $('#transCompResult').addClass('hide');
        $('#city-loader').removeClass('hide');
        $.ajax({
            type: 'POST',
            url: '/api/calc-delivery.php',
            dataType: 'json',
            cache: false,
            timeout: 4000,
            data: {
                'products': JSON.stringify(getProductsArray()),
                'city': city
            },
            success: function(data) {
                if (data.result == 1) {
//                    $('#transComp').removeClass('hide').find('input').removeClass('hidden');
                    $('#transCompResult').addClass('hide');
                    $('.trans_comp').html('');
                    $.each(data.data, function(i,val) {
                        if ($(this) !== undefined) {
//                          -----------------------
                            if(val.id !== undefined) {
                                $('.trans_comp').append(
                                    '<tr class="order-amount" id="company' + i + '">' +
                                    '<td>' +
                                    '<input type="radio" name="transport_company" class="required" value="' + val.id + '" />' +
                                    '<span class="company_name"> ' + val.company + ':' + '</span>' +
                                    '</td>' +
                                    '<td>' +
                                    '<strong>' + val.price + '</strong> �.' +
                                    '</td>' +
                                    '<td  class="delivery_time hide">' +
                                    '<span>(' + val.day + ')</span>' +
                                    '</td>' +
                                    '</tr>');

                                var transport_company_val = $("form#order2 input[name='transport_company_val']").val();
                                if (val.id == transport_company_val) {
                                    $(".trans_comp input[name='transport_company']").attr('checked', true);
                                }

                                if (val.day !== "&nbsp;") {
                                    $('#company' + i + ' .delivery_time').removeClass('hide');
                                } else {
                                    $('#company' + i + ' .delivery_time').addClass('hide');
                                }
                            } else {
                                $('.trans_comp').append(
                                    '<tr class="order-amount" id="company' + i + '">' +
                                    '<td>' +
                                    '<input type="radio" name="transport_company" class="required" value="' + val.entrance.id + '" />' +
                                    '<span class="company_name"> ' + val.entrance.company + ':' + '</span>' +
                                    '</td>' +
                                    '<td>' +
                                    '<strong>' + val.entrance.price + '</strong> �.' +
                                    '</td>' +
                                    '<td>' +
                                    '<span class="entrance">�� ��������</span>' +
                                    '</td>' +
                                    '<td  class="delivery_time hide">' +
                                    '<span>(' + val.entrance.day + ')</span>' +
                                    '</td>' +
                                    '</tr>');

                                var transport_company_val = $("form#order2 input[name='transport_company_val']").val();
                                if (val.entrance.id == transport_company_val) {
                                    $(".trans_comp input[name='transport_company']").attr('checked', true);
                                }

                                if (val.entrance.day !== "&nbsp;") {
                                    $('#company' + i + ' .delivery_time').removeClass('hide');
                                } else {
                                    $('#company' + i + ' .delivery_time').addClass('hide');
                                }
                            }

//                          -----------------------

                        } else { $('#company' + i).hide().find('input').addClass('hidden'); }
                    });
                } else if (data.result == 0) {
//                    $('#transComp').removeClass('hide').find('input').addClass('hidden');
                    $('.trans_comp').html('');
                    $('#transCompResult').removeClass('hide');
                    $('#transCompResult').text('��� �������� � �.' + city);
                } else {
                    $('.trans_comp').html('');
                    $('#transCompResult').removeClass('hide');
                    $('#transCompResult').text('������������ �������� �� �������');
                }
                $('#city-loader').addClass('hide');
                $('#transComp').addClass('calculated');
                callback ? callback(data.result) : null;
            },
            error: function (xhr, ajaxOptions, thrownError) {
                if(ajaxOptions ==="timeout") {
                    $('#city-loader').addClass('hide');
                    $('#transComp').removeClass('hide');
                    $('.trans_comp').html('');
                    $('#transCompResult').removeClass('hide');
                    $('#transCompResult').text('������� ������');
                }
            }
        });
    } else if ($('input[name="delivery"][value="2"]').is(':checked')) {
        $('#transComp').removeClass('hide');
        $('.trans_comp').html('');
        $('#transCompResult').removeClass('hide');
        $('#transCompResult').text('�� ������ ����� ��������');
    }

}

// �������� � ����� ����� ����� ���
function getFullName(name) {
    if (name == 'fname') {
        if ($("form#order2 input[name='firstname']").val() !== '' && $("form#order2 input[name='firstname']").is(':visible')) {
            return $("form#order2 input[name='firstname']").val();
        } else {
            return $("form#order2 input[name='firstname2']").val();
        }
    }
    if (name == 'lname') {
        if ($("form#order2 input[name='lastname']").val() !== '' && $("form#order2 input[name='lastname']").is(':visible')) {
            return $("form#order2 input[name='lastname']").val();
        } else {
            return $("form#order2 input[name='lastname2']").val();
        }
    }
    if (name == 'mname') {
        if ($("form#order2 input[name='middlename']").val() !== '' && $("form#order2 input[name='middlename']").is(':visible')) {
            return $("form#order2 input[name='middlename']").val();
        } else {
            return $("form#order2 input[name='middlename2']").val();
        }
    }
}

// �������� ������
function submitOrder() {
    $.ajax({
        type: 'POST',
        url: 'ajax/stores_order.php',
        dataType: 'json',
        cache: false,
        data: {
            'products': JSON.stringify(getProductsArray()),
            'fname': getFullName('fname'),
            'lname': getFullName('lname'),
            'mname': getFullName('mname'),
            'email': $("form#order2 input[name='email']").val(),
            'phone': $("form#order2 input[name='phone']").val(),
            'address': $('input[name="delivery"]:checked').val() == "0" ? $("form#order2 input[name='address']").val() : null,
            'delivery_city': $('input[name="delivery"]:checked').val() == "2" ? $("form#order2 #delivery_city").val() : null,
            'transport_company': $('input[name="delivery"]:checked').val() == "2" ? $("form#order2 input[name='transport_company']:checked").val() : null,
            'yur_payer': $("form#order2 input[name='yur_payer']").val(),
            'yur_address': $("form#order2 input[name='yur_address']").val(),
            'yur_inn': $("form#order2 input[name='yur_inn']").val(),
            'yur_kpp': $("form#order2 input[name='yur_kpp']").val(),
            'yur_rs': $("form#order2 input[name='yur_rs']").val(),
            'yur_bank': $("form#order2 input[name='yur_bank']").val(),
            'yur_ks': $("form#order2 input[name='yur_ks']").val(),
            'yur_bik': $("form#order2 input[name='yur_bik']").val(),
            'wmid': $("form#order2 input[name='wmid']").val(),
            'fiz_address': $("form#order2 input[name='fiz_address']").val(),
            'comments': $("form#order2 textarea[name='note']").val(),
            'customer_type': $('input[name="customer"]:checked').val(),
            'payment_type': $('input[name="payment"]:checked').val(),
            'site_id': $("form#order2 select#site").val(),
            'delivery_id': $('input[name="delivery"]:checked').val(),
            'amount':$('#sum-total').html()-$('#sum-delivery').html(),
            //'delivery_date': $.datepicker.formatDate('yy-mm-dd', $("#datepicker").datepicker('getDate')),
            'sms_notice_buyer': $('.sms_notice-buyer').is(':checked')?"1":"0",
            'sms_notice_managers': $('.sms_notice-managers').is(':checked')?"1":"0"
        },
        beforeSend : function(req) {
        },
        success: function(data) {
            try {
                if (data.status == 1) {
                    /* ����� ����� */
                    location.reload();
                } else {
                    $("#msgError").show('fast');
                }
            } catch(e) {
                $("#msgError").show('fast');
            }
        },
        error: function(xhr, ajaxOptions, thrownError) {
            $("#msgError").show('fast');
        },
        complete: function() {}
    });
}

function recalculateOrder()
{
    var sum = 0;
    $('.product-row').each(function(i, el) {
        sum += $(el).find('.product-count').val()*$(el).find('.product-price').val();
    });
    $('#sum-total').html(sum + parseInt($('#sum-delivery').html()));
}

function AddProduct()
{
    if (loaded == false)
        return;

    var to_clone = $('.product-row').first().clone();
    $('.product-row').last().after(to_clone);

    //hideShowRowControls();

    $('.btn-add-product').hide();
    $('.product-row').last().find('.btn-add-product').show();

    $('.product-row').last().find('.product-price-and-count').hide();
    $('.product-row').last().find('.product-count').val('1');

    //������� �� ������� ���������� ������ �������� � ��������
    $('.btn-add-product').click( function () { AddProduct(); return false; } );
    $('.btn-remove-product').click( function () { RemoveProduct(this); return false; } );

    setProductAutoComplete();
    $('.product-select').last().val('');

    //�������� �� �������� ����
    $('.product-price').change( function () {
        if ($(this).val()!=$(this).data('originalPrice')) {
            $(this).addClass('warning-text');
        } else {
            $(this).removeClass('warning-text');
        }
        updatePriceAndCountRow($(this).parent());
        calculateDelivery($('input[name="delivery"]:checked').val());
    });

    $('.product-row').last().find('.product-select').focus();

    preventNotNumberQuantity();
}

//prevent non digits input into quantity field
function preventNotNumberQuantity()
{
    $('.product-count').keydown(function(event) {
        // Allow: backspace, delete, tab, escape, enter and .
        if ( $.inArray(event.keyCode,[46,8,9,27,13,190]) !== -1 ||
                // Allow: Ctrl+A
            (event.keyCode == 65 && event.ctrlKey === true) ||
                // Allow: home, end, left, right
            (event.keyCode >= 35 && event.keyCode <= 39)) {
            // let it happen, don't do anything
            return;
        }
        else {
            // Ensure that it is a number and stop the keypress
            if (event.shiftKey || (event.keyCode < 48 || event.keyCode > 57) && (event.keyCode < 96 || event.keyCode > 105 )) {
                event.preventDefault();
            }
        }
    });
}

function selectCustomerType(type)
{
    if (type == 1) {
        if ($('input[name="delivery"]:checked').val() !== "2") {
            $('input[name="payment"]').attr('disabled',false);
            $('input[name="payment"]').parent().css("color", "#666666");
            selectPaymentType($('input[name="payment"]:checked').val());
        } else {
            $('input[name="payment"]').attr('disabled',false);
            $('input[name="payment"]').parent().css("color", "#666666");
            $('input[name="payment"][value="1"]').attr('disabled',true);
            $('input[name="payment"][value="1"]').parent().css("color", "#C8C7C7");
            $('input[name="payment"][value="3"]').attr('disabled',true);
            $('input[name="payment"][value="3"]').parent().css("color", "#C8C7C7");
            selectPaymentType($('input[name="payment"]:checked').val());
        }
    }
    if (type == 2) {
        $('input[name="payment"]').attr('disabled',true);
        $('input[name="payment"]').parent().css("color", "#C8C7C7");
        $('input[name="payment"][value="2"]').attr('disabled',false);
        $('input[name="payment"][value="2"]').parent().css("color", "#666666");
        $('input[name="payment"][value="2"]').click();
    }
}

function selectDeliveryType(type)
{
    switch(type) {
        case "0":
            $('#to_city').addClass('hide');
            $('#address').removeClass('hide');
            $('#transComp').addClass('hide');
            if ($('input[name="customer"][value="1"]').is(':checked')) {
                $('input[name="payment"]').attr('disabled',false);
                $('input[name="payment"]').parent().css("color", "#666666");
                selectPaymentType($('input[name="payment"]:checked').val());
            }
            break;
        case "1":
            $('#to_city').addClass('hide');
            $('#address').addClass('hide');
            $('#transComp').addClass('hide');
            if ($('input[name="customer"][value="1"]').is(':checked')) {
                $('input[name="payment"]').attr('disabled',false);
                $('input[name="payment"]').parent().css("color", "#666666");
                selectPaymentType($('input[name="payment"]:checked').val());
            }
            $('#sum-delivery-hint').text('');
            break;
        case "2":
            $('#to_city').removeClass('hide');
            $('#address').addClass('hide');
            if ($('#transComp').hasClass('calculated') || $('#transCompResult').text() == '�� ��������� ������ ����� ��������') {
                $('#transComp').removeClass('hide');
            }
            if ($.trim($("#delivery_city").val()) == '') {
                $('#transComp').removeClass('hide');
                $('#transCompResult').removeClass('hide');
                $('#transCompResult').text('�� ������ ����� ��������');
            }
            $('input[name="payment"]').attr('disabled',true);
            $('input[name="payment"]').parent().css("color", "#C8C7C7");
            $('input[name="payment"][value="2"]').attr('disabled',false);
            $('input[name="payment"][value="2"]').parent().css("color", "#666666");

            if ($('input[name="customer"][value="1"]').is(':checked')) {
                $('input[name="payment"][value="4"]').attr('disabled',false);
                $('input[name="payment"][value="4"]').parent().css("color", "#666666");
            }

            $('input[name="payment"][value="2"]').click();

            $('#sum-delivery-hint').text('');
            break;
    }

}

function selectPaymentType(type)
{
    var customer_type = $('input[name="customer"]:checked').val();

    var a = "#payment-details-" + customer_type + "-" + type;
    if ($("#payment-details-" + customer_type + "-" + type).length  > 0 && !$("#payment-details-" + customer_type + "-" + type).is(":visible")) {
        $('#heading-payment-details').show();
        $('.payment-details').hide();
        $("#payment-details-" + customer_type + "-" + type).toggle('fast');
    } else if ($("#payment-details-" + customer_type + "-" + type).length  == 0) {
        $('#heading-payment-details').hide();
        $('.payment-details').hide();
    }

    if (type == 2 || $('.btn-delivery-type.active').data('value') == 2 && type == 2) {
        $('#block-email').show();
    } else {
        $('#block-email').hide();
    }
    if (customer_type == 1 && type == 2 || $('.btn-delivery-type.active').data('value') == 2 && customer_type == 1 && type !== 4) {
        $('#block-reciever').hide();
    } else {
        $('#block-reciever').show();
    }

}

function RemoveProduct(el)
{
    var row = $(el).parent().parent().parent();
    if (loaded == false)
        return;

    if ($('.product-row:visible').length == 1) {
        row.find('.product-select').val('');
        row.find('.product-select').data('code','0');
        row.find('.product-select').data('product_id','0');
        row.find('.product-price-and-count').hide();
        row.find('.product-price').val('0');
        row.find('.product-count').data('count','0');
        $('#transComp').addClass('hide');
    } else {
        row.remove();
        $('.btn-add-product').hide();
        $('.product-row:visible').last().find('.btn-add-product').show();
        if ($('input[name="delivery"][value="2"]').is(':checked') && $('#to_city').is(':visible')) {
            var loc = cit;
            if (loc.indexOf($.trim($('#delivery_city').val())) !== -1) {
                getDeliveryCost($.trim($('#delivery_city').val()));
            } else if ($.trim($('#delivery_city').val()) == '') {
                showError('�� ������ ����� ��������');
            } else {
                showError('�� ��������� ������ ����� ��������');
            }
        }
    }
    calculateDelivery($('input[name="delivery"]:checked').val());
}

function showError(msg) {
    $('#transComp').removeClass('hide').find('input').addClass('hidden');
    $('.trans_comp').html('');
    $('#transCompResult').removeClass('hide');
    $('#transCompResult').text(msg);
    same_city = null;
}

function setProductAutoComplete()
{
    $(".product-select").each(function(i, el) {
        el = $(el);
        el.autocomplete({
            minLength: 0,
            source: products,
            change: function(event, ui) {
            },
            select: function( event, ui ) {
                $(this).data('selected', {label: ui.item.value, value: ui.item.value, price: ui.item.price, code: ui.item.code, product_id: ui.item.product_id});
                $(this).data('code', ui.item.code);
                $(this).data('product_id', ui.item.product_id);
                updatePriceAndCountForElement($(this));
                calculateDelivery($('input[name="delivery"]:checked').val());

                if ($('#delivery_3').is(':checked') && $('#to_city').is(':visible')) {
                    var loc = cit;
                    if (loc.indexOf($.trim($('#delivery_city').val())) !== -1) {
                        getDeliveryCost($.trim($('#delivery_city').val()));
                    } else if ($.trim($('#delivery_city').val()) == '') {
                        showError('�� ������ ����� ��������');
                    } else {
                        showError('�� ��������� ������ ����� ��������');
                    }
                }

            },
            source: function( request, response ) {
                var matcher = new RegExp( $.ui.autocomplete.escapeRegex(request.term), "i" );
                var filtered = jQuery.map(products, function(el) {
                    var text = el.label;
                    if ( text && ( !request.term || matcher.test(text) ) )
                    {
                        return {
                            label: text.replace( new RegExp("(?![^&;]+;)(?!<[^<>]*)(" + $.ui.autocomplete.escapeRegex(request.term) + ")(?![^<>]*>)(?![^&;]+;)", "gi"), "<strong>$1</strong>" ),
                            value: text,
                            price: el.price,
                            code: el.code,
                            product_id: el.product_id
                        };
                    }
                });
                response(filtered);
                if (filtered.length) {
                    $(this.element).parents('.control-group').removeClass('error');
                } else {
                    $(this.element).parents('.control-group').addClass('error');
                }
                if (request.term.toString() != $(this.element).data('text')) {
                    $(this.element).removeData('selected');
                    $(this.element).removeData('code');
                    $(this.element).removeData('product_id');
                    updatePriceAndCountForElement($(this.element));
                }
                $(this.element).data('text', request.term);
            }
        }).bind('focus', function(){
            $(this).val($(this).data('text'));
            $(this).autocomplete("search");
        }).focusout(function(){
            var selected = $(this).data('selected');
            if (selected) {
                $(this).val(selected.label);
            } else {
                RemoveProduct($(this).parent().find('.btn-remove-product'));
                //$(this).val('');
            }
            updatePriceAndCountForElement($(this));
        }).data( "ui-autocomplete" )._renderItem = function( ul, item ) {
            return $( "<li>" ).append( "<a>" + item.label + "<small>" + item.price + " ���.</small></a>" ).appendTo( ul );
        };
    });

    // ���������� ��� ���������� �����
    $('.input-manifested').mouseenter(function(){ $(this).removeClass('input-frameless'); })
        .mouseleave(function(){ if (!$(this).is(":focus")) $(this).addClass('input-frameless'); })
        .focusin(function(){ $(this).removeClass('input-frameless'); })
        .focusout(function(){ $(this).addClass('input-frameless'); });
}

function calculateDelivery(type)
{
    if (type == undefined || type == "0" || type == "2") {
        var sum = 0;
        var extraSum = 0;
        $('.product-row').each(function(i, el) {
            sum += $(el).find('.product-count').val()*$(el).find('.product-price').val();
        });
        if (sum < 10000) {
            extraSum = 400;
        }
        $('#sum-delivery').html(extraSum);
        recalculateOrder();
    } else {
        $('#sum-delivery').html('0');
        recalculateOrder();
    }
}