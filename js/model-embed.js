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
	let currentHalfExtents = null; // { cylR, halfH } — exact Y-rotation bounding-cylinder fit extents

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

		// Shrink each axis's usable FOV angle proportionally to how much
		// smaller the padded viewport is than the full viewport on that
		// axis — same padded-FOV mechanism as before (previously expressed
		// as inflating the fit *distance* by height/paddedHeight; expressed
		// here as an equivalent angle so the exact cylinder formulas below
		// stay exact), so fitting the model into this smaller angle leaves
		// the 20px padding margin on every side.
		const vFovPadded = 2 * Math.atan(Math.tan(vFov / 2) * (paddedHeight / height));
		const hFovPadded = 2 * Math.atan(Math.tan(hFov / 2) * (paddedWidth / width));

		// Exact bounding-cylinder fit: the model only ever spins around Y, so
		// every point traces a circle of some radius (<= cylR) in the XZ
		// plane at some height (within [-halfH, halfH] of the origin, using
		// the larger of the top/bottom extents since the camera looks at
		// the origin, not the vertical center of mass).
		const { cylR, halfH } = currentHalfExtents;

		// Horizontal: worst case is the cylinder's equatorial silhouette,
		// radius cylR, as seen edge-on — requires the half horizontal FOV to
		// subtend at least asin(cylR / d).
		const hFitDistance = cylR / Math.sin(hFovPadded / 2);

		// Vertical: worst case is the top/bottom rim at the point on the
		// cylinder nearest the camera (z = cylR toward the camera), which
		// sits (d - cylR) away along the view axis and halfH off-axis.
		const vFitDistance = halfH / Math.tan(vFovPadded / 2) + cylR;

		// The cylinder bound is exact, so no extra fudge factor is needed;
		// keep a hairline safety margin against floating-point rounding.
		const fitDistance = Math.max(hFitDistance, vFitDistance) * 1.01;

		// Scale the fit distance by the configured size: a smaller
		// sizePercent pushes the camera further away, making the model
		// occupy a smaller fraction of the padded container.
		const scaledFitDistance = fitDistance * (100 / sizePercent);

		cameraDistance = scaledFitDistance;
		camera.near = Math.max((scaledFitDistance - cylR) * 0.5, 0.01);
		camera.far = scaledFitDistance + cylR * 4;
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
		const center = box.getCenter(new THREE.Vector3());

		// Recenter the object around its own bounding-box middle so that
		// middle coincides with the pivot's origin — which is both the
		// rotation pivot and the camera's lookAt target.
		object.position.sub(center);

		// Re-apply world matrices so vertex positions read below are in the
		// recentered frame (object.position changed above).
		object.updateWorldMatrix(true, true);

		// Exact bounding-cylinder fit around the Y axis through the origin:
		// walk every vertex of every mesh, transform to world (recentered)
		// space, and track the furthest XZ radius and the tallest/deepest Y
		// extent. This guarantees no rotation angle can ever clip, unlike
		// the bounding-box estimate (which underestimates the true swept
		// radius for non-box-shaped meshes and over/under-estimates depending
		// on rotation phase).
		let cylR = 0;
		let yMin = Infinity;
		let yMax = -Infinity;
		const v = new THREE.Vector3();

		object.traverse((node) => {
			if (!node.isMesh || !node.geometry) return;
			const positionAttr = node.geometry.getAttribute('position');
			if (!positionAttr) return;
			node.updateWorldMatrix(true, false);
			const matrixWorld = node.matrixWorld;
			for (let i = 0; i < positionAttr.count; i++) {
				v.fromBufferAttribute(positionAttr, i);
				v.applyMatrix4(matrixWorld);
				const r = Math.sqrt(v.x * v.x + v.z * v.z);
				if (r > cylR) cylR = r;
				if (v.y < yMin) yMin = v.y;
				if (v.y > yMax) yMax = v.y;
			}
		});

		if (!Number.isFinite(yMin) || !Number.isFinite(yMax)) {
			yMin = 0;
			yMax = 0;
		}

		// Camera looks at the origin, not the vertical midpoint, so use the
		// larger of the top/bottom extents — otherwise the shorter side
		// would under-frame and the taller side would clip.
		const halfH = Math.max(Math.abs(yMin), Math.abs(yMax));

		currentHalfExtents = { cylR: cylR || 1, halfH: halfH || 1 };
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
