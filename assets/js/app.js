"use strict";

document.addEventListener("DOMContentLoaded", function () {
	var storedTheme = localStorage.getItem("cmmp-theme") || "light";
	if (storedTheme === "dark") {
		document.body.setAttribute("data-cmmp-theme", "dark");
	}

	document.querySelectorAll("[data-bs-toggle=tooltip]").forEach(function (element) {
		new bootstrap.Tooltip(element);
	});

	document.querySelectorAll("[data-cmmp-toast]").forEach(function (element) {
		new bootstrap.Toast(element).show();
	});

	var themeToggle = document.querySelector("[data-cmmp-theme-toggle]");
	if (themeToggle) {
		themeToggle.addEventListener("click", function () {
			var nextTheme = document.body.getAttribute("data-cmmp-theme") === "dark" ? "light" : "dark";
			document.body.setAttribute("data-cmmp-theme", nextTheme);
			localStorage.setItem("cmmp-theme", nextTheme);
		});
	}
});
