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

export function initModelEmbed(container, modelUrl, options = {}) {
	const dom = buildWidgetDom(container);
	const { canvas, statusEl, loaderEl, loaderLabelEl, loaderTrackEl, loaderBarEl, loaderPercentEl } = dom;

	// Resolve the configured size once at init time. Accepts a number in
	// [1, 100] (percentage of the padded container the model should fill),
	// or 'auto' / undefined / null / non-numeric, all of which mean "100"
	// (full size, current default behavior).
	let sizePercent = 100;
	if (typeof options.size === 'number' && Number.isFinite(options.size)) {
		sizePercent = Math.min(100, Math.max(1, options.size));
	}

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
	let currentHalfExtents = null; // { halfH, halfW } — Y-rotation-aware fit extents

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
		renderer.setSize(width, height, false);
		updateCameraFraming();
	}

	const resizeObserver = new ResizeObserver(onResize);
	resizeObserver.observe(container);

	// Recomputes camera aspect, fit distance (against both vertical and
	// horizontal FOV, with 20px padding on every side), near/far, and
	// position/lookAt. Safe to call before a model has loaded (no-ops the
	// distance/position portion in that case) and on every resize.
	function updateCameraFraming() {
		const { width, height } = getSize();
		camera.aspect = width / height;

		if (!currentHalfExtents) {
			camera.updateProjectionMatrix();
			return;
		}

		const PADDING = 20; // px, per side
		// Clamp so the padded viewport never goes to zero/negative for tiny containers.
		const paddedWidth = Math.max(width - PADDING * 2, width * 0.1);
		const paddedHeight = Math.max(height - PADDING * 2, height * 0.1);

		const vFov = (camera.fov * Math.PI) / 180; // full vertical FOV, radians
		const fullAspect = width / height;
		const hFov = 2 * Math.atan(Math.tan(vFov / 2) * fullAspect); // full horizontal FOV, radians

		// Y-rotation-aware fit: the model only ever spins around Y, so its
		// vertical extent is constant (halfH) while its horizontal footprint
		// (as seen by the camera) varies between its narrowest and widest
		// side; halfW is the realistic worst case — the model's largest
		// horizontal extent (its widest side, not the XZ diagonal) — since
		// the diagonal only faces the camera at a fleeting instant mid-spin.
		const { halfH, halfW } = currentHalfExtents;

		const vFitDistanceFull = halfH / Math.tan(vFov / 2);
		const hFitDistanceFull = halfW / Math.tan(hFov / 2);

		// Inflate each axis's fit distance proportionally to how much smaller
		// the padded viewport is than the full viewport on that axis.
		const vFitDistance = vFitDistanceFull * (height / paddedHeight);
		const hFitDistance = hFitDistanceFull * (width / paddedWidth);

		// Use realistic extents (no diagonal depth allowance) plus a modest
		// safety margin — an extremity may occasionally graze the edge
		// mid-rotation, which is accepted in exchange for a larger model.
		const fitDistance = Math.max(vFitDistance, hFitDistance) * 1.04;

		// Scale the fit distance by the configured size: a smaller
		// sizePercent pushes the camera further away, making the model
		// occupy a smaller fraction of the padded container.
		const scaledFitDistance = fitDistance * (100 / sizePercent);

		// Near/far still need to account for the worst-case rotated diagonal
		// footprint, since the model's nearest point to the camera can swing
		// as close as (distance - diagHalf) during rotation, regardless of
		// the realistic-extent framing distance used above.
		const { diagHalf } = currentHalfExtents;

		cameraDistance = scaledFitDistance;
		camera.near = Math.max((scaledFitDistance - diagHalf) * 0.5, 0.01);
		camera.far = scaledFitDistance + diagHalf * 4;
		if (camera.near >= camera.far) {
			camera.near = 0.01;
			camera.far = Math.max(scaledFitDistance * 2, camera.near + 1);
		}
		camera.updateProjectionMatrix();
		camera.position.set(0, 0, cameraDistance);
		camera.lookAt(0, 0, 0);
	}

	function frameCameraToObject(object) {
		// Measure and recenter BEFORE parenting into the pivot. The pivot
		// auto-rotates continuously from the moment the widget starts (see
		// animate()), so by the time an async GLTF load resolves, pivot may
		// already be sitting at an arbitrary non-zero rotation. Computing the
		// box on `object` while it is still unparented (object.parent is
		// null) guarantees object.matrixWorld === object.matrix, i.e. the
		// measurement is taken in the model's own undisturbed local space —
		// decoupled from the pivot's live rotation — so the recenter offset
		// and the pivot's rotation origin always agree. Force a matrix
		// update first so the box isn't computed from a stale matrix.
		object.updateWorldMatrix(true, true);
		const box = new THREE.Box3().setFromObject(object);
		const size = box.getSize(new THREE.Vector3());
		const center = box.getCenter(new THREE.Vector3());

		// Recenter the object around its own bounding-box middle so that
		// middle coincides with the pivot's origin — which is both the
		// rotation pivot and the camera's lookAt target.
		object.position.sub(center);

		const halfH = size.y / 2;
		// Realistic max horizontal extent during Y rotation — the model's
		// widest side, not the XZ diagonal (see updateCameraFraming()).
		const halfW = Math.max(size.x, size.z) / 2;
		// Diagonal half-extent of the XZ footprint — used only for near/far
		// clipping-plane safety, since the worst-case rotated corner can
		// bring the model this close to the camera regardless of the
		// realistic-extent framing distance.
		const diagHalf = Math.sqrt((size.x / 2) ** 2 + (size.z / 2) ** 2);
		currentHalfExtents = { halfH: halfH || 1, halfW: halfW || 1, diagHalf: diagHalf || 1 };
		updateCameraFraming();
	}

	function loadModel(path) {
		showLoader();
		setLoaderIndeterminate();

		const loader = new GLTFLoader();
		loader.load(
			path,
			(gltf) => {
				// Measure + recenter first, while gltf.scene is still
				// unparented, then attach to the (possibly already
				// auto-rotating) pivot. See frameCameraToObject() for why
				// the ordering matters.
				frameCameraToObject(gltf.scene);
				pivot.add(gltf.scene);
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
