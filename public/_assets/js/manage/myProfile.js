$(document).ready(function() {

    // form ajax
    $('#btnUpdate').on('click', function() {
        var firstName = $('#txtFirstName').val(),
            lastName  = $('#txtLastName').val(),
            email     = $('#txtEmail').val(),
            currency  = $('#selCurrency').val(),
            token     = $('#token').val();

        $.post(document.location.href, {
            'action'    : 'update',
            'token'     : token,
            'firstName' : firstName,
            'lastName'  : lastName,
            'email'     : email,
            'currency'  : currency
        }, function(data) {
            try {
                data = $.parseJSON(data);
                if(data.Result == "Ok") {
                    $('#response').empty().append(data.message);
                }
            } catch(err) {
                console.log(err);
                $('#response').empty().append("Looks like a server problem. Please try again later.");
            }
        })
    });

});