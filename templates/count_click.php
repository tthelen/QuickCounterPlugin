jQuery(function() {

     // delete counter event from counter list / [qclist]
     jQuery('a.delete_event_id').live('click', function () {
         link = jQuery(this);
         jQuery.ajax({
             url: '<?= PluginEngine::getUrl('QuickCounterPlugin/delete_event') ?>',
             data: {'event_id': jQuery(this).attr('data-id') },
             success: function(data) {
                 if (data=='OK') {
                     link.parent().parent().fadeOut();
                 } else {
                     link.effect("shake",{ times:2, distance:10},100);
                 }
             },
             error: function(data) {
                 link.effect("shake",{ times:2, distance:10},100);
             }
       
         });
         return false;
     });

     // count and add comment
     jQuery('a.quickcounter').live('click', function () {
          var oldcontent = jQuery(this).html();
          var link = jQuery(this);
          jQuery(this).html("<form action='#' method='get' id='qc_comments_form' style='display:inline;'><input id='qc_comment' name='comment' type='text' data-countername='"+jQuery(this).attr('data-countername')+"' data-counterfrom='"+jQuery(this).attr('data-counterfrom')+"'></form>");
          jQuery("#qc_comment").focus();
          jQuery("#qc_comment").keydown( function (e) {
              if (e.which==27) {
                  jQuery("#qc_comment").blur();
                  return false;
              }
              if (e.which==13) {
		  jQuery("#qc_comment").blur( function () { } );
		  link.parent().load(
		     '<?= PluginEngine::getURL('QuickCounterPlugin/count') ?>',
			{ counter_name: jQuery("#qc_comment").attr('data-countername'), 
                          counter_from: jQuery("#qc_comment").attr('data-counterfrom'), 
			  comment: jQuery("#qc_comment").val() 
		     });
		     return false; 
              }
          });
          jQuery("#qc_comment").blur( function () {
              link.html(oldcontent);
              return false;
          });
          return false;
      });

});

