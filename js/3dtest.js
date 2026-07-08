import * as THREE from 'three';
import { GLTFLoader } from './vendor/loaders/GLTFLoader.js';

const canvas = document.getElementById('viewer');
const statusEl = document.getElementById('status');
const loaderEl = document.getElementById('loader');
const loaderLabelEl = document.getElementById('loader-label');
const loaderTrackEl = loaderEl ? loaderEl.querySelector('.loader-track') : null;
const loaderBarEl = document.getElementById('loader-bar');
const loaderPercentEl = document.getElementById('loader-percent');

const params = new URLSearchParams(window.location.search);
const modelPath = params.get('model') || './assets/horde-up.glb';

function showStatus(message) {
	statusEl.innerHTML = message;
	statusEl.hidden = false;
}

function hideStatus() {
	statusEl.hidden = true;
}

function showLoader() {
	if (!loaderEl) return;
	loaderEl.hidden = false;
	loaderEl.classList.remove('fade-out');
}

function hideLoader() {
	if (!loaderEl) return;
	loaderEl.classList.add('fade-out');
	window.setTimeout(() => {
		loaderEl.hidden = true;
	}, 250);
}

function setLoaderIndeterminate() {
	if (!loaderTrackEl) return;
	loaderTrackEl.classList.add('indeterminate');
	if (loaderLabelEl) loaderLabelEl.textContent = 'Loading model…';
	if (loaderPercentEl) loaderPercentEl.textContent = '';
}

function setLoaderProgress(fraction) {
	if (!loaderTrackEl || !loaderBarEl) return;
	loaderTrackEl.classList.remove('indeterminate');
	const percent = Math.round(Math.min(1, Math.max(0, fraction)) * 100);
	loaderBarEl.style.width = percent + '%';
	if (loaderLabelEl) loaderLabelEl.textContent = 'Loading model…';
	if (loaderPercentEl) loaderPercentEl.textContent = percent + '%';
}

let renderer;
try {
	renderer = new THREE.WebGLRenderer({ canvas, antialias: true });
} catch (err) {
	showStatus(
		"<strong>Can't start 3D viewer</strong>Your browser couldn't create a WebGL context. Enable hardware acceleration in your browser settings (<code>chrome://gpu</code> to diagnose) or update your GPU drivers, then reload."
	);
	renderer = null;
}

const scene = new THREE.Scene();
scene.background = new THREE.Color(0x0a0a0a);

const camera = new THREE.PerspectiveCamera(
	45,
	window.innerWidth / window.innerHeight,
	0.01,
	5000
);
camera.position.set(0, 0, 5);

if (renderer) {
	renderer.setPixelRatio(Math.min(window.devicePixelRatio, 2));
	renderer.setSize(window.innerWidth, window.innerHeight);
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

// --- Orbit state (Y-axis only) ---
let isDragging = false;
let previousPointerX = 0;
let cameraDistance = 5;
let targetDistance = 5;
let minDistance = 0.1;
let maxDistance = 100;

function onPointerDown(event) {
	isDragging = true;
	previousPointerX = event.clientX;
	canvas.classList.add('dragging');
}

function onPointerMove(event) {
	if (!isDragging) return;
	const deltaX = event.clientX - previousPointerX;
	previousPointerX = event.clientX;
	const rotationSpeed = 0.005;
	pivot.rotation.y += deltaX * rotationSpeed;
}

function onPointerUp() {
	isDragging = false;
	canvas.classList.remove('dragging');
}

canvas.addEventListener('pointerdown', onPointerDown);
window.addEventListener('pointermove', onPointerMove);
window.addEventListener('pointerup', onPointerUp);
window.addEventListener('pointercancel', onPointerUp);

canvas.addEventListener(
	'wheel',
	(event) => {
		event.preventDefault();
		const zoomSpeed = 0.0015;
		targetDistance *= 1 + event.deltaY * zoomSpeed;
		targetDistance = Math.min(maxDistance, Math.max(minDistance, targetDistance));
	},
	{ passive: false }
);

function updateCameraDistance() {
	cameraDistance += (targetDistance - cameraDistance) * 0.15;
	camera.position.set(0, camera.position.y, cameraDistance);
	camera.lookAt(0, camera.position.y, 0);
}

function onWindowResize() {
	if (!renderer) return;
	camera.aspect = window.innerWidth / window.innerHeight;
	camera.updateProjectionMatrix();
	renderer.setSize(window.innerWidth, window.innerHeight);
}
window.addEventListener('resize', onWindowResize);

function frameCameraToObject(object) {
	const box = new THREE.Box3().setFromObject(object);
	const size = box.getSize(new THREE.Vector3());
	const center = box.getCenter(new THREE.Vector3());

	// Recenter the object within the pivot so it rotates around its own middle.
	object.position.sub(center);

	const boundingSphereRadius = size.length() / 2 || 1;
	const fitDistance =
		(boundingSphereRadius / Math.sin((Math.PI * camera.fov) / 360)) * 1.2;

	targetDistance = fitDistance;
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
			showStatus(
				'<strong>No model found</strong>Save your Meshy export as <code>web/assets/horde-up.glb</code> and reload this page.'
			);
		}
	);
}

function animate() {
	requestAnimationFrame(animate);
	updateCameraDistance();
	renderer.render(scene, camera);
}

if (renderer) {
	loadModel(modelPath);
	animate();
}
