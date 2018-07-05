
function spinner(view) {
    if(view === "show") {
        $(".divBuffer").css('display','block');
    } else if(view === "hide") {
        $(".divBuffer").css('display','none');
    }
}

$(document).ready(function() {

    // Save account details
    $('#btnSave').on('click', function() {
        var formToken = $('#token').val(),
            name = $('#txtAccountName').val(),
            type = $('#selAccountType').val(),
            budget = $('#txtAccountBudget').val(),
            balance = $('#txtAccountBalance').val(),
            incNet = $('#chkIncNet').prop('checked');

        spinner("show");

        incNet = incNet==true ? 1 : 0;

        $.post(document.location.href, {
            'action'  : 'update',
            'token'   : formToken,
            'name'    : name,
            'type'    : type,
            'budget'  : budget,
            'balance' : balance,
            'incNet'  : incNet
        }, function(data) {
            try {
                data = $.parseJSON(data);
                if(data.Result = "Ok") {
                    spinner("hide");
                    $('#divResponse').empty().append(data.message);
                }
            } catch(err) {
                console.log(err);
                spinner("hide");
                $('#divResponse').empty().append("Oops! Looks like an internal server problem. If problem persists, contact administrator..");
            }
        });
    });


    // Delete account ajax
    $('#btnDelete').on('click', function() {
        if(confirm("Are you sure you want to permanently delete this account? This action cannot be undone.")) {
            spinner("show");
            var token = $('#token').val();
            $.post(document.location.href, { 'action'  : 'delete', 'token' : token }, function(data) {
                try {
                    data = $.parseJSON(data);
                    if(data.Result = "Ok") {
                        spinner("hide");
                        $('#divResponse').empty().append(data.message);
                    }
                } catch(err) {
                    spinner("hide");
                    console.log(err);
                    $('#divResponse').empty().append("Oops! Looks like an internal server problem. If problem persists, contact administrator..");
                }
            });
        }
    });

});
