// Rig Check — dead-simple, isolated three.js FBX viewer.
// STANDARD three.js path only: FBXLoader + AnimationMixer + SkinnedMesh.
// No bind, no shim, no grounding, no retarget. The rig is shown exactly as loaded.

import * as THREE from 'three';
import { FBXLoader } from './lib/loaders/FBXLoader.js';
import { OrbitControls } from './lib/controls/OrbitControls.js';

const canvas = document.getElementById('scene');
const rigSelect = document.getElementById('rigSelect');
const clipSelect = document.getElementById('clipSelect');
const pauseBtn = document.getElementById('pauseBtn');
const fileInput = document.getElementById('fileInput');
const statusEl = document.getElementById('status');

// --- renderer / scene / camera ---
const renderer = new THREE.WebGLRenderer({ canvas, antialias: true });
renderer.setPixelRatio(Math.min(window.devicePixelRatio, 2));

const scene = new THREE.Scene();
scene.background = new THREE.Color(0x0a0a0c);

const camera = new THREE.PerspectiveCamera(50, 1, 0.1, 5000);
camera.position.set(150, 160, 320);

const controls = new OrbitControls(camera, renderer.domElement);
controls.target.set(0, 90, 0);
controls.enableDamping = true;

// --- lights (soft, neutral, matte read) ---
scene.add(new THREE.HemisphereLight(0xffffff, 0x333338, 1.1));
const key = new THREE.DirectionalLight(0xffffff, 1.4);
key.position.set(200, 400, 300);
scene.add(key);
const fill = new THREE.DirectionalLight(0xffffff, 0.5);
fill.position.set(-250, 200, -200);
scene.add(fill);

// --- ground grid at y=0 for floor-contact reference ---
const grid = new THREE.GridHelper(600, 60, 0x444450, 0x22222a);
scene.add(grid);
scene.add(new THREE.AxesHelper(30));

// --- state ---
const loader = new FBXLoader();
const clock = new THREE.Clock();
let current = null;   // loaded FBX root
let mixer = null;     // AnimationMixer
let action = null;    // current AnimationAction
let clips = [];       // available clips
let paused = false;

function setStatus(msg) { statusEl.textContent = msg; }

function clearCurrent() {
  if (mixer) { mixer.stopAllAction(); mixer = null; }
  action = null;
  clips = [];
  if (current) { scene.remove(current); current = null; }
  clipSelect.innerHTML = '<option value="">— none —</option>';
  pauseBtn.disabled = true;
  paused = false;
  pauseBtn.textContent = 'Pause';
}

function playClip(index) {
  if (!mixer || !clips[index]) return;
  if (action) action.stop();
  action = mixer.clipAction(clips[index]);
  action.reset().play();
  paused = false;
  pauseBtn.textContent = 'Pause';
  pauseBtn.disabled = false;
}

function onLoaded(obj) {
  clearCurrent();
  current = obj;
  scene.add(obj);   // exactly as loaded — no transform of any kind

  clips = obj.animations || [];
  if (clips.length) {
    mixer = new THREE.AnimationMixer(obj);
    clipSelect.innerHTML = '';
    clips.forEach((c, i) => {
      const opt = document.createElement('option');
      opt.value = String(i);
      opt.textContent = c.name || `clip ${i}`;
      clipSelect.appendChild(opt);
    });
    playClip(0);
    setStatus(`loaded — ${clips.length} clip(s)`);
  } else {
    setStatus('loaded — static (no clips)');
  }
}

function loadUrl(url) {
  setStatus('loading…');
  loader.load(
    url,
    onLoaded,
    undefined,
    (err) => { setStatus('load error — see console'); console.error(err); }
  );
}

function loadFile(file) {
  setStatus('loading…');
  const reader = new FileReader();
  reader.onload = () => {
    try {
      const obj = loader.parse(reader.result, '');
      onLoaded(obj);
    } catch (err) {
      setStatus('parse error — see console');
      console.error(err);
    }
  };
  reader.readAsArrayBuffer(file);
}

// --- UI ---
rigSelect.addEventListener('change', () => loadUrl(rigSelect.value));
clipSelect.addEventListener('change', () => {
  const i = clipSelect.value;
  if (i !== '') playClip(Number(i));
});
pauseBtn.addEventListener('click', () => {
  if (!action) return;
  paused = !paused;
  action.paused = paused;
  pauseBtn.textContent = paused ? 'Play' : 'Pause';
});
fileInput.addEventListener('change', () => {
  if (fileInput.files[0]) loadFile(fileInput.files[0]);
});

// --- resize / render loop ---
function resize() {
  const w = window.innerWidth, h = window.innerHeight;
  renderer.setSize(w, h, false);
  camera.aspect = w / h;
  camera.updateProjectionMatrix();
}
window.addEventListener('resize', resize);
resize();

function animate() {
  requestAnimationFrame(animate);
  const dt = clock.getDelta();
  if (mixer && !paused) mixer.update(dt);
  controls.update();
  renderer.render(scene, camera);
}
animate();

// initial load
loadUrl(rigSelect.value);
