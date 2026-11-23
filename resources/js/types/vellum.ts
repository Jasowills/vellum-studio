export type GenerationStatus =
    | 'pending'
    | 'queued'
    | 'processing'
    | 'succeeded'
    | 'failed';

export type GenerationImage = {
    url: string | null;
    available: boolean;
    expired: boolean;
    expires_at: string | null;
};

export type GenerationCost = {
    currency: string;
    customer_charge_nanos: number | null;
    customer_charge: string | null;
    customer_charge_exact: string | null;
};

export type GenerationTokens = {
    input_text_tokens: number | null;
    input_image_tokens: number | null;
    output_tokens: number | null;
    reasoning_tokens: number | null;
    total_tokens: number | null;
};

export type Generation = {
    id: string;
    status: GenerationStatus;
    status_label: string;
    status_tone: 'waiting' | 'working' | 'success' | 'danger';
    prompt: string;
    size: string;
    quality: string;
    reference_images: string[];
    created_at: string | null;
    updated_at: string | null;
    timeline: {
        submitted_at: string | null;
        accepted_at: string | null;
        processing_at: string | null;
        completed_at: string | null;
        settled_at: string | null;
    };
    image: GenerationImage;
    estimate: {
        customer_charge_nanos: { min: number | null; max: number | null };
        customer_charge: { min: string | null; max: string | null };
        total_tokens: { min: number; max: number } | null;
        markup_percent: number;
        pricing_fetched_at: string | null;
    };
    cost: GenerationCost;
    usage: GenerationTokens;
    failure: {
        code: string | null;
        message: string | null;
        stage: string | null;
    } | null;
    charge: {
        customer_charge_nanos: number;
        customer_charge: string;
        customer_charge_exact: string;
        settled_at: string | null;
        tokens: GenerationTokens;
    } | null;
    can_retry: boolean;
    prefill_url: string;
    provider?: {
        generation_id: string | null;
        provider_cost_nanos: number | null;
        provider_cost: string | null;
        submit_attempts: number;
        reconcile_attempts: number;
        error: Record<string, unknown> | null;
    };
};

export type PricingPayload = {
    rates: Record<string, string>;
    tokens: {
        characters_per_text_token: number;
        text_token_variance: number | string;
        per_reference_image: number;
        output: Record<string, Record<string, number>>;
        reasoning: Record<string, { min: number; max: number }>;
    };
    fetched_at: string;
};

export type EstimatePayload = {
    customer_charge_nanos: { min: number; max: number };
    customer_charge: { min: string; max: string };
    total_tokens: { min: number; max: number };
    text_tokens: { base: number; min: number; max: number };
    input_image_tokens: number;
    output_tokens: number;
    reasoning_tokens: { min: number; max: number };
    markup_percent: number;
    pricing_fetched_at: string;
};

export type Paginated<T> = {
    data: T[];
    current_page: number;
    last_page: number;
    per_page: number;
    total: number;
    from: number | null;
    to: number | null;
    path: string;
    links: Array<{ url: string | null; label: string; active: boolean }>;
    prev_page_url: string | null;
    next_page_url: string | null;
};

export type Flash = {
    status: string | null;
    error: string | null;
};
