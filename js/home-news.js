/*
 * THE DEAD LAST — home page "Latest News" live feed.
 *
 * Fetches GET /api/feed?section=latest-news and renders the items into
 * #home-news-list using the same markup/classes the hardcoded prototype
 * used (css/index.css .news-item styles keep working untouched). Points
 * the "View All News" button (#home-news-all) at the feed's forum
 * category. On any failure it quietly renders "No news yet." — no
 * console spam, no layout break.
 */
(function () {
	'use strict';

	var SECTION = 'latest-news';
	// Threads have no images; rotate the existing placeholder thumbs.
	var THUMBS = [
		'/assets/images/9.png',
		'/assets/images/10.png',
		'/assets/images/11.png'
	];

	var list = document.getElementById('home-news-list');
	if (!list) {
		return;
	}

	function showEmpty() {
		list.textContent = '';
		var p = document.createElement('p');
		p.className = 'news-list__empty text-muted';
		p.textContent = 'No news yet.';
		list.appendChild(p);
	}

	function formatDate(iso) {
		var d = new Date(iso);
		if (isNaN(d.getTime())) {
			return '';
		}
		return d.toLocaleDateString('en-US', {
			month: 'long',
			day: 'numeric',
			year: 'numeric'
		});
	}

	function renderItem(item, index) {
		var link = document.createElement('a');
		link.className = 'news-item';
		link.href = typeof item.url === 'string' ? item.url : '#';

		var thumb = document.createElement('img');
		thumb.className = 'news-item__thumb';
		thumb.src = THUMBS[index % THUMBS.length];
		thumb.alt = '';

		var body = document.createElement('div');
		body.className = 'news-item__body';

		var title = document.createElement('h3');
		title.className = 'news-item__title';
		title.textContent = item.title || '';

		var date = document.createElement('p');
		date.className = 'news-item__date';
		date.textContent = formatDate(item.date);

		var blurb = document.createElement('p');
		blurb.className = 'news-item__blurb text-muted';
		blurb.textContent = item.excerpt || '';

		body.appendChild(title);
		body.appendChild(date);
		body.appendChild(blurb);
		link.appendChild(thumb);
		link.appendChild(body);

		return link;
	}

	fetch('/api/feed?section=' + encodeURIComponent(SECTION))
		.then(function (res) {
			if (!res.ok) {
				throw new Error('feed unavailable');
			}
			return res.json();
		})
		.then(function (data) {
			var items = data && Array.isArray(data.items) ? data.items : [];
			if (items.length === 0) {
				showEmpty();
				return;
			}

			list.textContent = '';
			items.forEach(function (item, index) {
				list.appendChild(renderItem(item, index));
			});

			var allBtn = document.getElementById('home-news-all');
			if (allBtn && data && typeof data.category_url === 'string') {
				allBtn.href = data.category_url;
			}
		})
		.catch(function () {
			showEmpty();
		});
})();
