
$(document).ready(function() {

    // taken from Stack Oveflow
    // used to get url query string value(s)
    function getUrlParameter(sParam) {
        var sPageURL = decodeURIComponent(window.location.search.substring(1)),
            sURLVariables = sPageURL.split('&'),
            sParameterName,
            i;
        for (i=0; i<sURLVariables.length; i++) {
            sParameterName = sURLVariables[i].split('=');
            if (sParameterName[0] === sParam) {
                return sParameterName[1] === undefined ? true : sParameterName[1];
            }
        }
    }

    var qryStr = getUrlParameter('location'); // get query string value if one is set

    // If query string is set and equals users, show users tab
    if(qryStr == "users") {
        $('.nav-item').removeClass('active');
        $('.adminContent').css('display', 'none');
        $('#adminUsers').css('display', 'block');
        $('#btnUsers').addClass('active');
    }

    // Budget navigation menu
    $('.nav-item', this).click(function () {
        if ($(this).hasClass('active')) {
            return true;
        } else {
            $('.nav-item').removeClass('active');
            $(this).addClass('active');
            $('.adminContent').css('display', 'none');

            switch ($(this).val()) {
                case "Dashboard":
                    $('#adminDashboard').css('display', 'block');
                    break;
                case "Users":
                    $('#adminUsers').css('display', 'block');
                    break;
            }
        }
    });

    // Show/hide new currency form
    $('#addNew').on('click', function($e) {
        $e.preventDefault();
        var form = $('#divAddNewCurrency');
        if(form.css('display') == 'none')
            form.css('display', 'block');
        else
            form.css('display', 'none');
    });


    // Add currency AJAX
    $('#btnAddCurrency').on('click', function() {
        var token = $('#token').val(),
            name = $('#txtCurrencyName').val(),
            code = $('#txtCurrencyCode').val(),
            label = $('#txtCurrencyLabel').val(),
            locale = $('#txtCurrencyLocale').val();

        $.post(document.location.href, {
            'action' : 'addCurrency',
            'token'  : token,
            'name'   : name,
            'code'   : code,
            'label'  : label,
            'locale' : locale
        }, function(data) {
            try {
                data = $.parseJSON(data);
                if(data.Result == "Ok") {
                    $('#divResponse').empty().append(data.message);
                    $('#frmAddCurrency')[0].reset();
                }
            } catch(err) {
                console.log(err);
                $('#divResponse').empty().append("Oops! Looks like an internal server problem. If problem persists, contact administrator..");
            }
        })
    });
});