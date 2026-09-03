/**
 * Embed sob demanda do YouTube — porte de YouTubeLazyEmbed.
 *
 * A pagina carrega apenas o poster; o iframe entra no clique. Usa
 * youtube-nocookie, como no React.
 */
(function () {
	'use strict';

	function activate(wrapper) {
		var videoId = wrapper.dataset.videoId;

		if (!videoId || wrapper.dataset.contornoVideoLoaded === '1') {
			return;
		}

		wrapper.dataset.contornoVideoLoaded = '1';

		var iframe = document.createElement('iframe');
		iframe.src =
			'https://www.youtube-nocookie.com/embed/' +
			encodeURIComponent(videoId) +
			'?autoplay=1&rel=0&modestbranding=1';
		iframe.title = wrapper.dataset.videoTitle || 'Video';
		iframe.allow =
			'accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture; web-share';
		iframe.allowFullscreen = true;
		iframe.setAttribute('referrerpolicy', 'strict-origin-when-cross-origin');

		var trigger = wrapper.querySelector('[data-contorno-video-play]');
		if (trigger) {
			trigger.remove();
		}

		wrapper.appendChild(iframe);
	}

	function setup(wrapper) {
		if (wrapper.dataset.contornoVideoReady === '1') {
			return;
		}
		wrapper.dataset.contornoVideoReady = '1';

		var trigger = wrapper.querySelector('[data-contorno-video-play]');

		if (!trigger) {
			return;
		}

		trigger.addEventListener('click', function () {
			activate(wrapper);
		});
	}

	function init() {
		Array.prototype.forEach.call(
			document.querySelectorAll('[data-contorno-video]'),
			setup
		);
	}

	if (document.readyState !== 'loading') {
		init();
	} else {
		document.addEventListener('DOMContentLoaded', init);
	}

	document.addEventListener('contorno:refresh', init);
})();
