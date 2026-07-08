import { initModelEmbed } from './model-embed.js?v=3';

const container = document.getElementById('model-embed');
if (container) {
	initModelEmbed(container, './assets/horde-up.glb', { size: 'auto' });
}
