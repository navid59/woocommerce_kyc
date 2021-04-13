var $ = jQuery;
$("#netopia_agreement_privacy_policy_verify, #netopia_agreement_terms_conditions_verify, #netopia_agreement_delivery_policy_verify, #netopia_agreement_return_cancel_verify, #netopia_agreement_gdpr_verify,#netopia_agreement_visa_logo_verify,#netopia_agreement_master_logo_verify,#netopia_agreement_netopia_logo_verify").click(function () {
    validate_url($(this))
})
function validate_url(button){
    var elem = button.prev('input');
    var address = elem.val()
    $.ajax({
        url: checkAddress_ajax.ajax_url,
        method: 'POST',
        dataType: 'json',
        data: {
            address: address,
            action:'check_url_validation',
        },
        success: function (response) {
            if(response.exists == false) {
                if(!address) {
                    toastr.error("You enter an empty address!");
                } else {
                    toastr.error("`" + address + "`" + " is not a valid url address!");
                }
                elem.val('');
                elem.removeAttr('style');
                elem.css('border','1px red solid');
            } else {
                toastr.success("`" + address + "`" + " is valid!");
                elem.removeAttr('style');
                elem.css('border','1px green solid');
            }
        }
    })
}


$("#netopia_agreement_ssl_verify").on('click',function(){
    $.ajax({
        url: checkAddress_ajax.ajax_url,
        method: 'POST',
        dataType: 'json',
        data: {
            action:'ssl_validation',
        },
        success: function (response) {
            if(response == false) {
                toastr.error("Non valid SSL certification!");
            } else {
                toastr.success("Valid certification!");
            }
        }
    })
})

$("#askGoLive_verify").on('click', function (){
    $.ajax({
        url: checkAddress_ajax.ajax_url,
        method: 'POST',
        dataType: 'json',
        data: {
            action:'golive_validation',
        },
        success: function (response) {
            if(response == false) {
                toastr.error("Your request is not sent!!!");
            } else {
                toastr.success("Your Request is sent");
            }
        }
    })
})


$("#sendToVerify").on('click', function (){
    $.ajax({
        url: checkAddress_ajax.ajax_url,
        method: 'POST',
        dataType: 'json',
        data: {
            action:'send_agreement',
        },
        success: function (response) {
            if(response == false) {
                toastr.error("Your request is not sent!!!");
            } else {
                toastr.success("Your Request is sent");
            }
        }
    })
})