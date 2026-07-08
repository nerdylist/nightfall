import { initModelEmbed } from './model-embed.js?v=3';

const container = document.getElementById('home-hero-model');
if (container) {
	initModelEmbed(container, './assets/horde-up.glb', { size: 'auto' });
}
