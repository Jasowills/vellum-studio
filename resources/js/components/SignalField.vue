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
let sweep: Three.Mesh | null = null;
let resizeObserver: ResizeObserver | null = null;
let intersectionObserver: IntersectionObserver | null = null;
let animationFrame = 0;
let running = false;
let onScreen = true;
let elapsed = 0;
let lastFrame = 0;
let pointerX = 0;
let pointerY = 0;
let targetX = 0;
let targetY = 0;

const geometries: Three.BufferGeometry[] = [];
const materials: Three.Material[] = [];

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
    return (nav.hardwareConcurrency ?? 4) <= 4 || (nav.deviceMemory ?? 4) <= 4;
}

function update(time: number, delta: number): void {
    if (!group || !sweep) {
        return;
    }

    targetX += (pointerX - targetX) * Math.min(1, delta * 2.4);
    targetY += (pointerY - targetY) * Math.min(1, delta * 2.4);

    const drift = Math.sin(time * 0.22) * 0.055;
    group.rotation.y = targetX * 0.19 + drift;
    group.rotation.x = -targetY * 0.11 + Math.sin(time * 0.16) * 0.018;
    group.position.y = Math.sin(time * 0.28) * 0.025;
    sweep.position.y = Math.sin(time * 0.52) * 1.08;
}

function renderFrame(now: number): void {
    if (!running || disposed || !renderer || !scene || !camera) {
        return;
    }

    const delta = Math.min((now - lastFrame) / 1000, 0.05);
    lastFrame = now;
    elapsed += delta;
    update(elapsed, delta);
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

function handlePointer(event: PointerEvent): void {
    if (!host.value) {
        return;
    }

    const rect = host.value.getBoundingClientRect();
    pointerX = ((event.clientX - rect.left) / Math.max(rect.width, 1)) * 2 - 1;
    pointerY = ((event.clientY - rect.top) / Math.max(rect.height, 1)) * 2 - 1;
}

function clearPointer(): void {
    pointerX = 0;
    pointerY = 0;
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
        camera = new three.PerspectiveCamera(38, 1, 0.1, 24);
        camera.position.set(0, 0.06, 4.35);

        group = new three.Group();
        scene.add(group);

        const keyLight = new three.DirectionalLight(0xffffff, 2.6);
        keyLight.position.set(-2.4, 3.2, 4.2);
        scene.add(keyLight);
        const rimLight = new three.DirectionalLight(0xffffff, 0.9);
        rimLight.position.set(2.6, -0.8, 2.2);
        scene.add(rimLight);
        scene.add(new three.AmbientLight(0xffffff, 0.26));

        const veilGeometry = new three.PlaneGeometry(2.55, 2.85);
        const veilMaterial = new three.MeshBasicMaterial({
            color: 0xffffff,
            transparent: true,
            opacity: 0.028,
            side: three.DoubleSide,
            depthWrite: false,
        });
        const veil = new three.Mesh(veilGeometry, veilMaterial);
        veil.position.z = -0.24;
        veil.rotation.y = -0.12;
        group.add(veil);
        geometries.push(veilGeometry);
        materials.push(veilMaterial);

        const armGeometry = new three.CylinderGeometry(
            0.028,
            0.135,
            2.62,
            3,
            5,
            false,
        );
        geometries.push(armGeometry);

        const armMaterial = new three.MeshStandardMaterial({
            color: 0xf5f5f5,
            roughness: 0.48,
            metalness: 0.02,
            flatShading: true,
        });
        materials.push(armMaterial);

        const edgeMaterial = new three.LineBasicMaterial({
            color: 0xffffff,
            transparent: true,
            opacity: 0.42,
        });
        materials.push(edgeMaterial);

        const arms = [
            { x: -0.66, z: 0.02, rotate: 0.4, yaw: -0.2 },
            { x: 0.66, z: -0.02, rotate: -0.4, yaw: 0.2 },
        ];

        for (const arm of arms) {
            const mesh = new three.Mesh(armGeometry, armMaterial);
            mesh.position.set(arm.x, 0.08, arm.z);
            mesh.rotation.set(0.04, arm.yaw, arm.rotate);
            group.add(mesh);

            const edgeGeometry = new three.EdgesGeometry(armGeometry, 18);
            geometries.push(edgeGeometry);
            const edges = new three.LineSegments(edgeGeometry, edgeMaterial);
            edges.position.copy(mesh.position);
            edges.rotation.copy(mesh.rotation);
            group.add(edges);
        }

        const baseGeometry = new three.BoxGeometry(1.7, 0.014, 0.014);
        const baseMaterial = new three.MeshBasicMaterial({
            color: 0xffffff,
            transparent: true,
            opacity: 0.3,
        });
        const base = new three.Mesh(baseGeometry, baseMaterial);
        base.position.y = -1.17;
        group.add(base);
        geometries.push(baseGeometry);
        materials.push(baseMaterial);

        const sweepGeometry = new three.PlaneGeometry(2.05, 0.006);
        const sweepMaterial = new three.MeshBasicMaterial({
            color: 0xffffff,
            transparent: true,
            opacity: 0.32,
            depthWrite: false,
        });
        sweep = new three.Mesh(sweepGeometry, sweepMaterial);
        sweep.position.z = 0.26;
        group.add(sweep);
        geometries.push(sweepGeometry);
        materials.push(sweepMaterial);

        renderer = new three.WebGLRenderer({
            alpha: true,
            antialias: true,
            powerPreference: 'low-power',
        });
        renderer.setPixelRatio(Math.min(window.devicePixelRatio || 1, 1.6));
        renderer.setClearColor(0x000000, 0);
        renderer.domElement.setAttribute('aria-hidden', 'true');
        renderer.domElement.style.width = '100%';
        renderer.domElement.style.height = '100%';
        renderer.domElement.style.display = 'block';
        host.value.appendChild(renderer.domElement);

        resize();
        update(0, 1);
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
            host.value.addEventListener('pointermove', handlePointer);
            host.value.addEventListener('pointerleave', clearPointer);
            handleVisibility();
        }
    } catch {
        /* The inline V remains visible when WebGL or Three.js is unavailable. */
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
    host.value?.removeEventListener('pointermove', handlePointer);
    host.value?.removeEventListener('pointerleave', clearPointer);

    if (renderer?.domElement.parentNode) {
        renderer.domElement.parentNode.removeChild(renderer.domElement);
    }

    geometries.forEach((geometry) => geometry.dispose());
    materials.forEach((material) => material.dispose());
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
        aria-label="A geometric V sculpture formed from two faceted white planes."
    >
        <svg
            v-if="!ready"
            class="absolute inset-0 size-full text-document"
            viewBox="0 0 600 500"
            fill="none"
            aria-hidden="true"
            focusable="false"
        >
            <path
                d="M98 76h78l124 334h-76L98 76Z"
                fill="currentColor"
                fill-opacity=".72"
            />
            <path
                d="M424 76h78L378 410h-76L424 76Z"
                fill="currentColor"
                fill-opacity=".72"
            />
            <path
                d="M176 76h24l100 334h-22L176 76Z"
                fill="#fff"
                fill-opacity=".22"
            />
            <path d="M76 414h448" stroke="currentColor" stroke-opacity=".28" />
        </svg>
    </div>
</template>
