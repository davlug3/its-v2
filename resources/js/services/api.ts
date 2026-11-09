const BASE_URL = import.meta.env.VITE_API_BASE_URL ?? '/api/v1';

let token: string | null = localStorage.getItem('auth_token');
let csrfToken: string | null = localStorage.getItem('csrf_token');

export function setToken(value: string | null): void {
    token = value;
    if (value === null) {
        localStorage.removeItem('auth_token');
    } else {
        localStorage.setItem('auth_token', value);
    }
}

export function setCsrfToken(value: string | null): void {
    csrfToken = value;
    if (value === null) {
        localStorage.removeItem('csrf_token');
    } else {
        localStorage.setItem('csrf_token', value);
    }
}

export function getToken(): string | null {
    return token;
}

export function newIdempotencyKey(): string {
    return crypto.randomUUID();
}

export interface ApiError {
    message: string;
    errors?: Record<string, string[]>;
}

export async function api<T = unknown>(
    path: string,
    options: RequestInit = {},
    idempotencyKey?: string,
): Promise<T> {
    const headers = new Headers(options.headers);
    headers.set('Accept', 'application/json');
    const isFormData = options.body instanceof FormData;
    if (options.body !== undefined && !isFormData && !headers.has('Content-Type')) {
        headers.set('Content-Type', 'application/json');
    }
    if (token !== null) {
        headers.set('Authorization', `Bearer ${token}`);
    }

    const method = (options.method ?? 'GET').toUpperCase();
    if (csrfToken !== null && method !== 'GET' && method !== 'HEAD' && method !== 'OPTIONS') {
        headers.set('X-CSRF-TOKEN', csrfToken);
    }

    if (idempotencyKey) {
        headers.set('Idempotency-Key', idempotencyKey);
    }

    const response = await fetch(`${BASE_URL}${path}`, {
        ...options,
        headers,
    });

    if (response.status === 401) {
        setToken(null);
        window.location.href = '/login';
        throw new Error('Unauthenticated');
    }

    if (!response.ok) {
        let message = `Request failed (${response.status})`;
        let errors: Record<string, string[]> | undefined;
        try {
            const body = (await response.json()) as {
                message?: string;
                errors?: Record<string, string[]>;
            };
            if (body.message) message = body.message;
            errors = body.errors;
        } catch {
            // ignore parse errors
        }
        const err: ApiError = { message, errors };
        throw err;
    }

    if (response.status === 204) {
        return undefined as T;
    }

    return (await response.json()) as T;
}
