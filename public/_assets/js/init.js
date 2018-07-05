$(document).ready(function(){

    /* This checks for the data-href tag on any clickable item and uses it as a hyperlink. This is needed on mobile devices instead of a normal a-tag */
    var startX;
    var startY;
    var tap;

    $('body').on('touchstart', '[data-href]:not(:disabled)', function (event) {
        event = event.originalEvent;
        startX = event.clientX;
        startY = event.clientY;
    }).on('touchend', '[data-href]:not(:disabled)', function (event) {
        var endX = event.clientX;
        var endY = event.clientY;

        // If movement is less than 10px, execute the handler
        if ((endX - startX < 10 || endX - startX > -10) && (endY - startY < 10 || endY - startY > -10)) {
            tap = true;
            setTimeout(function() {
                tap = false;
            }, 100);

            document.location.href = $(this).attr('data-href');
        }
    }).on('click', '[data-href]:not(:disabled)', function(){
        document.location.href = $(this).attr('data-href');
    });

});