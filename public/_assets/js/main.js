
//Counter for character limit
function countChar(charLen)
{
    var dataValue = $('#txtDescription').data('length');
    var txtareaLen = charLen.value.length;

    if(txtareaLen >= dataValue) {
        charLen.value = charLen.value.substring(0,dataValue);
    } else {
        $('#count').text(dataValue - txtareaLen);
    }
}


function formatFl(val) {
    return val.toFixed(2);
}

function errorMsg(msg) {
    return "<span style=\"color:red; margin-top:1em;\"><span class=\"fa fa-exclamation-circle\">&nbsp;</span>"+msg+"</span>"
}