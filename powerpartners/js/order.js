var loaded = false;

var orders_on_map = false;

$(document).ready(function(){

	$(document).ready(function() {
		$(window).keydown(function(event){
			if(event.keyCode == 13) {
				event.preventDefault();
				return false;
			}
		});
	});

    var delivery_type = $('#delivery_type a.active').data('value');
    selectDeliveryType(delivery_type);
	$('.btn-group').button();
  
	//�������� �� ������� ���������� ������ �������� � ��������
	$('.btn-add-product').click( function () { AddProduct(); return false; } );
	$('.btn-remove-product').click( function () { RemoveProduct(this); return false; } );

    if ($('#is_new_order').val() == 1) {
        $('#order-form .product-price-and-count').attr('style', 'display:none !important;');
    }
	
	//�������� �� �������� ����
	$('.product-count').change( function () { updatePriceAndCountRow($(this).parent()); calculateDelivery($('#delivery_type .active').data('value')); });
	$('.product-price').change( function () { 
		if ($(this).val()!=$(this).data('originalPrice')) {
			$(this).addClass('warning-text');
		} else {
			$(this).removeClass('warning-text');		
		}
		updatePriceAndCountRow($(this).parent()); 
		calculateDelivery($('#delivery_type .active').data('value'));
	});

	// ��������� datepicker-�
	$("#datepicker").datepicker({
		defaultDate: $("#datepicker").data('date') != '' ? new Date($("#datepicker").data('date')) : '',
		onSelect: function(date) { 
			$("#delivery_date").text(date);
            if (!tabs) {
                mapOrders($.datepicker.formatDate('dd.mm.yy', $("#datepicker").datepicker('getDate')),$('#order_id').val());
                //���� ����������� �����, �� ��������� ���
                if ($('#order_id').val() > 0)
                    showOrderOnMap();
            } else {
                orders_on_map = false;
            }
		}
	});
	if ($("#datepicker").data('date') == '') {
		$("#datepicker").val('');
		$("#datepicker").find(".ui-datepicker-current-day").removeClass("ui-datepicker-current-day").removeClass("ui-datepicker-days-cell-over").find(".ui-state-active").removeClass("ui-state-active");
	}

	// ��������� ������ ������ ������
	$("form#order input[name='phone']").keyup(function() {
		if ($("form#order input[name='phone']").val().length > 9)
			clearInputError($("form#order input[name='phone']"));
	});
	$("form#order input").keyup(function() {
		var formInputs = ['lastname', 'firstname', 'middlename', 'fiz_address', 'wmid', 'yur_payer', 'yur_address', 'yur_inn', 'yur_kpp', 'yur_rs', 'yur_bank', 'yur_ks', 'yur_bik', 'lastname2', 'firstname2', 'middlename2', 'address'];
		if ($.inArray($(this).attr('name'),formInputs) && $(this).val().length > 0) {
			clearInputError(this);
		}
	});

    // ������� ������ ��������� � ����������� ����� �� ��� ����������.
    $('.trans_comp').on('change', 'input[name="transport_company"]', function() {
        if ($("form#order input[name='transport_company']:checked").val() !== undefined)
            clearInputError($("form#order input[name='transport_company']"));
        $('input[name="transport_company_val"]').val($(this).val());
    });

	// ������� �� ����� ������ �� ����������� ����
	ymaps.ready(function() {
        if (!tabs) {
            var datepicker_date = $.datepicker.formatDate('dd.mm.yy', $("#datepicker").datepicker('getDate'));
            if (datepicker_date !== '') {
                mapOrders(datepicker_date, $('#order_id').val());
            } /* else {
                var current_date = new Date(),
                    date_string = (current_date.getDate()<10 ? '0' + current_date.getDate() : current_date.getDate()) + '.' + ((current_date.getMonth() + 1)<10 ? '0' + (current_date.getMonth() + 1) : (current_date.getMonth() + 1)) + '.' + current_date.getFullYear();

                mapOrders(date_string);
            } */

            //���� ����������� �����, �� ��������� ���
            if ($('#order_id').val() > 0)
                showOrderOnMap();
        }
	});

	// ����� ������ � ������� �� �����
	$('button#find').on("click", function(event){
        if (!tabs) {
            showOrderOnMap();
            setTimeout(function() {
                if($('#address-loader').is(':visible')) {
                    $('#find').prop('disabled', false);
                    $('#address-loader').hide();
                    $('#address_error').removeClass('hide');
                    $('#address_error').text('����� �� ������');
                }
            }, 4000);
        } else {
            $('#tabs-panel li>a[href="#map"]').click();
        }
	});

	// ������������ ������ ��/���
	$('#customer_type .btn').on("click", function(event){
		selectCustomerType($(this).data('value'));
	});

    $('#customer_type .active').click();

	// ������������ ������ ��������/���������/������������ ��������
	$('#delivery_type .btn').on("click", function(event){
        calculateDelivery($(this).data('value'));
		selectDeliveryType($(this).data('value'));
    });
	
	//����� ������� ������
	$('#payment_type .btn').on("click", function(event){

        var a = $(this).attr('disabled');
        if (!a) {
            $(this).closest('.btn-group').find('.btn').removeClass('active');
            $(this).addClass('active');
            selectPaymentType($(this).data('value'));
        }
	});


  // ����� �����
  ///////////////////////////////////////////////////////////////////////////////////////
  setSiteAutoComplete();
	
  // ����� ������
	setProductAutoComplete();

  // ��������� ������ ������
  $('#btnCancel').on("click", function(event){
	enableForm($('form#order'));
	return false;
  });

  // ��������� �����
  $('form#order').submit(function(){

	var errors = 0;
	
	/*if ($('input.product-select').data('selected') == undefined) {
	  setInputError($('input.product-select'));
	  errors++;
	}*/
	//�����
	if ($("form#order input[name='phone']").val().length < 10) {
	  setInputError($("form#order input[name='phone']"));
	  errors++;
	}
	if ($("form#order input[name='address']").val().length < 1 && $("form#order input[name='address']").is(':visible')) {
		setInputError($("form#order input[name='address']"));
		errors++;
	}
	if ($("form#order input[name='firstname2']").val().length < 1 && $("form#order input[name='firstname2']").is(':visible')) {
		setInputError($("form#order input[name='firstname2']"));
		errors++;
	}
	if ($("form#order input[name='lastname2']").val().length < 1 && $("form#order input[name='lastname2']").is(':visible')) {
		setInputError($("form#order input[name='lastname2']"));
		errors++;
	}
	if ($("form#order input[name='middlename2']").val().length < 1 && $("form#order input[name='middlename2']").is(':visible')) {
		setInputError($("form#order input[name='middlename2']"));
		errors++;
	}
	//��� ������
	if ($("form#order input[name='firstname']").val().length < 1 && $("form#order input[name='firstname']").is(':visible')) {
		setInputError($("form#order input[name='firstname']"));
		errors++;
	}
	if ($("form#order input[name='lastname']").val().length < 1 && $("form#order input[name='lastname']").is(':visible')) {
		setInputError($("form#order input[name='lastname']"));
		errors++;
	}
	if ($("form#order input[name='middlename']").val().length < 1 && $("form#order input[name='middlename']").is(':visible')) {
		setInputError($("form#order input[name='middlename']"));
		errors++;
	}
	if ($("form#order input[name='fiz_address']").val().length < 1 && $("form#order input[name='fiz_address']").is(':visible')) {
		setInputError($("form#order input[name='fiz_address']"));
		errors++;
	}		
	if ($("form#order input[name='fiz_address']").val().length < 1 && $("form#order input[name='fiz_address']").is(':visible')) {
		setInputError($("form#order input[name='fiz_address']"));
		errors++;
	}
	//��� �������
	if ($("form#order input[name='wmid']").val().length < 1 && $("form#order input[name='wmid']").is(':visible')) {
		setInputError($("form#order input[name='wmid']"));
		errors++;
	}
	//�� ������
	if ($("form#order input[name='yur_payer']").val().length < 1 && $("form#order input[name='yur_payer']").is(':visible')) {
		setInputError($("form#order input[name='yur_payer']"));
		errors++;
	}
	if ($("form#order input[name='yur_address']").val().length < 1 && $("form#order input[name='yur_address']").is(':visible')) {
		setInputError($("form#order input[name='yur_address']"));
		errors++;
	}
	if ($("form#order input[name='yur_inn']").val().length < 1 && $("form#order input[name='yur_inn']").is(':visible')) {
		setInputError($("form#order input[name='yur_inn']"));
		errors++;
	}
	if ($("form#order input[name='yur_kpp']").val().length < 1 && $("form#order input[name='yur_kpp']").is(':visible')) {
		setInputError($("form#order input[name='yur_kpp']"));
		errors++;
	}		
	if ($("form#order input[name='yur_rs']").val().length < 1 && $("form#order input[name='yur_rs']").is(':visible')) {
		setInputError($("form#order input[name='yur_rs']"));
		errors++;
	}	
	if ($("form#order input[name='yur_bank']").val().length < 1 && $("form#order input[name='yur_bank']").is(':visible')) {
		setInputError($("form#order input[name='yur_bank']"));
		errors++;
	}	
	if ($("form#order input[name='yur_ks']").val().length < 1 && $("form#order input[name='yur_ks']").is(':visible')) {
		setInputError($("form#order input[name='yur_ks']"));
		errors++;
	}	
	if ($("form#order input[name='yur_bik']").val().length < 1 && $("form#order input[name='yur_bik']").is(':visible')) {
		setInputError($("form#order input[name='yur_bik']"));
		errors++;
	}
    if ($("form#order input[name='transport_company']:checked").val() == undefined && $("form#order input[name='transport_company']").is(':visible')) {
        setInputError($("form#order input[name='transport_company']"));
        errors++;
    }

    if ($("form#order input[name='delivery_city']").val() == '' && $("form#order input[name='delivery_city']").is(':visible')) {
        setInputError($("form#order input[name='delivery_city']"));
        errors++;

        $('#transComp').removeClass('hide').find('input').addClass('hidden');
        $('.trans_comp').html('');
        $('#transCompResult').removeClass('hide');
        $('#transCompResult').text('�� ������ ����� ��������');
    }

    if ($('#transCompResult').is(":visible")) {
        setInputError($("form#order input[name='delivery_city']"));
        errors++;
    }
	
	
	if (($("form#order input#site").data('id') == undefined) || (($("form#order input#site").data('id').length == 0))) {
	  setInputError($("form#order input#site"));
	  errors++;
	}
	if (!errors) {
	  submitOrder();
	}
	return false;
  });
  
	//�������� ���� ��� ������ ��������������
	$('.product-row').each(function(i, el) {
		updatePriceAndCountRow(el);
	});
	calculateDelivery($('#delivery_type .active').data('value'));
	//hideShowRowControls();
	
	preventNotNumberQuantity();
	
	selectPaymentType($('#payment_type').find('a.active').data('value'));
	
	loaded = true;

    // ������ ��������� �������� ����� edost.ru ��� �������������� ������

    if ($('.btn-delivery-type.active').data('value') == 2 && $('.product-select').val() !=='') {
        getDeliveryCost($.trim($('#delivery_city').val()));
    }

    //�������� �� �������� ����
    $('.control-group').on('change', '.product-count', function () {
        updatePriceAndCountRow($(this).parent());
        calculateDelivery($('#delivery_type .active').data('value'));
        if ($('#delivery3').hasClass('active')) {
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

    //������� ���������� ��� ����������
    hideDatepicker();

    $('.preorder').change(function(){
        if (!$(this).is(":checked") && !$(".btn-payment-type[data-value='2']").hasClass('active')){
            $('#datepicker').parents('.control-group').show();
        } else if ($('#datepicker').parents('.control-group').is(":visible")) {
            $('#datepicker').parents('.control-group').hide();
        }
    });

});

function hideDatepicker() {
    if ($('.preorder').is(":checked")) {
        $('#datepicker').parents('.control-group').hide();
    }
}

// ������������� ���� ������ ��� ��������� �����
function setInputError(element) {
  $(element).parents('.control-group').addClass('error');
}

// ���������� ���� ������ ��� �������� �����
function clearInputError(element) {
  $(element).parents('.control-group').removeClass('error');
}

// �������� �����
function disableForm(form) {
  $(form).find("input").attr('disabled','disabled');
  $(form).find("select").attr('disabled','disabled');
  $(form).find("textarea").attr('disabled','disabled');
  $(form).find("a.btn").addClass('disabled');
  $(form).find(".hasDatepicker").datepicker('disable');
  $(form).find("button[type='submit']").button('loading');
  loaded = false;
}

// ������� �����
function enableForm(form) {
  $(form).find("input").removeAttr('disabled');
  $(form).find("select").removeAttr('disabled');
  $(form).find("textarea").removeAttr('disabled');
  $(form).find("a.btn").removeClass('disabled');
  $(form).find(".hasDatepicker").datepicker('enable');
  $(form).find("button[type='submit']").button('reset');
  loaded = true;
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
            url: 'api_calc_delivery.php',
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
                                            '<span class="company_name">' + val.company + ':' + '</span>' +
                                        '</td>' +
                                        '<td>' +
                                            '<strong>' + val.price + '</strong> �.' +
                                        '</td>' +
                                        '<td  class="delivery_time hide">' +
                                            '<span>(' + val.day + ')</span>' +
                                        '</td>' +
                                    '</tr>');

                                var transport_company_val = $("form#order input[name='transport_company_val']").val();
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
                                            '<span class="company_name">' + val.entrance.company + ':' + '</span>' +
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

                                var transport_company_val = $("form#order input[name='transport_company_val']").val();
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
    } else if ($('.btn-delivery-type.active').data('value') == 2) {
        $('#transComp').removeClass('hide');
        $('.trans_comp').html('');
        $('#transCompResult').removeClass('hide');
        $('#transCompResult').text('�� ������ ����� ��������');
    }

}

// ������� ������������ ������������� ��������.
function getDelivery_id() {
    if ($('#delivery').hasClass('active'))
    { return 0; }  else if ($('#delivery2').hasClass('active'))
    { return 1; } else return 2;
}

// �������� � ����� ����� ����� ���
function getFullName(name) {
    if (name == 'fname') {
        if ($("form#order input[name='firstname']").val() !== '' && $("form#order input[name='firstname']").is(':visible')) {
            return $("form#order input[name='firstname']").val();
        } else {
            return $("form#order input[name='firstname2']").val();
        }
    }
    if (name == 'lname') {
        if ($("form#order input[name='lastname']").val() !== '' && $("form#order input[name='lastname']").is(':visible')) {
            return $("form#order input[name='lastname']").val();
        } else {
            return $("form#order input[name='lastname2']").val();
        }
    }
    if (name == 'mname') {
        if ($("form#order input[name='middlename']").val() !== '' && $("form#order input[name='middlename']").is(':visible')) {
            return $("form#order input[name='middlename']").val();
        } else {
            return $("form#order input[name='middlename2']").val();
        }
    }
}

// �������� ������
function submitOrder() {
    if ($('#datepicker').is(':visible')) {
        if ($("#datepicker").datepicker('getDate') == null) {
            if (!confirm('�� ������� ��� ������ ��������� ����� � ������������� �����?'))
                return;
        }
    }

    if (getProductsArray().length == 0) {
        if (!confirm('�� ������� ��� ������ ��������� ����� � �� ��������� ������� ?'))
            return;
    }
  $.ajax({
	type: 'POST',
	url: 'ajax_order_edit.php',
	dataType: 'json', 
	cache: false,  
	data: {
		   'id': $("form#order input[name='id']").val(),
		   'products': JSON.stringify(getProductsArray()),
		   'fname': getFullName('fname'),
		   'lname': getFullName('lname'),
		   'mname': getFullName('mname'),
		   'email': $("form#order input[name='email']").val(),
		   'phone': $("form#order input[name='phone']").val(),
		   'address': $('.btn-delivery-type.active').data('value') == 0 ? $("form#order input[name='address']").val() : null,
           'delivery_city': $('.btn-delivery-type.active').data('value') == 2 ? $("form#order #delivery_city").val() : null,
           'transport_company': $('.btn-delivery-type.active').data('value') == 2 ? $("form#order input[name='transport_company']:checked").val() : null,
		   'yur_payer': $("form#order input[name='yur_payer']").val(),
		   'yur_address': $("form#order input[name='yur_address']").val(),
		   'yur_inn': $("form#order input[name='yur_inn']").val(),
		   'yur_kpp': $("form#order input[name='yur_kpp']").val(),
		   'yur_rs': $("form#order input[name='yur_rs']").val(),
		   'yur_bank': $("form#order input[name='yur_bank']").val(),
		   'yur_ks': $("form#order input[name='yur_ks']").val(),
		   'yur_bik': $("form#order input[name='yur_bik']").val(),
		   'wmid': $("form#order input[name='wmid']").val(),
		   'fiz_address': $("form#order input[name='fiz_address']").val(),
		   'comments': $("form#order textarea[name='note']").val(),
		   'customer_type': $('.btn-customer-type.active').data('value'),
		   'payment_type': $('.btn-payment-type.active').data('value'),
		   'site_id': $("form#order input#site").data('id'),
		   'delivery_id': getDelivery_id(),
		   'amount':$('#sum-total').html()-$('#sum-delivery').html(),
		   'delivery_date': $('#datepicker').is(':visible') ? $.datepicker.formatDate('yy-mm-dd', $("#datepicker").datepicker('getDate')) : null,
           'sms_notice_buyer': $('.sms_notice-buyer').is(':checked')?"1":"0",
           'sms_notice_managers': $('.sms_notice-managers').is(':checked')?"1":"0",
           'preorder': $('.preorder').is(':checked')?"1":"0"
		  },
	beforeSend : function(req) {
					disableForm($('form#order'));
				 },
	success: function(data) {
				try {
				  if (data.status == 1) {
                      /* ����� ����� */
					//$("#msgSuccess").show('fast');
					if ($("form#order input[name='id']").val() != data.order_id) {
                        $('form#order #edit-order-title').html('����� #' + data.order_id);
                        $('form#order .sms_notice-buyer').closest('.control-group').empty().remove();
                    }
					$("form#order input[name='id']").val(data.order_id);
				  } else {
					$("#msgError").show('fast');
				  }
				} catch(e) {
				  $("#msgError").show('fast');
				} finally {
				  enableForm($('form#order'));
				}
			},
	error: function(xhr, ajaxOptions, thrownError) { 
				$("#msgError").show('fast');
				enableForm($('form#order'));
			},
	complete: function() {}
  });
}

function recalculateOrder()
{
	sum = 0;
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

    $('.product-row').last().find('.product-price-and-count').attr('style', 'display:none !important;');
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
		calculateDelivery($('#delivery_type .active').data('value'));
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
	$('.btn-customer-type').removeClass('active');
	$('#customer_type').find("[data-value='" + type + "']").addClass('active');
	if (type == 1) {
        if ($('.btn-delivery-type.active').data('value') !== 2) {
            $('.btn-payment-type').attr('disabled',false);
            selectPaymentType($('#payment_type').find('.active').data('value'));
        } else {
            $('.btn-payment-type').attr('disabled',false);
            $('#payment_type').find("[data-value='1']").attr('disabled',true);
            $('#payment_type').find("[data-value='3']").attr('disabled',true);
            selectPaymentType($('#payment_type').find('.active').data('value'));
        }
	}
	if (type == 2) {
		$('.btn-payment-type').attr('disabled',true);
		$('#payment_type').find("[data-value='2']").attr('disabled',false);
		$('#payment_type').find("[data-value='2']").click();		
	}
}

function selectDeliveryType(type)
{
    switch(type) {
        case 0:
            $('#to_city').addClass('hide');
            $('#address').removeClass('hide');
            $('#transComp').addClass('hide');
            if ($('#customer_type').find("[data-value='1']").hasClass('active')) {
                $('.btn-payment-type').attr('disabled',false);
                selectPaymentType($('#payment_type').find('.active').data('value'));
            }
            break;
        case 1:
            $('#to_city').addClass('hide');
            $('#address').addClass('hide');
            $('#transComp').addClass('hide');
            if ($('#customer_type').find("[data-value='1']").hasClass('active')) {
                $('.btn-payment-type').attr('disabled',false);
                selectPaymentType($('#payment_type').find('.active').data('value'));
            }
            $('#sum-delivery-hint').text('');
            $('#distance-from-mkad').val('0');
            break;
        case 2:
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
            $('.btn-payment-type').attr('disabled',true);
            $('#payment_type').find("[data-value='2']").attr('disabled',false);

            if ($('#customer_type').find('.btn.active').data('value') == 1) {
                $('#payment_type').find("[data-value='4']").attr('disabled',false);
            }

            $('#payment_type').find("[data-value='2']").click();

            $('#sum-delivery-hint').text('');
            $('#distance-from-mkad').val('0');
            break;
    }

}

function selectPaymentType(type)
{
	var customer_type = $('#customer_type').find('.btn.active').data('value');
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
        $('#datepicker').parents('.control-group').hide();
    } else if (!$('.preorder').is(":checked")) {
        $('#block-email').hide();
        $('#datepicker').parents('.control-group').show();
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
        row.find('.product-price-and-count').attr('style', 'display:none !important;');
        row.find('.product-price').val('0');
        row.find('.product-count').data('count','0');
        $('#transComp').addClass('hide');
	} else {
        row.remove();
		$('.btn-add-product').hide();
		$('.product-row:visible').last().find('.btn-add-product').show();
        if ($('#delivery3').hasClass('active') && $('#to_city').is(':visible')) {
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
	calculateDelivery($('#delivery_type .active').data('value'));
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
			calculateDelivery($('#delivery_type .active').data('value'));

            if ($('#delivery3').hasClass('active') && $('#to_city').is(':visible')) {
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

function setSiteAutoComplete()
{
	$("#site").autocomplete({
		source: function( request, response ) {
			var matcher = new RegExp( $.ui.autocomplete.escapeRegex(request.term), "i" );
			var filtered = jQuery.map(sites, function(el) {
				var text = el.label;
				if ( text && ( !request.term || matcher.test(text) ) )
				{
					return {
						label: text.replace( new RegExp("(?![^&;]+;)(?!<[^<>]*)(" + $.ui.autocomplete.escapeRegex(request.term) + ")(?![^<>]*>)(?![^&;]+;)", "gi"), "<strong>$1</strong>" ),
						value: text,
						site_id: el.site_id
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
				$(this.element).removeData('id');
				$(this.element).removeData('site');
				$(this.element).removeData('text');
			}
			$(this.element).data('text', request.term);
		},
		minLength: 3,
		select: function( event, ui ) {
			$(this).data('id', ui.item.site_id);
			$(this).data('site', ui.item.value);
			$(this).data('text', $(this).val());
			clearInputError($(this));
		}
	}).focusout(function(){
		$(this).data('text', $(this).val());
		if ($(this).data('site') != $(this).val()) {
			$(this).removeData('id');
			$(this).removeData('site');
			$(this).val('');
		}
	}).bind('focus', function(){
		  $(this).val($(this).data('text'));
		  $(this).autocomplete("search");
	}).data("ui-autocomplete")._renderItem = function( ul, item ) {
		return $( "<li>" ).append( "<a>" + item.label + "</a>" ).appendTo( ul );
	};
}

var markOrder2;
var objectsOutsideMoscow;

function updateNewOrderPage(order) {
	if (typeof order.mark != 'undefined') {
		markOrder2 = order.mark;
		markOrder2.options.set({preset: 'twirl#darkgreenStretchyIcon'});
		map.panTo(markOrder2.geometry.getCoordinates(), {flying: true, delay: 0});
	}
	if (typeof order.objectsOutsideMoscow != 'undefined') {
		objectsOutsideMoscow = order.objectsOutsideMoscow;
	}
	$('#distance-from-mkad').val(order.distanceFromMkad);
	calculateDelivery($('#delivery_type .active').data('value'));
	
	//������� ������, ������ ������
	$('#find').prop('disabled',false);
	$('#address-loader').hide();
}

function showOrderOnMap()
{
	//�������� ������, ���������� ������
	$('#find').prop('disabled',true);
	$('#address-loader').show();
	
	if (typeof markOrder2 === 'object' && same_address !== $('#suggest_address').val()) {
        colOrder.removeAll();
	}
	if (typeof objectsOutsideMoscow === 'object' && same_address !== $('#suggest_address').val()) {
		objectsOutsideMoscow.each(function (geoObject) {
			colOrders.remove(geoObject);
		});
	}
    if ($('#suggest_address').val() !== '' && $('#delivery').hasClass('active') && same_address !== $('#suggest_address').val()) {
        same_address = $('#suggest_address').val();
        $('#address_error').addClass('hide');
        mapOrder(($('#order_id').val() == 0) ? '����� �����' : $('#order_id').val(), $("input[name='address']").val(), undefined, true, updateNewOrderPage);
    } else {
        $('#find').prop('disabled', false);
        $('#address-loader').hide();
    }
}

function showOrdersWithTab() {
    if (!orders_on_map) {
        var datepicker_date = $.datepicker.formatDate('dd.mm.yy', $("#datepicker").datepicker('getDate'));
        if (datepicker_date !== '') {
            mapOrders(datepicker_date, $('#order_id').val());
        } else {
            var current_date = new Date(),
                date_string = (current_date.getDate()<10 ? '0' + current_date.getDate() : current_date.getDate()) + '.' + ((current_date.getMonth() + 1)<10 ? '0' + (current_date.getMonth() + 1) : (current_date.getMonth() + 1)) + '.' + current_date.getFullYear();

            mapOrders(date_string);
        }

        orders_on_map = true;
    }

    showOrderOnMap();
    setTimeout(function() {
        if($('#address-loader').is(':visible')) {
            $('#find').prop('disabled', false);
            $('#address-loader').hide();
            $('#address_error').removeClass('hide');
            $('#address_error').text('����� �� ������');
        }
    }, 4000);
}

function calculateDelivery(type)
{
    if (type == undefined || type == 0 || type == 2) {
        var distanceFromMkad = $('#distance-from-mkad').val();
        var sum = 0;
        var deliverySum = Math.round(distanceFromMkad*20);
        var extraSum = 0;
        $('.product-row').each(function(i, el) {
            sum += $(el).find('.product-count').val()*$(el).find('.product-price').val();
        });
        if (sum < 10000) {
            extraSum = 400;
        }
        if (distanceFromMkad > 0) {
            if (extraSum > 0)
                $('#sum-delivery-hint').html(extraSum + '�. + ' + deliverySum + '�. (' + distanceFromMkad + ' �� �� ����) = ');
            else
                $('#sum-delivery-hint').html(deliverySum + '�. (' + distanceFromMkad + ' �� �� ����) = ');

        } else {
            $('#sum-delivery-hint').html('');
        }
        $('#sum-delivery').html(deliverySum + extraSum);
        recalculateOrder();
    } else {
        $('#sum-delivery').html('0');
        recalculateOrder();
    }
}