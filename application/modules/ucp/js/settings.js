var Settings = {

	wrongPassword: null,
	canSubmit: true,

	submit: function() {
		// Client-side check new password and confirmation must match
		if ($("#new_password").val() !== $("#new_password_confirm").val())
		{
			if (Settings.canSubmit) {
				Swal.fire({
					text: lang("pw_doesnt_match", "ucp"),
					icon: 'error'
				});
				Settings.canSubmit = false;
			}
			return;
		}

		if (Settings.wrongPassword != null && Settings.wrongPassword == $("#old_password").val())
		{
			return false;
		}

		// Show that we're loading something
		Settings.canSubmit = true;
		$("#settings_ajax").html('<div class="spinner-border text-primary" role="status"><span class="visually-hidden">Loading...</span></div>');

		// Gather the values
		var values = {
			old_password: $("#old_password").val(),
			new_password: $("#new_password").val(),
			csrf_token_name: Config.CSRF
		};

		// Submit the request
		$.post(Config.URL + "ucp/settings/submit", values, function(data) {
			// Clear spinner
			$("#settings_ajax").html('');

			// Parse the JSON response (jQuery automatically parses if server sends correct Content-Type,
			// but we can force dataType: 'json' in the $.post settings for safety)
			if (data.status === 'success') {
				Swal.fire({
					html: data.message || lang("changes_saved", "ucp"),
					icon: 'success',
					willClose: () => {
						window.location = Config.URL + "login";
					}
				});
			} else if (data.status === 'error') {
				// Handle error based on its code
				if (data.message === lang("invalid_pw", "ucp")) {
					// Store the wrong value to avoid repeated attempts
					Settings.wrongPassword = $("#old_password").val();
				}

				Swal.fire({
					html: data.message || lang("invalid_pw", "ucp"),
					icon: 'error'
				});
			} else {
				// Fallback for unexpected responses
				Swal.fire({
					html: data.message || data,
					icon: 'error'
				});
			}
		}, 'json').fail(function(jqXHR, textStatus, errorThrown) {
			// Handle network/server errors
			$("#settings_ajax").html('');
			Swal.fire({
				html: 'Request failed: ' + textStatus,
				icon: 'error'
			});
		});
	},

	submitInfo: function()
	{
		var value = $("#nickname_field").val(),
			loc = $("#location_field").val(),
			language;

		if($("#language_field"))
		{
			language = $("#language_field").val();
		}
		else
		{
			language = 0;
		}

		if(value.length < 4 || value.length > 14)
		{
			Swal.fire({
				text: lang("nickname_error", "ucp"),
				icon: 'error'
			});
		}
		else if(loc.length > 32)
		{
			Swal.fire({
				text: lang("location_error", "ucp"),
				icon: 'error'
			});
		}
		else
		{
			// Show that we're loading something
			$("#settings_info_ajax").html('<div class="spinner-border text-primary" role="status"><span class="visually-hidden">Loading...</span></div>');

			// Submit the request
			$.post(Config.URL + "ucp/settings/submitInfo",
			{
				nickname: value,
				location: loc,
				language: language,
				csrf_token_name: Config.CSRF
			},
			function(data)
			{
				$("#settings_info_ajax").html("");
				if(/1/.test(data))
				{					
					Swal.fire({
						text: lang("changes_saved", "ucp"),
						icon: 'success',
						willClose: () => {
							window.location.reload(true);
						}
					});
				}
				else if(/2/.test(data))
				{					
					Swal.fire({
						text: lang("nickname_taken", "ucp"),
						icon: 'error'
					});
				}
				else if(/3/.test(data))
				{
					Swal.fire({
						text: lang("invalid_language", "ucp"),
						icon: 'error'
					});
				}
				else
				{
					Swal.fire({
						text: data,
						icon: 'error'
					});
				}
			});
		}
	},

	changePhone: function() {
		var U = Config.URL + "ucp/settings/";
		var parse = function(d) { try { return JSON.parse(d); } catch (e) { return {}; } };
		var post = function(action, data) { return $.post(U + action, $.extend({ csrf_token_name: Config.CSRF }, data || {})); };
		var fail = function(r) { Swal.fire({ title: 'Error', text: (r && r.error) ? r.error : '', icon: 'error' }); };

		// 1) send code to current phone
		Swal.fire({
			title: lang('phone_change', 'ucp') || 'Change phone',
			text: lang('phone_send_to_old', 'ucp') || 'A code will be sent to your current phone.',
			showCancelButton: true,
			confirmButtonText: lang('login_send_code', 'auth') || 'Send code'
		}).then(function(s1) {
			if (!s1.isConfirmed) { return; }
			post("phoneSendOld").done(function(d) {
				var r = parse(d); if (!r.ok) { return fail(r); }
				// 2) verify current phone code
				Swal.fire({ title: lang('phone_code_old', 'ucp') || 'Code (current phone)', input: 'text', inputAttributes: { inputmode: 'numeric' }, showCancelButton: true }).then(function(s2) {
					if (!s2.isConfirmed || !s2.value) { return; }
					post("phoneVerifyOld", { code: s2.value }).done(function(d2) {
						var r2 = parse(d2); if (!r2.ok) { return fail(r2); }
						// 3) enter new phone -> send code
						Swal.fire({ title: lang('phone_change_new', 'ucp') || 'New phone number', input: 'tel', inputPlaceholder: '+34600000000', showCancelButton: true, confirmButtonText: lang('login_send_code', 'auth') || 'Send code' }).then(function(s3) {
							if (!s3.isConfirmed || !s3.value) { return; }
							post("phoneSendNew", { phone: s3.value }).done(function(d3) {
								var r3 = parse(d3); if (!r3.ok) { return fail(r3); }
								// 4) verify new phone code -> saved
								Swal.fire({ title: lang('phone_code_new', 'ucp') || 'Code (new phone)', input: 'text', inputAttributes: { inputmode: 'numeric' }, showCancelButton: true }).then(function(s4) {
									if (!s4.isConfirmed || !s4.value) { return; }
									post("phoneVerifyNew", { code: s4.value }).done(function(d4) {
										var r4 = parse(d4); if (!r4.ok) { return fail(r4); }
										$("#phone_field").val(r4.phone_masked || '');
										Swal.fire({ title: lang('phone_changed', 'ucp') || 'Phone changed', icon: 'success' });
									});
								});
							});
						});
					});
				});
			});
		});
	}
}
