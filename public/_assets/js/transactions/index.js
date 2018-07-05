function spinner(view) {
    if(view === "show") {
        $(".divBuffer").css('display','block');
    } else if(view === "hide") {
        $(".divBuffer").css('display','none');
    }
}

$(document).ready(function() {
    //Enables all recur input fields once checkbox is checked
    $('#chkRecurTrans').click(function() {
        $(".inptRecur").attr("disabled", !this.checked);
    });


    // Add transaction ajax
    $('#btnSubmitTrans').on('click', function() {
        var formToken = $('#transToken').val(),
            type = $('#selTransType').val(),
            account = $('#selAccounts').val(),
            category = $('#selCategories').val(),
            amount = $('#txtTransAmount').val(),
            receiptNo = $('#txtReceipt').val(),
            description = $('#txtDescription').val();

        if($('#chkRecurTrans').is(':checked')) {
            var startDate = $('#txtStartDate').val(),
                endDate = $('#txtEndDate').val(),
                freq = $('#selRecurRepeat').val(),
                repeatInd = 0;

            if($('#chkRecurIndef').is(':checked')) {
                repeatInd = 1;
            }
            var formData = {
                'action'      : 'addTransaction',
                'token'       : formToken,
                'type'        : type,
                'account'     : account,
                'category'    : category,
                'amount'      : amount,
                'receiptNo'   : receiptNo,
                'description' : description,
                'recur'       : 1,
                'startDate'   : startDate,
                'endDate'     : endDate,
                'frequency'   : freq,
                'repeatInd'   : repeatInd
            };
        } else {
            formData = {
                'action'      : 'addTransaction',
                'token'       : formToken,
                'type'        : type,
                'account'     : account,
                'category'    : category,
                'amount'      : amount,
                'receiptNo'   : receiptNo,
                'description' : description
            };
        }

        spinner("show");

        $.post(document.location.href, formData, function(data) {
            try {
                data = $.parseJSON(data);
                if(data.Result == "Ok") {
                    spinner("hide");
                    $('#transResponse').empty().append(data.message);
                    $('#frmNewTransaction')[0].reset();
                }
            } catch(err) {
                spinner("hide");
                console.log(err);
                $('#transResponse').empty().append(data.message);
            }
        })

    });


    // Add new category ajax
    $('#btnAddCat').on('click', function() {
        var formToken = $('#catToken').val(),
            name = $('#txtCategoryName').val(),
            budget = $('#txtCatBudget').val();

        spinner("show");

        $.post(document.location.href, {
            'action' : 'addCategory',
            'token'  : formToken,
            'name'   : name,
            'budget' : budget
        }, function(data) {
            try {
                data = $.parseJSON(data);
                if(data.Result == "Ok") {
                    spinner("hide");
                    $('#catResponse').empty().append(data.message);
                    $('#form-trans-add-cat')[0].reset();
                    $('#selCategories').empty().append(data.ddl);
                }
            } catch(err) {
                console.log(err);
                $('#catResponse').empty().append("Oops! Looks like an internal server problem. If problem persists, contact administrator..");
            }
        })
    });

});
