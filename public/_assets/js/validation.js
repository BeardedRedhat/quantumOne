
// Written & owned by ISArc Ltd.

function validationIsNullOrWhiteSpace(str){
    return str === null || str.match(/^ *$/) !== null;
}

function validateForm(form) {
    var isValid = true;

    form.children("input, select, textarea, label").removeClass('error');

    /***** Required Field Validation *****/
    form.find('.required:visible:not(:disabled)').each(function () {
        var input = $(this);
        var label = $('label[for=' + input.attr('id') + ']');

        if (validationIsNullOrWhiteSpace(input.val())) {
            isValid = false;
            input.addClass('error');
            label.addClass('error');
        } else {
            input.removeClass('error');
            label.removeClass('error');
        }
    });
    /***** Required Field Validation *****/



    /***** Compare Integer *****/
    form.find('.compareInt:visible').each(function() {
        var input = $(this);
        var label = $('label[for=' + $(this).attr('id') + ']');

        if(input.val().length > 0) {
            var pattern = new RegExp(/^[0-9]+$/);

            if(!pattern.test(input.val())) {
                isValid = false;
                input.addClass('error');
                label.addClass('error');
            } else {
                input.removeClass('error');
                label.removeClass('error');
            }
        }
    });
    /***** Compare Integer *****/



    /***** Compare Decimal *****/
    form.find('.compareDecimal:visible').each(function() {
        var input = $(this);
        var label = $('label[for=' + $(this).attr('id') + ']');

        if(input.val().length > 0) {
            var pattern = new RegExp(/^[0-9]+(\.[0-9]+)?$/);

            if(!pattern.test(input.val())) {
                isValid = false;
                input.addClass('error');
                label.addClass('error');
            } else {
                input.removeClass('error');
                label.removeClass('error');
            }
        }
    });
    /***** Compare Decimal *****/



    /***** Compare Date *****/
    form.find('.compareDate:visible').each(function() {
        var input = $(this);
        var label = $('label[for=' + $(this).attr('id') + ']');

        if(input.val().length > 0) {
            var dateString = input.val();
            var validDate = (dateString.length == 10);

            var formatPattern = new RegExp(/^[0-3][0-9]\/[0-1][0-9]\/\d\d\d\d$/);

            if(validDate && formatPattern.test(dateString) == true) {
                var pattern = new RegExp(/^(\d{1,2})(\/|-)(\d{1,2})(\/|-)(\d{4})$/);
                var dtArray = dateString.match(pattern);

                var dtDay = dtArray[1];
                var dtMonth = dtArray[3];
                var dtYear = dtArray[5];

                if(dtMonth < 1 || dtMonth > 12) {
                    validDate = false;
                } else if(dtDay < 1 || dtDay > 31) {

                } else if((dtMonth == 4 || dtMonth == 6 || dtMonth == 9 || dtMonth == 11) && dtDay == 31) {
                    validDate = false;
                } else if(dtMonth == 2) {
                    var isLeapYear = (dtYear % 4 == 0);
                    if(dtDay > 29 || (dtDay == 29 && !isLeapYear)) {
                        validDate = false;
                    }
                }
            } else {
                validDate = false;
            }

            if(!validDate) {
                isValid = false;
                input.addClass('error');
                label.addClass('error');
            } else {
                input.removeClass('error');
                label.removeClass('error');
            }
        }
    });
    /***** Compare Date *****/



    /***** Compare Email Address *****/
    form.find('.compareEmail:visible').each(function() {
        var input = $(this);
        var label = $('label[for=' + $(this).attr('id') + ']');

        if(input.val().length > 0) {
            var pattern = new RegExp(/^((([a-z]|\d|[!#\$%&'\*\+\-\/=\?\^_`{\|}~]|[\u00A0-\uD7FF\uF900-\uFDCF\uFDF0-\uFFEF])+(\.([a-z]|\d|[!#\$%&'\*\+\-\/=\?\^_`{\|}~]|[\u00A0-\uD7FF\uF900-\uFDCF\uFDF0-\uFFEF])+)*)|((\x22)((((\x20|\x09)*(\x0d\x0a))?(\x20|\x09)+)?(([\x01-\x08\x0b\x0c\x0e-\x1f\x7f]|\x21|[\x23-\x5b]|[\x5d-\x7e]|[\u00A0-\uD7FF\uF900-\uFDCF\uFDF0-\uFFEF])|(\\([\x01-\x09\x0b\x0c\x0d-\x7f]|[\u00A0-\uD7FF\uF900-\uFDCF\uFDF0-\uFFEF]))))*(((\x20|\x09)*(\x0d\x0a))?(\x20|\x09)+)?(\x22)))@((([a-z]|\d|[\u00A0-\uD7FF\uF900-\uFDCF\uFDF0-\uFFEF])|(([a-z]|\d|[\u00A0-\uD7FF\uF900-\uFDCF\uFDF0-\uFFEF])([a-z]|\d|-|\.|_|~|[\u00A0-\uD7FF\uF900-\uFDCF\uFDF0-\uFFEF])*([a-z]|\d|[\u00A0-\uD7FF\uF900-\uFDCF\uFDF0-\uFFEF])))\.)+(([a-z]|[\u00A0-\uD7FF\uF900-\uFDCF\uFDF0-\uFFEF])|(([a-z]|[\u00A0-\uD7FF\uF900-\uFDCF\uFDF0-\uFFEF])([a-z]|\d|-|\.|_|~|[\u00A0-\uD7FF\uF900-\uFDCF\uFDF0-\uFFEF])*([a-z]|[\u00A0-\uD7FF\uF900-\uFDCF\uFDF0-\uFFEF])))\.?$/i);

            if(!pattern.test(input.val())) {
                isValid = false;
                input.addClass('error');
                label.addClass('error');
            } else {
                input.removeClass('error');
                label.removeClass('error');
            }
        }
    });
    /***** Compare Email Address *****/

    /***** Compare Regular Expression *****/
    form.find('[regex]:visible').each(function() {
        var input = $(this);
        var label = $('label[for=' + $(this).attr('id') + ']');

        if(input.val().length > 0) {
            var pattern = new RegExp(input.attr('regex'));

            //if(!pattern.test(input.val())) {
            if(!pattern.test(input.val())) {
                isValid = false;
                input.addClass('error');
                label.addClass('error');
            } else {
                input.removeClass('error');
                label.removeClass('error');
            }
        }
    });
    /***** Compare Regular Expression *****/

    return isValid;
}

$(document).ready(function () {
    $('form').submit(function (event) {
        if (!validateForm($(this))) {
            event.preventDefault();
            $('.error').first().focus();
        }
    });

    // Numeric only control handler
    $('.numericOnly').on('keydown', function(){

        if(event.keyCode == 17 || event.keyCode == 91) {
            ctrlKey = true;
            return true;
        }

        var key = event.charCode || event.keyCode || 0;
        // allow backspace, tab, delete, enter, arrows, numbers and keypad numbers ONLY
        // home, end, period, and numpad decimal

        return (
        key == 8 ||
        key == 9 ||
        key == 13 ||
        key == 46 ||
        key == 110 ||
        (key >= 35 && key <= 40) ||
        (key >= 48 && key <= 57) ||
        (key >= 96 && key <= 105) || (ctrlKey && key == 86));

    }).on('keyup', function() {
        if(event.keyCode == 17 || event.keyCode == 91) {
            ctrlKey = false;
        }
    });

    // Decimal only control handler
    $('.decimalOnly').on('keydown', function(){
        var key = event.charCode || event.keyCode || 0;
        // allow backspace, tab, delete, enter, arrows, numbers and keypad numbers ONLY
        // home, end, period, and numpad decimal
        return (
        key == 8 ||
        key == 9 ||
        key == 13 ||
        key == 46 ||
        key == 110 ||
        (key == 190 && $(this).val().indexOf('.') == -1) ||
        (key >= 35 && key <= 40) ||
        (key >= 48 && key <= 57) ||
        (key >= 96 && key <= 105));
    });

    $('.required').each(function(){
        var lbl = $('label[for='+$(this).attr('id')+']');
        lbl.html(lbl.html() + '<span style="color:#a94442;"> *</span>');
    });


    $('textarea[data-maxLength]').on('keydown', function() {
        if(event.keyCode == 17 || event.keyCode == 91) {
            ctrlKey = true;
            return true;
        }

        //if the key is either the delete or backspace keys
        if(event.keyCode == 8 || event.keyCode == 46 || (ctrlKey && event.keyCode == 65) || event.keyCode == 37 || event.keyCode == 38 || event.keyCode == 39 || event.keyCode == 40) {
            return true
        }

        var txt = $(this);
        var max = txt.attr('data-maxLength');

        if(txt.val().length >= max) {
            event.preventDefault();
        }
    }).on('keyup', function() {
        if(event.keyCode == 17 || event.keyCode == 91) {
            ctrlKey = false;
        }
    }).on('paste', function() {
        var txt = $(this);
        var max = txt.attr('data-maxLength');

        setTimeout(function() {
            txt.val(txt.val().replace('\n\r', '\n').replace('\r\n', '\n'));

            if(txt.val().length > max) {
                txt.val(txt.val().substring(0, max));
            }
        }, 0);
    });

});

