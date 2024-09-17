jQuery(document).ready(function($) {
	// Listen for change event on the active_leagues_type select field
	$('#active_leagues_type').change(function() {
		var selectedValue = $(this).val();
		$('#active_l_t_h').val(selectedValue);
		if(selectedValue==2){
			$('#manual_active_leagues').show();
			$('#list_active_leagues').hide();
		}else{
			$('#manual_active_leagues').hide();
			$('#list_active_leagues').show();
		}
	});
});

function syncro() {
    jQuery('#manual-sync-button').prop('disabled', true);
    var leagueId = jQuery('#league-id').val();
    var spinner = document.getElementById('spinner');
    var fechaHoraActual = new Date();
    spinner.style.display = 'block';
    showProgressMessage('Starting manual synchronization at ' + fechaHoraActual + ' ...');

    var syncPromise = new Promise(function(resolve, reject) {
        jQuery.ajax({
            url: ajaxurl,
            type: 'POST',
            data: {
                action: 'manual_sync',
                leagueId: leagueId
            },
            success: function(response) {
                console.log(response);
				var data = JSON.parse(response);
                if (data.success) {
					resolve(data.data);
                } else {
					console.log(response);
                    reject(data.data);
                }
            },
            error: function(error) {
				console.log(error);
                reject(error);
            }
        });
    });

    syncPromise.then(function(additionalData) {
		console.log(additionalData);		
        showProgressMessage('Synchronization completed.');
        jQuery('#manual-sync-button').prop('disabled', false);
        spinner.style.display = 'none';
    }).catch(function(error) {
        console.log(error)
        showProgressMessage('Error occurred during synchronization:', error);
        jQuery('#manual-sync-button').prop('disabled', false);
        spinner.style.display = 'none';
    });
}

function showProgressMessage(message) {
	var progressDiv = document.getElementById("sync-progress");
	var messageElement = document.createElement("p");
	messageElement.textContent = message;
	progressDiv.appendChild(messageElement);
}

