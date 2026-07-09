/* THE DEAD LAST — homepage town image carousel. Vanilla JS, no deps.
   Degrades gracefully: if this script fails to run, the first slide
   (marked .is-active in the HTML) stays visible and dots are inert. */
(function () {
	'use strict';

	var AUTO_ADVANCE_MS = 5000;

	function initCarousel(root) {
		var slides = root.querySelectorAll('.town-carousel__slide');
		var dots = root.querySelectorAll('.town-carousel__dot');
		if (!slides.length) return;

		var current = 0;
		var timer = null;

		function goTo(index) {
			current = (index + slides.length) % slides.length;
			slides.forEach(function (slide, i) {
				slide.classList.toggle('is-active', i === current);
			});
			dots.forEach(function (dot, i) {
				dot.classList.toggle('is-active', i === current);
			});
		}

		function next() {
			goTo(current + 1);
		}

		function startAuto() {
			stopAuto();
			timer = window.setInterval(next, AUTO_ADVANCE_MS);
		}

		function stopAuto() {
			if (timer !== null) {
				window.clearInterval(timer);
				timer = null;
			}
		}

		dots.forEach(function (dot, i) {
			dot.addEventListener('click', function () {
				goTo(i);
				startAuto();
			});
		});

		startAuto();
	}

	function init() {
		var root = document.getElementById('town-carousel');
		if (root) initCarousel(root);
	}

	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', init);
	} else {
		init();
	}
})();
