const STORAGE_KEY = 'vellum:pending-prompt';
const MAX_AGE_MS = 30 * 60 * 1000;

type PromptHandoff = {
    prompt: string;
    expiresAt: number;
};

function storage(): Storage | null {
    if (typeof window === 'undefined') {
        return null;
    }

    try {
        return window.sessionStorage;
    } catch {
        return null;
    }
}

export function queuePromptForStudio(prompt: string): void {
    const store = storage();
    if (!store) {
        return;
    }

    const handoff: PromptHandoff = {
        prompt,
        expiresAt: Date.now() + MAX_AGE_MS,
    };

    try {
        store.setItem(STORAGE_KEY, JSON.stringify(handoff));
    } catch {
        // Storage can be disabled; the studio still works without a handoff.
    }
}

export function consumePromptForStudio(): string | null {
    const store = storage();
    if (!store) {
        return null;
    }

    let raw: string | null = null;

    try {
        raw = store.getItem(STORAGE_KEY);
        store.removeItem(STORAGE_KEY);
    } catch {
        return null;
    }

    if (!raw) {
        return null;
    }

    try {
        const handoff = JSON.parse(raw) as PromptHandoff;

        if (
            typeof handoff.prompt !== 'string' ||
            typeof handoff.expiresAt !== 'number' ||
            handoff.expiresAt < Date.now()
        ) {
            return null;
        }

        return handoff.prompt;
    } catch {
        return null;
    }
}
