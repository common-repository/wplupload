(function(window, $, undefined) {
	
var _parent = window.dialogArguments || opener || parent || top;

$.fn.wplupload  = function($options) {
	var $up, $defaults = { 
		runtimes : 'gears,browserplus,html5,flash,silverlight,html4',
		browse_button_hover: 'hover',
		browse_button_active: 'active'
	};
	
	$options = $.extend({}, $defaults, $options);
	
	return this.each(function() {
		var $this = $(this);
				
		$up = new plupload.Uploader($options);
		
		
		/*$up.bind('Init', function(up) {
			var dropElm = $('#' + up.settings.drop_element);
			if (dropElm.length && up.features.dragdrop) {
				dropElm.bind('dragenter', function() {
					$(this).css('border', '3px dashed #cccccc');
				});
				dropElm.bind('dragout drop', function() {
					$(this).css('border', 'none');
				});
			}			
		});*/
		
		$up.bind('FilesAdded', function(up, files) {
			$.each(files, function(i, file) {
				// Create a progress bar containing the filename
				$('#media-items').append(
					'<div id="media-item-' + file.id + '" class="media-item child-of-' + 0 + '">' +
						'<div class="progress">' +
							'<div class="bar"></div>' +
						'</div>'+
						'<div class="filename original"><span class="percent"></span> ' + file.name + '</div>' +
					'</div>'
				);
			
				// Display the progress div
				$('.progress', '#media-item-' + file.id).show();				
			})
		});
		
		$up.init();
		
		
		$up.bind('Error', function(up, err) {
			var $item = $('#media-item-' + err.file.id);
			
			$item.html('<div class="error-div"><a class="dismiss" href="#" onclick="jQuery(this).parents(\'div.media-item\').slideUp(200, function(){jQuery(this).remove();});">' + swfuploadL10n.dismiss + '</a><strong>' + swfuploadL10n.error_uploading.replace(/%s/, err.file.name) + '</strong><br />'+err.message+'</div>');
		});
		
		$up.bind('FilesAdded', function(up, files) {
			// Disable submit and enable cancel
			$('#insert-gallery').attr('disabled', 'disabled');
			$('#cancel-upload').removeAttr('disabled');
			
			$up.start();
		});
		
		$up.bind('UploadFile', function(up, file) {			
			$('<div class="wpl-cancel" title="'+swfuploadL10n.cancel_upload+'"/>')
				.click(function() {
					var $item = $(this).closest('.media-item'),
						id = $item.attr('id').replace(/^media-item-/, '');
					
					$up.stop();
					$up.removeFile($up.getFile(id));
					$item.remove();
					$up.start();
				})
				.appendTo('#media-item-' + file.id + ' .progress');
		});
		
		$up.bind('UploadProgress', function(up, file) {
			// Lengthen the progress bar
			var $item = $('#media-item-' + file.id);
			
			$('.bar', $item).width( file.percent + '%' );
			$('.percent', $item).html( file.percent + '%' );
		
			if ( file.percent == '100' )
				$item
					.find('.progress .wpl-cancel')
						.remove()
						.end()
					.find('.bar')
						.html('<strong class="crunching">' + swfuploadL10n.crunching + '</strong>');
		});
		
		
		$up.bind('FileUploaded', function(up, file, r) {
			var fetch = typeof(shortform) == 'undefined' ? 1 : 2;
			
			r = _parseJSON(r.response);		
									
			if (r.OK) {			
				$('#media-item-' + file.id).load(ajaxurl, {
						name: file.name,
						action: 'wpl_handle_upload',
						nonce: r.nonce,
						fetch: fetch,
						post_id: post_id
					}, function() {
						prepareMediaItemInit(file);
						updateMediaForm()
					}
				);
			}
		});
		
		$up.bind('UploadComplete', function(up) {
			$('#cancel-upload').attr('disabled', 'disabled');
			$('#insert-gallery').removeAttr('disabled');
		});
		
		$('#cancel-upload').click(function() {
			var i, file;
			
			$up.stop();		
						
			i = $up.files.length;
			for (i = $up.files.length - 1; i >= 0; i--) {
				file = $up.files[i];
				if ($.inArray(file.status, [plupload.QUEUED, plupload.UPLOADING]) !== -1) {
					$up.removeFile($up.getFile(file.id));
					$('#media-item-' + file.id).remove();
				}
			}
			
			$('#cancel-upload').attr('disabled', 'disabled');
			$('#insert-gallery').removeAttr('disabled');
		});
		
	});	
};

function _parseJSON(r) {
	var obj;
	try {
		obj = $.parseJSON(r);	
	} catch (e) {
		obj = { OK : 0 };	
	}	
	return obj;
}

}(window, jQuery));