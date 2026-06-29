var Auth = {
	timeout: null,
	useCaptcha: false,
	useRecaptcha: false,
	useRecaptcha3: false,
	useFusionCaptcha: false,
	smsCode: "",

	// Battle.net: populate the dropdown of game accounts (e.g. 1#1, 1#2) for the user to pick
	renderAccountChooser: function(accounts) {
		var $select = $(".game-account-select").empty();

		if(!accounts || accounts.length === 0) {
			$("<option>", {
				value: "",
				text: "No game accounts",
				disabled: true,
				selected: true
			}).appendTo($select);
			$select.prop("disabled", true);
		} else {
			$select.prop("disabled", false);
			accounts.forEach(function(acc) {
				$("<option>", {
					value: acc.id,
					text: acc.label + " — " + acc.username
				}).appendTo($select);
			});
		}

		$(".game-account-field").removeClass("d-none");
		$(".error-feedback").addClass("d-none").removeClass("d-block");
	},

	login: function(submit = false) {
		var postData = {
			"username": $(".username-input").val(),
			"password": $(".password-input").val(),
			"remember": $(".remember-check").is(":checked"),
			"captcha": $(".captcha-input").val(),
			"game_account": $(".game-account-select").val() || "",
			"sms_code": Auth.smsCode || "",
			"submit": submit,
		};

		var fields = [
			"username", "password"
		];

		if(Auth.useCaptcha) {
			fields.push("captcha");
		}

		if(Auth.useRecaptcha) {
			postData["recaptcha"] = grecaptcha.getResponse();
		}

		if(Auth.useRecaptcha3) {
			postData["recaptcha"] = $(".g-recaptcha-response").val();
		}

		if(Auth.useFusionCaptcha) {
			postData["cap-token"] = $('input[name="cap-token"]').val();
		}

		clearTimeout (Auth.timeout);
		Auth.timeout = setTimeout (function()
		{
			$.post(Config.URL + "auth/checkLogin", postData, function(data) {
				try {
					data = JSON.parse(data);

					if(data["redirect"] === true) {
						window.location.href = Config.URL + "ucp";
						return;
					}

					// Battle.net: multiple game accounts -> let the user pick one
					if(data["selectAccount"]) {
						Auth.renderAccountChooser(data["selectAccount"]);
						return;
					}

					// SMS enrolment: account has no phone -> must register one before login
					if(data["enroll_phone"]) {
						var enrollErr = (data["messages"] && data["messages"]["error"]) ? data["messages"]["error"] : "";
						Swal.fire({
							title: lang('login_phone_title', 'auth') || 'Register your phone',
							text: enrollErr || (lang('login_phone_text', 'auth') || ''),
							input: 'tel',
							inputPlaceholder: '+34600000000',
							showCancelButton: true,
							confirmButtonText: lang('login_send_code', 'auth') || 'Send code'
						}).then(function(p) {
							if (!p.isConfirmed || !p.value) { return; }
							$.post(Config.URL + "auth/sendLoginCode", { phone: p.value, csrf_token_name: Config.CSRF }, function(d) {
								var rr; try { rr = JSON.parse(d); } catch(e) { rr = {}; }
								if (!rr.ok) { Swal.fire({ title: 'Error', text: rr.error || '', icon: 'error' }); return; }
								Swal.fire({
									title: lang('login_sms_title', 'auth') || 'SMS code',
									input: 'text',
									inputAttributes: { inputmode: 'numeric', autocomplete: 'one-time-code' },
									inputPlaceholder: lang('login_sms_ph', 'auth') || 'Code',
									showCancelButton: true
								}).then(function(c) {
									if (c.isConfirmed && c.value) { Auth.smsCode = c.value; Auth.login(true); }
								});
							});
						});
						return;
					}

					// SMS 2FA: an SMS code was sent, ask the user for it
					if(data["sms_required"]) {
						var errMsg = (data["messages"] && data["messages"]["error"]) ? data["messages"]["error"] : "";
						Swal.fire({
							title: lang('login_sms_title', 'auth') || 'SMS code',
							text: errMsg,
							input: 'text',
							inputAttributes: { inputmode: 'numeric', autocomplete: 'one-time-code' },
							inputPlaceholder: lang('login_sms_ph', 'auth') || 'Code',
							showCancelButton: true
						}).then(function(r) {
							if (r.isConfirmed && r.value) {
								Auth.smsCode = r.value;
								Auth.login(true);
							} else {
								Auth.smsCode = "";
							}
						});
						return;
					}

					if(data["showCaptcha"] === true) {
						$(".captcha-field").removeClass("d-none");
					}

					if (Auth.useFusionCaptcha && data.captcha_error === true) {
						Auth.resetFusionCaptcha();
					}

					if(Auth.useRecaptcha3)
						setCaptchaToken();

					for(var i = 0; i<fields.length;i++)
                    {
						if(data["messages"]["error"] != "")
                        {
							$(".username-input, .password-input, .captcha-input").parents(".input-group").addClass("border border-danger");
							$(".username-input, .password-input, .captcha-input").addClass("is-invalid");
							$(".error-feedback").addClass("invalid-feedback d-block").removeClass("d-none").html(data["messages"]["error"]);
						}
					}
				} catch(e) {
					console.error(e);
					console.log(data);
				}				
			});

		}, 500);
	},

	showPassword: function(ele) {
		if($(ele).data("show") == true) {
			$(ele).html('<i class="fa-duotone fa-eye-slash"></i>');
			$(ele).data("show", false);

			$("input#"+ $(ele).data("input-id")).attr("type", "password");
		} else if($(ele).data("show") == false) {
			$(ele).html('<i class="fa-duotone fa-eye"></i>');
			$(ele).data("show", true);
			
			$("input#"+ $(ele).data("input-id")).attr("type", "text");
		}
		
	},

	refreshCaptcha: function(ele) {
		$(".captcha-input").val('');
		$(".captcha-input").focus();
		var captchaID = $(ele).data("captcha-id");
		var imgField = $("img#"+ captchaID);
		imgField.attr("src", imgField.attr("src") +"&d="+ new Date().getTime());
	},

	resetFusionCaptcha: function () {
		$('input[name="cap-token"]').val('');

		const widget = document.querySelector('cap-widget');
		if (widget && typeof widget.reset === 'function') {
			widget.reset();
			widget.removeAttribute('disabled');
		} else {
			console.warn('Fusion captcha widget not found. Please ensure it is in the DOM.');
		}
	}
};