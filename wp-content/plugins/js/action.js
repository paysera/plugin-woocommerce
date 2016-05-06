/***********
*
* Paysera payment gateway
*
* Javascript actions
*
***********/
jQuery( document ).ready(function($) {

    function fixas(){
        $('.payment-countries').css('display', 'none');
        idas = $('#billing_country').val().toLowerCase();
        geras = $('#' + idas).attr('class');

        if(!geras){
            idas = 'other';
        }

        jQuery('#paysera_country option')
            .attr("selected", "");

        jQuery('#paysera_country option[value="'+idas+'"]')
            .attr("selected", "selected");

        $('#' + idas).css('display', 'block');
    }

    $('.country_select').click(
        function(){
            fixas();
        });

    $(document).on('change', '#paysera_country' ,function(){
        idas =$( '#paysera_country' ).val();
        $('.payment-countries').css('display', 'none');
        $('#'+idas).css('display', 'block');
        });

    $( document ).ajaxComplete(function($) {fixas(); });

    });
