$(document).ready(function() {
    // form ajax
    $('#btnSubmit').on('click', function() {
        var token   = $('#token').val(),
            subject = $('#txtSubject').val(),
            message = $('#txtMessage').val();

        $.post(document.location.href, {
            'action'  : 'submitForm',
            'token'   : token,
            'subject' : subject,
            'message' : message
        }, function(data) {
            try {
                data = $.parseJSON(data);
                if(data.Result == "Ok") {
                    $('#response').empty().append(data.message);
                    $('#frmContact')[0].reset();
                }
            } catch(err) {
                console.log(err);
                $('#response').empty().append(data.message);
            }
        })
    });
});
