jQuery(document).ready(function($) {

    $("#btn_nabooki_link_account").click(function() {

        $("#nabooki_account_link_error").hide();

        $.post(
            ajaxurl,
            {
                action: 'nabooki_link_account',
                email: $('#nabooki_email').val().trim(),
                password: $('#nabooki_password').val().trim()
            },
            function(response) {

                if (response == 'ok') {

                    location.reload();

                } else {

                    $("#nabooki_account_link_error").show();
                }
            }
        );
    });

    $("#btn_nabooki_unlink_account").click(function() {

        $("#nabooki_account_unlink_error").hide();
        
        $.post(
            ajaxurl,
            {
                action: 'nabooki_unlink_account'
            },
            function(response) {

                if (response == 'ok') {

                    location.reload();

                } else {

                    $("#nabooki_account_unlink_error").show();
                }
            }
        );
    });

});