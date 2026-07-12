import { initModelEmbed } from './model-embed.js?v=6';

const container = document.getElementById('home-hero-model');
if (container) {
	initModelEmbed(container, './assets/horde-up.glb', { size: 'auto' });

	/*
	 * Grab-to-rotate wiring. The widget/canvas is pointer-events: none in
	 * css/index.css so the enlarged, overlapping canvas never blocks the
	 * news links beneath it. That also kills the drag controls, so this
	 * invisible proxy covers ONLY the model's own column (the central,
	 * non-overlapping area) and forwards pointer-downs to the canvas —
	 * model-embed.js starts its drag from the canvas pointerdown and then
	 * tracks pointermove/pointerup at the window level, so a single
	 * forwarded event restores full rotation. In the overlap zones the
	 * links win, by design.
	 */
	const column = container.parentElement;
	const canvas = container.querySelector('canvas');
	if (column && canvas) {
		const proxy = document.createElement('div');
		proxy.className = 'community__model-grab';
		proxy.setAttribute('aria-hidden', 'true');
		column.appendChild(proxy);

		proxy.addEventListener('pointerdown', (event) => {
			canvas.dispatchEvent(new PointerEvent('pointerdown', {
				bubbles: true,
				cancelable: true,
				pointerId: event.pointerId,
				pointerType: event.pointerType,
				isPrimary: event.isPrimary,
				clientX: event.clientX,
				clientY: event.clientY,
				button: event.button,
				buttons: event.buttons,
			}));
		});
	}
}
