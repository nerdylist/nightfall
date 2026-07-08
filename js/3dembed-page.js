import { initModelEmbed } from './model-embed.js';

const container = document.getElementById('model-embed');
if (container) {
	initModelEmbed(container, './assets/horde-up.glb');
}
