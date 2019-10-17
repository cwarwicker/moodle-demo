function pp_setImage(obj, img)
{
    $(obj).attr('src', 'pix/'+img);
} 

function pp_deleteRequest(sID){
    
    var confirm = window.confirm("Are you sure you want to delete/cancel this request?");
    
    if (confirm === true){
        
        window.location.href = www + '?action=deleterequest&sID='+sID;
        return;
        
    }
    
}

function pp_searchParents()
{
    var search = $('#findParent').val();
    var params = {
        search: search
    };

    $('#parentSearchImg').html('<img src=\"pix/loading.gif\" />');
    $('#parentAccountSelect').html(''); 

    $.post('ajaxScripts.php', {action: 'search_parents', params: params}, function(data){
        eval(data);
        $('#parentSearchImg').html('');
    });
}

function pp_searchStudents()
{
    var search = $('#findStudent').val();
    var params = {
        search: search
    };

    $('#studentSearchImg').html('<img src=\"pix/loading.gif\" />');
    $('#studentAccountSelect').html('');

    $.post('ajaxScripts.php', {action: 'search_students', params: params}, function(data){
        eval(data);
        $('#studentSearchImg').html('');
    });
}

function pp_loadParentInfo(id)
{
    var params = {
        id: id
    };

    $('#parentSearchImg').html('<img src=\"pix/loading.gif\" />');

    $.post('ajaxScripts.php', {action: 'load_parent', params: params}, function(data){
        eval(data);
        $('#parentSearchImg').html('');
    });
}

function pp_loadStudentInfo(id)
{
    var params = {
        id: id
    };

    $('#studentSearchImg').html('<img src=\"pix/loading.gif\" />');

    $.post('ajaxScripts.php', {action: 'load_student', params: params}, function(data){
        eval(data);
        $('#studentSearchImg').html('');
    });
}

function pp_cancelAccess(id, sid)
{
    var params = {
        id: id,
        sid: sid
    };

    $.post('ajaxScripts.php', {action: 'cancel_access', params: params}, function(data){
        eval(data);
    });
}

function pp_confirmAccess(id, sid)
{
    var params = {
        id: id,
        sid: sid
    };

    $.post('ajaxScripts.php', {action: 'confirm_access', params: params}, function(data){
        eval(data);
    });
}

function pp_showHidePassword(id){
    
    var type = $('#'+id).attr('type');
    if (type == 'password'){
        $('#'+id).attr('type', 'text');
    } else {
        $('#'+id).attr('type', 'password');
    }
    
}

function pp_load_display(type, tab){
    
    var params = { type: type, studentID: ELBP.studentID, courseID: ELBP.courseID };
    
    ELBP.set_view_link(tab);
    $('#elbp_portal_content').html('<img src="'+M.cfg.wwwroot+'/blocks/elbp/pix/loader.gif" alt="" />');
    
    $.post(M.cfg.wwwroot + '/local/parentportal/ajaxScripts.php', {action: 'load_display_elbp', params: params}, function(data){
        $('#elbp_portal_content').html(data);
    });
        
}

function pp_update_status(id, password, status){
    
    var params = { id: id, password: password, status: status, studentID: ELBP.studentID, courseID: ELBP.courseID };
    
    $('#request_loading_'+id).html('<img src="'+M.cfg.wwwroot+'/blocks/elbp/pix/loader.gif" alt="" />');
    
    $.post(M.cfg.wwwroot + '/local/parentportal/ajaxScripts.php', {action: 'update_status_elbp', params: params}, function(data){
        eval(data);
        $('#request_loading_'+id).html('');
    });
    
}

// http://stackoverflow.com/questions/8579643/simple-jquery-scroll-to-anchor-up-or-down-the-page
function pp_scroll_to_anchor(aid){
    var aTag = $("a[name='"+ aid +"']");
    $('html,body').animate({scrollTop: aTag.offset().top},'slow');
}