import { initModelEmbed } from './model-embed.js?v=6';

const container = document.getElementById('shop-model');
if (container) {
    initModelEmbed(container, './assets/shopper.glb', {
        size: 'auto',
        spotlight: true,
        ambient: 1.6,
        groundShadow: true,
    });
}
