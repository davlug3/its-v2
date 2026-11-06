<script setup lang="ts">
import { useForm } from 'vee-validate';
import { toTypedSchema } from '@vee-validate/yup';
import { useRoute, useRouter } from 'vue-router';

import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { useAuthStore } from '@/stores/auth';
import { loginSchema } from '@/schemas/login';

const auth = useAuthStore();
const router = useRouter();
const route = useRoute();

const { handleSubmit, errors, defineField } = useForm({
    validationSchema: toTypedSchema(loginSchema),
});

const [username, usernameAttrs] = defineField('username');
const [password, passwordAttrs] = defineField('password');

const onSubmit = handleSubmit(async (values) => {
    const ok = await auth.login(values);
    if (ok) {
        const redirect = typeof route.query.redirect === 'string' ? route.query.redirect : '/';
        await router.push(redirect);
    }
});
</script>

<template>
    <div class="flex min-h-screen items-center justify-center bg-background p-4">
        <Card class="w-full max-w-sm">
            <CardHeader>
                <CardTitle class="text-lg">Sign in</CardTitle>
                <CardDescription>IHOMS Ticketing System</CardDescription>
            </CardHeader>
            <CardContent>
                <form class="space-y-4" @submit="onSubmit">
                    <div class="space-y-2">
                        <Label for="username">Username</Label>
                        <Input
                            id="username"
                            v-model="username"
                            v-bind="usernameAttrs"
                            type="text"
                            autocomplete="username"
                        />
                        <p v-if="errors.username" class="text-xs text-destructive">
                            {{ errors.username }}
                        </p>
                    </div>

                    <div class="space-y-2">
                        <Label for="password">Password</Label>
                        <Input
                            id="password"
                            v-model="password"
                            v-bind="passwordAttrs"
                            type="password"
                            autocomplete="current-password"
                        />
                        <p v-if="errors.password" class="text-xs text-destructive">
                            {{ errors.password }}
                        </p>
                    </div>

                    <p v-if="auth.error" class="text-xs text-destructive">
                        {{ auth.error }}
                    </p>

                    <Button type="submit" class="w-full" :disabled="auth.loading">
                        {{ auth.loading ? 'Signing in...' : 'Sign in' }}
                    </Button>
                </form>
            </CardContent>
        </Card>
    </div>
</template>
