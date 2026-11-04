import { defineStore } from 'pinia';
import { computed, ref } from 'vue';

import { api, setToken } from '@/services/api';
import { loginSchema, type LoginInput } from '@/schemas/login';

interface AuthUser {
    id: number;
    username: string;
    fullname: string;
}

export const useAuthStore = defineStore('auth', () => {
    const user = ref<AuthUser | null>(null);
    const token = ref<string | null>(localStorage.getItem('auth_token'));
    const loading = ref(false);
    const error = ref<string | null>(null);

    const isAuthenticated = computed(() => token.value !== null);

    async function login(input: LoginInput): Promise<boolean> {
        loading.value = true;
        error.value = null;
        try {
            const parsed = await loginSchema.validate(input, { abortEarly: false });
            const response = await api<{ token: string }>('/auth/login', {
                method: 'POST',
                body: JSON.stringify(parsed),
            });
            setToken(response.token);
            token.value = response.token;
            await fetchMe();
            return true;
        } catch (e) {
            const err = e as { message?: string };
            error.value = err.message ?? 'Login failed.';
            return false;
        } finally {
            loading.value = false;
        }
    }

    async function fetchMe(): Promise<void> {
        try {
            const response = await api<AuthUser>('/auth/me');
            user.value = response;
        } catch {
            user.value = null;
        }
    }

    function logout(): void {
        void api('/auth/logout', { method: 'POST' }).catch(() => {
            // ignore
        });
        setToken(null);
        token.value = null;
        user.value = null;
    }

    return { user, token, loading, error, isAuthenticated, login, fetchMe, logout };
});
