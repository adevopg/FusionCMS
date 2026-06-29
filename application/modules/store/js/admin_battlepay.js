var BattlePay = {

	/**
	 * Delete a product
	 */
	remove: function(id, element) {
		Swal.fire({
			title: 'Delete this product?',
			text: "You won't be able to revert this!",
			icon: 'warning',
			showCancelButton: true,
			confirmButtonColor: '#d33',
			confirmButtonText: 'Yes, delete it!'
		}).then((result) => {
			if (result.isConfirmed) {
				$.post(Config.URL + "store/admin_battlepay/delete/" + id, {}, function() {
					$(element).parents("tr").slideUp(300, function() { $(this).remove(); });
				});
			}
		});
	},

	/**
	 * Toggle enabled/disabled
	 */
	toggle: function(id, element) {
		$.post(Config.URL + "store/admin_battlepay/toggle/" + id, {}, function() {
			var $badge = $(element).find("span");
			if ($badge.hasClass("bg-success")) {
				$badge.removeClass("bg-success").addClass("bg-secondary").text($badge.data("off") || "Disabled");
			} else {
				$badge.removeClass("bg-secondary").addClass("bg-success").text($badge.data("on") || "Enabled");
			}
		});
	},

	/**
	 * Submit the add/edit form via AJAX
	 */
	submit: function(form) {
		var $form = $(form);

		$.post($form.attr("action"), $form.serialize(), function(data) {
			if ($.trim(data) === "yes") {
				Swal.fire({ title: 'Saved!', icon: 'success', timer: 1200, showConfirmButton: false })
					.then(function() { window.location.href = Config.URL + "store/admin_battlepay"; });
			} else {
				Swal.fire({ title: 'Error', text: data, icon: 'error' });
			}
		});

		return false;
	}
};

$(function() {
	// Live icon preview in the add/edit form
	$("#bp_icon").on("keyup change", function() {
		var val = $.trim($(this).val());
		var base = $(this).data("iconbase");
		var src;
		if (val === "") {
			src = base + "/small/inv_misc_gift_02.jpg";
		} else if (/^https?:\/\//i.test(val)) {
			src = val;
		} else {
			src = base + "/small/" + val.toLowerCase().replace(/\.(jpg|jpeg|png|gif)$/i, "") + ".jpg";
		}
		$("#bp_icon_preview").css("opacity", 1).attr("src", src);
	});

	// Simple client-side search filter
	$("#BattlePaySearch").on("keyup", function() {
		var q = $(this).val().toLowerCase();
		$("#BattlePayTableResult tr").each(function() {
			$(this).toggle($(this).text().toLowerCase().indexOf(q) > -1);
		});
	});
});
