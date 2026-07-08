import * as THREE from 'three';
import { GLTFLoader } from './vendor/loaders/GLTFLoader.js';

const AUTO_ROTATE_SPEED = 0.2; // radians/sec
const ROTATION_SPEED = 0.005;

function buildWidgetDom(container) {
	container.classList.add('me-widget');

	const canvas = document.createElement('canvas');
	canvas.className = 'me-canvas';
	container.appendChild(canvas);

	const statusEl = document.createElement('div');
	statusEl.className = 'me-status';
	statusEl.hidden = true;
	container.appendChild(statusEl);

	const loaderEl = document.createElement('div');
	loaderEl.className = 'me-loader';
	loaderEl.hidden = true;

	const loaderLabelEl = document.createElement('div');
	loaderLabelEl.className = 'me-loader-label';
	loaderLabelEl.textContent = 'Loading model…';
	loaderEl.appendChild(loaderLabelEl);

	const loaderTrackEl = document.createElement('div');
	loaderTrackEl.className = 'me-loader-track';

	const loaderBarEl = document.createElement('div');
	loaderBarEl.className = 'me-loader-bar';
	loaderTrackEl.appendChild(loaderBarEl);
	loaderEl.appendChild(loaderTrackEl);

	const loaderPercentEl = document.createElement('div');
	loaderPercentEl.className = 'me-loader-percent';
	loaderEl.appendChild(loaderPercentEl);

	container.appendChild(loaderEl);

	return {
		canvas,
		statusEl,
		loaderEl,
		loaderLabelEl,
		loaderTrackEl,
		loaderBarEl,
		loaderPercentEl,
	};
}

export function initModelEmbed(container, modelUrl) {
	const dom = buildWidgetDom(container);
	const { canvas, statusEl, loaderEl, loaderLabelEl, loaderTrackEl, loaderBarEl, loaderPercentEl } = dom;

	function showStatus(message) {
		statusEl.innerHTML = message;
		statusEl.hidden = false;
	}

	function hideStatus() {
		statusEl.hidden = true;
	}

	function showLoader() {
		loaderEl.hidden = false;
		loaderEl.classList.remove('me-loader--fade-out');
	}

	function hideLoader() {
		loaderEl.classList.add('me-loader--fade-out');
		window.setTimeout(() => {
			loaderEl.hidden = true;
		}, 250);
	}

	function setLoaderIndeterminate() {
		loaderTrackEl.classList.add('me-loader-track--indeterminate');
		loaderLabelEl.textContent = 'Loading model…';
		loaderPercentEl.textContent = '';
	}

	function setLoaderProgress(fraction) {
		loaderTrackEl.classList.remove('me-loader-track--indeterminate');
		const percent = Math.round(Math.min(1, Math.max(0, fraction)) * 100);
		loaderBarEl.style.width = percent + '%';
		loaderLabelEl.textContent = 'Loading model…';
		loaderPercentEl.textContent = percent + '%';
	}

	function getSize() {
		const width = container.clientWidth || 1;
		const height = container.clientHeight || width || 1;
		return { width, height };
	}

	let renderer;
	try {
		renderer = new THREE.WebGLRenderer({ canvas, antialias: true, alpha: true });
	} catch (err) {
		showStatus(
			"<strong>3D preview unavailable</strong>Your browser couldn't create a WebGL context. Try enabling hardware acceleration or updating your GPU drivers."
		);
		renderer = null;
	}

	const { width: initWidth, height: initHeight } = getSize();

	const scene = new THREE.Scene();

	const camera = new THREE.PerspectiveCamera(45, initWidth / initHeight, 0.01, 5000);
	camera.position.set(0, 0, 5);

	if (renderer) {
		renderer.setClearColor(0x000000, 0);
		renderer.setPixelRatio(Math.min(window.devicePixelRatio, 2));
		renderer.setSize(initWidth, initHeight, false);
		renderer.outputColorSpace = THREE.SRGBColorSpace;
	}

	// Lighting: neutral ambient + directional so any model is clearly visible.
	const ambientLight = new THREE.AmbientLight(0xffffff, 0.9);
	scene.add(ambientLight);

	const keyLight = new THREE.DirectionalLight(0xffffff, 1.4);
	keyLight.position.set(3, 5, 4);
	scene.add(keyLight);

	const fillLight = new THREE.DirectionalLight(0xffffff, 0.5);
	fillLight.position.set(-4, -2, -3);
	scene.add(fillLight);

	// A pivot group we rotate horizontally in response to drag input.
	const pivot = new THREE.Group();
	scene.add(pivot);

	// --- Orbit state (Y-axis only), scoped per-instance ---
	let isDragging = false;
	let previousPointerX = 0;
	let cameraDistance = 5;

	function onPointerDown(event) {
		isDragging = true;
		previousPointerX = event.clientX;
		canvas.classList.add('dragging');
		canvas.setPointerCapture(event.pointerId);
	}

	function onPointerMove(event) {
		if (!isDragging) return;
		const deltaX = event.clientX - previousPointerX;
		previousPointerX = event.clientX;
		pivot.rotation.y += deltaX * ROTATION_SPEED;
	}

	function onPointerUp(event) {
		isDragging = false;
		canvas.classList.remove('dragging');
		if (event && canvas.hasPointerCapture(event.pointerId)) {
			canvas.releasePointerCapture(event.pointerId);
		}
	}

	canvas.addEventListener('pointerdown', onPointerDown);
	window.addEventListener('pointermove', onPointerMove);
	window.addEventListener('pointerup', onPointerUp);
	window.addEventListener('pointercancel', onPointerUp);

	function onResize() {
		if (!renderer) return;
		const { width, height } = getSize();
		camera.aspect = width / height;
		camera.updateProjectionMatrix();
		renderer.setSize(width, height, false);
	}

	const resizeObserver = new ResizeObserver(onResize);
	resizeObserver.observe(container);

	function frameCameraToObject(object) {
		const box = new THREE.Box3().setFromObject(object);
		const size = box.getSize(new THREE.Vector3());
		const center = box.getCenter(new THREE.Vector3());

		// Recenter the object within the pivot so it rotates around its own middle.
		object.position.sub(center);

		const boundingSphereRadius = size.length() / 2 || 1;
		const fitDistance =
			(boundingSphereRadius / Math.sin((Math.PI * camera.fov) / 360)) * 1.2;

		cameraDistance = fitDistance;
		camera.near = Math.max(fitDistance / 100, 0.01);
		camera.far = fitDistance * 100;
		camera.updateProjectionMatrix();
		camera.position.set(0, 0, cameraDistance);
		camera.lookAt(0, 0, 0);
	}

	function loadModel(path) {
		showLoader();
		setLoaderIndeterminate();

		const loader = new GLTFLoader();
		loader.load(
			path,
			(gltf) => {
				pivot.add(gltf.scene);
				frameCameraToObject(gltf.scene);
				hideStatus();
				hideLoader();
			},
			(event) => {
				if (event.lengthComputable && event.total > 0) {
					setLoaderProgress(event.loaded / event.total);
				} else {
					setLoaderIndeterminate();
				}
			},
			() => {
				hideLoader();
				showStatus('<strong>Couldn’t load 3D model</strong>Please try again later.');
			}
		);
	}

	const clock = new THREE.Clock();
	let animationFrameId = null;

	function animate() {
		animationFrameId = requestAnimationFrame(animate);
		const delta = clock.getDelta();
		if (!isDragging) {
			pivot.rotation.y += AUTO_ROTATE_SPEED * delta;
		}
		renderer.render(scene, camera);
	}

	if (renderer) {
		loadModel(modelUrl);
		animate();
	}

	function dispose() {
		if (animationFrameId !== null) cancelAnimationFrame(animationFrameId);
		resizeObserver.disconnect();
		window.removeEventListener('pointermove', onPointerMove);
		window.removeEventListener('pointerup', onPointerUp);
		window.removeEventListener('pointercancel', onPointerUp);
		canvas.removeEventListener('pointerdown', onPointerDown);
		if (renderer) renderer.dispose();
	}

	return { dispose };
}

export default initModelEmbed;
