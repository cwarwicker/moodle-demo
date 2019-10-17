define(['jquery'], function($) {
 
    return {
        init: function(courseID) {
 
            // Submit form
            $('#quick_user_form').off('submit');
            $('#quick_user_form').on('submit', function(e){

                var search = $('#quick_user_search').val();
                search.trim();

                var results = $('#quick_user_results');
                results.html('');

                if (search == ''){
                    e.preventDefault();
                    e.stopPropagation();
                    return false;
                }

                results.html('<div class="quick_user_centre"><img id="quick_user_loading" src="'+M.cfg.wwwroot+'/blocks/quick_user/pix/load.gif" /></div>');

                $.post(M.cfg.wwwroot + '/blocks/quick_user/search.php', {
                    course: courseID,
                    search: search
                }, function(data){
                    results.html(data);
                });

                e.preventDefault();
                e.stopPropagation();
                return true;

            });



            // Clear results
            $('#quick_user_clear').off('click');
            $('#quick_user_clear').on('click', function(e){

                $('#quick_user_search').val('');
                $('#quick_user_results').html('');

                e.preventDefault();
                e.stopPropagation();
                return true;

            }); 
            

        }
    };
});