import type { Generation } from '@/types';

export function formatDateTime(value: string | null): string {
    if (!value) {
        return '—';
    }

    return new Intl.DateTimeFormat('en', {
        day: '2-digit',
        month: 'short',
        year: 'numeric',
        hour: '2-digit',
        minute: '2-digit',
        timeZoneName: 'short',
    }).format(new Date(value));
}

export function formatDate(value: string | null): string {
    if (!value) {
        return '—';
    }

    return new Intl.DateTimeFormat('en', {
        day: '2-digit',
        month: 'short',
        year: 'numeric',
    }).format(new Date(value));
}

export function formatRelative(value: string | null): string {
    if (!value) {
        return '';
    }

    const seconds = Math.round((new Date(value).getTime() - Date.now()) / 1000);
    const formatter = new Intl.RelativeTimeFormat('en', { numeric: 'auto' });
    const units: Array<[Intl.RelativeTimeFormatUnit, number]> = [
        ['year', 60 * 60 * 24 * 365],
        ['month', 60 * 60 * 24 * 30],
        ['week', 60 * 60 * 24 * 7],
        ['day', 60 * 60 * 24],
        ['hour', 60 * 60],
        ['minute', 60],
        ['second', 1],
    ];

    for (const [unit, size] of units) {
        if (Math.abs(seconds) >= size || unit === 'second') {
            return formatter.format(Math.round(seconds / size), unit);
        }
    }

    return '';
}

export function ratioLabel(size: string): string {
    return (
        {
            '1024x1024': 'Square',
            '1536x1024': 'Landscape',
            '1024x1536': 'Portrait',
        }[size] ?? size
    );
}

export function ratioCss(size: string): string {
    return (
        {
            '1024x1024': '1 / 1',
            '1536x1024': '3 / 2',
            '1024x1536': '2 / 3',
        }[size] ?? '1 / 1'
    );
}

export function qualityLabel(quality: string): string {
    return (
        {
            low: 'Sketch',
            medium: 'Studio',
            high: 'Gallery',
        }[quality] ?? quality
    );
}

export function qualityDescription(quality: string): string {
    return (
        {
            low: 'Fast studies and loose ideas.',
            medium: 'The everyday working image.',
            high: 'Fine detail, more tokens, more time.',
        }[quality] ?? ''
    );
}

export function generationAlt(generation: Generation): string {
    return generation.prompt.length > 140
        ? `${generation.prompt.slice(0, 137)}…`
        : generation.prompt;
}

export async function copyText(value: string): Promise<boolean> {
    try {
        await navigator.clipboard.writeText(value);

        return true;
    } catch {
        return false;
    }
}

export function csrfToken(): string {
    return (
        document
            .querySelector<HTMLMetaElement>('meta[name="csrf-token"]')
            ?.getAttribute('content') ?? ''
    );
}

export function randomUuid(): string {
    if (typeof globalThis.crypto?.randomUUID === 'function') {
        return globalThis.crypto.randomUUID();
    }

    return 'xxxxxxxx-xxxx-4xxx-yxxx-xxxxxxxxxxxx'.replace(/[xy]/g, (char) => {
        const random = Math.floor(Math.random() * 16);
        const value = char === 'x' ? random : (random & 0x3) | 0x8;

        return value.toString(16);
    });
}
