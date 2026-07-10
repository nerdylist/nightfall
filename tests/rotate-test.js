/*
 * Assertions for the home-page 3D model drag proxy (js/home-hero-model.js):
 *  - the invisible .community__model-grab surface exists and is the hit
 *    target at the model column's center;
 *  - a pointerdown on the proxy engages model-embed's drag state (canvas
 *    gains the "dragging" class), pointermove keeps it, pointerup ends it;
 *  - news links remain the hit target inside the canvas-overlap zone;
 *  - model rect / section height match the approved layout.
 * Results are written into #out as MEASURE_JSON for headless scraping.
 */
window.addEventListener('load', function () {
	window.setTimeout(runRotateTest, 1500);
});

function runRotateTest() {
	var res = {};
	var proxy = document.querySelector('.community__model-grab');
	var canvas = document.querySelector('.me-canvas');
	var model = document.querySelector('.community__model');
	var news = document.getElementById('news-1');
	var section = document.querySelector('.community');

	res.proxy_exists = !!proxy;
	res.canvas_exists = !!canvas;

	if (proxy && canvas) {
		var pr = proxy.getBoundingClientRect();
		var cx = pr.left + pr.width / 2;
		var cy = pr.top + pr.height / 2;

		var centerHit = document.elementFromPoint(cx, cy);
		res.center_hit_is_proxy = centerHit === proxy;

		function pev(type, x, y) {
			return new PointerEvent(type, {
				bubbles: true,
				cancelable: true,
				pointerId: 7,
				pointerType: 'mouse',
				isPrimary: true,
				clientX: x,
				clientY: y,
				button: 0,
				buttons: 1
			});
		}

		proxy.dispatchEvent(pev('pointerdown', cx, cy));
		res.dragging_after_down = canvas.classList.contains('dragging');

		window.dispatchEvent(pev('pointermove', cx + 60, cy));
		res.dragging_during_move = canvas.classList.contains('dragging');

		window.dispatchEvent(pev('pointerup', cx + 60, cy));
		res.dragging_after_up = canvas.classList.contains('dragging');
	}

	if (news && model) {
		var nr = news.getBoundingClientRect();
		var mr = model.getBoundingClientRect();
		var hasOverlap = Math.max(nr.left, mr.left) < Math.min(nr.right, mr.right)
			&& Math.max(nr.top, mr.top) < Math.min(nr.bottom, mr.bottom);
		var ox = (Math.max(nr.left, mr.left) + Math.min(nr.right, mr.right)) / 2;
		var oy = (Math.max(nr.top, mr.top) + Math.min(nr.bottom, mr.bottom)) / 2;
		var hit = hasOverlap ? document.elementFromPoint(ox, oy) : null;
		res.overlap_zone_hit = {
			has_overlap: hasOverlap,
			tag: hit ? hit.tagName : null,
			resolves_to_news_link: !!(hit && (hit === news || news.contains(hit))),
			is_model_or_proxy: !!(hit && (model.contains(hit) || hit === document.querySelector('.community__model-grab')))
		};
		res.model_rect = { w: Math.round(mr.width), h: Math.round(mr.height), x: Math.round(mr.left), y: Math.round(mr.top) };
	}

	if (section) {
		res.section_height = Math.round(section.getBoundingClientRect().height);
	}

	document.getElementById('out').textContent = 'MEASURE_' + 'JSON:' + JSON.stringify(res);
}
