<script setup lang="ts">
import type * as Three from 'three';
import { onBeforeUnmount, onMounted, ref } from 'vue';

const props = withDefaults(
    defineProps<{
        mode?: 'hero' | 'compact';
        class?: string;
    }>(),
    {
        mode: 'hero',
        class: '',
    },
);

const host = ref<HTMLDivElement | null>(null);
const ready = ref(false);

let disposed = false;
let renderer: Three.WebGLRenderer | null = null;
let scene: Three.Scene | null = null;
let camera: Three.PerspectiveCamera | null = null;
let group: Three.Group | null = null;
let geometry: Three.BufferGeometry | null = null;
let material: Three.PointsMaterial | null = null;
let lineGeometry: Three.BufferGeometry | null = null;
let lineMaterial: Three.LineBasicMaterial | null = null;
let resizeObserver: ResizeObserver | null = null;
let intersectionObserver: IntersectionObserver | null = null;
let animationFrame = 0;
let running = false;
let onScreen = true;
let elapsed = 0;
let lastFrame = 0;
let bases = new Float32Array(0);
let pointAttribute: Three.BufferAttribute | null = null;
let lineAttribute: Three.BufferAttribute | null = null;

function webGLAvailable(): boolean {
    if (typeof document === 'undefined') {
        return false;
    }

    try {
        const canvas = document.createElement('canvas');
        return Boolean(
            canvas.getContext('webgl2') ?? canvas.getContext('webgl'),
        );
    } catch {
        return false;
    }
}

function lowPower(): boolean {
    if (typeof navigator === 'undefined') {
        return true;
    }

    const nav = navigator as Navigator & { deviceMemory?: number };
    const cores = nav.hardwareConcurrency ?? 4;
    const memory = nav.deviceMemory ?? 4;

    return cores <= 4 || memory <= 4;
}

function updateSignal(time: number): void {
    if (!pointAttribute || !lineAttribute) {
        return;
    }

    const pointCount = pointAttribute.count;

    for (let index = 0; index < pointCount; index += 1) {
        const x = bases[index * 2] ?? 0;
        const y = bases[index * 2 + 1] ?? 0;
        const radius = Math.sqrt(x * x + y * y);
        const envelope = Math.exp(-radius * 1.12);
        const primary = Math.sin(radius * 9.2 - time * 1.25) * 0.3 * envelope;
        const interference =
            Math.sin(x * 4.6 + time * 0.46) *
            Math.cos(y * 3.8 - time * 0.32) *
            0.075;
        pointAttribute.setZ(index, primary + interference);
    }

    pointAttribute.needsUpdate = true;

    const lineCount = lineAttribute.count;

    for (let index = 0; index < lineCount; index += 1) {
        const x = -1.4 + (index / (lineCount - 1)) * 2.8;
        const envelope = Math.exp(-Math.abs(x) * 0.72);
        const y = Math.sin(x * 4.8 - time * 1.1) * 0.105 * envelope;
        lineAttribute.setXYZ(index, x, y, 0.05);
    }

    lineAttribute.needsUpdate = true;

    if (group) {
        group.rotation.y = Math.sin(time * 0.18) * 0.2;
        group.rotation.x = -0.14 + Math.sin(time * 0.11) * 0.035;
    }
}

function renderFrame(now: number): void {
    if (!running || disposed || !renderer || !scene || !camera) {
        return;
    }

    const delta = Math.min((now - lastFrame) / 1000, 0.05);
    lastFrame = now;
    elapsed += delta;
    updateSignal(elapsed);
    renderer.render(scene, camera);
    animationFrame = window.requestAnimationFrame(renderFrame);
}

function setRunning(next: boolean): void {
    if (next === running || disposed) {
        return;
    }

    running = next;

    if (next) {
        lastFrame = performance.now();
        animationFrame = window.requestAnimationFrame(renderFrame);
        return;
    }

    window.cancelAnimationFrame(animationFrame);
    animationFrame = 0;
}

function resize(): void {
    if (!host.value || !renderer || !camera) {
        return;
    }

    const width = Math.max(host.value.clientWidth, 1);
    const height = Math.max(host.value.clientHeight, 1);
    renderer.setSize(width, height, false);
    camera.aspect = width / height;
    camera.updateProjectionMatrix();

    if (!running) {
        renderer.render(scene!, camera);
    }
}

function handleVisibility(): void {
    setRunning(
        onScreen &&
            !document.hidden &&
            !window.matchMedia('(prefers-reduced-motion: reduce)').matches,
    );
}

async function initialise(): Promise<void> {
    if (!host.value || !webGLAvailable()) {
        return;
    }

    const reduced = window.matchMedia(
        '(prefers-reduced-motion: reduce)',
    ).matches;
    const compact = props.mode === 'compact';
    const staticRender = reduced || compact || lowPower();

    try {
        const three = await import('three');

        if (disposed || !host.value) {
            return;
        }

        scene = new three.Scene();
        camera = new three.PerspectiveCamera(42, 1, 0.1, 20);
        camera.position.set(0, 0, 2.9);

        group = new three.Group();
        scene.add(group);

        const columns = compact ? 43 : 67;
        const rows = compact ? 23 : 35;
        const pointCount = columns * rows;
        const positions = new Float32Array(pointCount * 3);
        bases = new Float32Array(pointCount * 2);

        for (let row = 0; row < rows; row += 1) {
            for (let column = 0; column < columns; column += 1) {
                const index = row * columns + column;
                const x = (column / (columns - 1) - 0.5) * 2.7;
                const y = (row / (rows - 1) - 0.5) * 1.75;
                bases[index * 2] = x;
                bases[index * 2 + 1] = y;
                positions[index * 3] = x;
                positions[index * 3 + 1] = y;
                positions[index * 3 + 2] = 0;
            }
        }

        geometry = new three.BufferGeometry();
        pointAttribute = new three.BufferAttribute(positions, 3);
        geometry.setAttribute('position', pointAttribute);

        material = new three.PointsMaterial({
            color: 0xf4f4f4,
            size: compact ? 0.014 : 0.018,
            sizeAttenuation: true,
            transparent: true,
            opacity: 0.82,
            depthWrite: false,
        });

        group.add(new three.Points(geometry, material));

        const lineCount = compact ? 72 : 128;
        const linePositions = new Float32Array(lineCount * 3);
        lineGeometry = new three.BufferGeometry();
        lineAttribute = new three.BufferAttribute(linePositions, 3);
        lineGeometry.setAttribute('position', lineAttribute);
        lineMaterial = new three.LineBasicMaterial({
            color: 0xf4f4f4,
            transparent: true,
            opacity: 0.42,
        });
        group.add(new three.Line(lineGeometry, lineMaterial));

        renderer = new three.WebGLRenderer({
            alpha: true,
            antialias: false,
            powerPreference: 'low-power',
        });
        renderer.setPixelRatio(Math.min(window.devicePixelRatio || 1, 1.75));
        renderer.setClearColor(0x000000, 0);
        renderer.domElement.setAttribute('aria-hidden', 'true');
        renderer.domElement.style.width = '100%';
        renderer.domElement.style.height = '100%';
        renderer.domElement.style.display = 'block';
        host.value.appendChild(renderer.domElement);

        resize();
        updateSignal(0);
        renderer.render(scene, camera);
        ready.value = true;

        resizeObserver = new ResizeObserver(resize);
        resizeObserver.observe(host.value);

        intersectionObserver = new IntersectionObserver(
            (entries) => {
                onScreen = entries.some((entry) => entry.isIntersecting);
                handleVisibility();
            },
            { threshold: 0.02 },
        );
        intersectionObserver.observe(host.value);
        document.addEventListener('visibilitychange', handleVisibility);

        if (!staticRender) {
            handleVisibility();
        }
    } catch {
        // Fallback artwork stays visible when WebGL or Three.js is unavailable.
    }
}

onMounted(() => {
    void initialise();
});

onBeforeUnmount(() => {
    disposed = true;
    setRunning(false);
    window.cancelAnimationFrame(animationFrame);
    resizeObserver?.disconnect();
    intersectionObserver?.disconnect();
    document.removeEventListener('visibilitychange', handleVisibility);

    if (renderer?.domElement.parentNode) {
        renderer.domElement.parentNode.removeChild(renderer.domElement);
    }

    geometry?.dispose();
    material?.dispose();
    lineGeometry?.dispose();
    lineMaterial?.dispose();
    renderer?.dispose();
    renderer?.forceContextLoss();
});
</script>

<template>
    <div
        ref="host"
        class="relative isolate overflow-hidden"
        :class="props.class"
        role="img"
        :aria-label="
            props.mode === 'hero'
                ? 'A dimensional field of points resolving into a waveform'
                : 'A compact signal waveform'
        "
    >
        <svg
            v-show="!ready"
            viewBox="0 0 600 420"
            preserveAspectRatio="xMidYMid slice"
            class="absolute inset-0 h-full w-full text-signal"
            aria-hidden="true"
        >
            <defs>
                <pattern
                    id="signal-grid"
                    width="40"
                    height="40"
                    patternUnits="userSpaceOnUse"
                >
                    <path
                        d="M40 0H0v40"
                        fill="none"
                        stroke="currentColor"
                        stroke-opacity=".08"
                    />
                </pattern>
                <radialGradient id="signal-fade">
                    <stop
                        offset="0"
                        stop-color="currentColor"
                        stop-opacity=".85"
                    />
                    <stop
                        offset="1"
                        stop-color="currentColor"
                        stop-opacity="0"
                    />
                </radialGradient>
            </defs>
            <rect width="600" height="420" fill="url(#signal-grid)" />
            <ellipse
                cx="300"
                cy="210"
                rx="190"
                ry="130"
                fill="url(#signal-fade)"
                opacity=".1"
            />
            <circle
                cx="300"
                cy="210"
                r="118"
                fill="none"
                stroke="currentColor"
                stroke-opacity=".22"
            />
            <circle
                cx="300"
                cy="210"
                r="72"
                fill="none"
                stroke="currentColor"
                stroke-opacity=".16"
            />
            <path
                d="M40 210c52 0 52-72 104-72s52 144 104 144 52-216 104-216 52 288 104 288 52-144 104-144"
                fill="none"
                stroke="currentColor"
                stroke-opacity=".72"
                stroke-width="1.4"
            />
            <g fill="currentColor" fill-opacity=".5">
                <circle
                    v-for="n in 18"
                    :key="n"
                    :cx="70 + n * 26"
                    :cy="210 + Math.sin(n * 0.9) * 46"
                    r="1.6"
                />
            </g>
        </svg>
    </div>
</template>
