
$(document).ready(function() {

    // Update user ajax
    $('#btnSubmit').on('click', function() {
        var formToken = $('#token').val(),
            currency  = $('#selCurrency').val(),
            firstName = $('#txtFirstName').val(),
            lastName  = $('#txtLastName').val(),
            email     = $('#txtEmail').val(),
            notes     = $('#txtNotes').val();

        $.post(document.location.href, {
            'action'    : 'update',
            'token'     : formToken,
            'currency'  : currency,
            'firstName' : firstName,
            'lastName'  : lastName,
            'email'     : email,
            'notes'     : notes
        }, function(data) {
            try {
                data = $.parseJSON(data);
                console.log(data);
                if(data.Result == "Ok") {
                    $('#response').empty().append(data.message);
                }
            } catch(err) {
                console.log(err);
                $('#response').empty().append("Looks like an internal server problem. If problem persists, contact administrator..");
            }
        })
    });

    // Delete user ajax
    $('#btnDeleteUser').on('click', function() {
        if(confirm("Are you sure you want to permanently delete this user? \nThis action cannot be undone.")) {

        } else {
            alert("HERE");
        }
    });

});