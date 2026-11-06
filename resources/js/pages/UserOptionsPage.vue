<script setup lang="ts">
import { ref } from 'vue';

import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import PageHeader from '@/components/common/PageHeader.vue';
import { Label } from '@/components/ui/label';
import { api } from '@/services/api';
import { useAuthStore } from '@/stores/auth';

const auth = useAuthStore();

const currentPassword = ref('');
const newPassword = ref('');
const confirmPassword = ref('');
const saving = ref(false);
const error = ref<string | null>(null);
const success = ref<string | null>(null);

const submit = async (): Promise<void> => {
    error.value = null;
    success.value = null;

    if (newPassword.value !== confirmPassword.value) {
        error.value = 'New password and confirmation do not match.';
        return;
    }

    saving.value = true;
    try {
        await api('/auth/change-password', {
            method: 'POST',
            body: JSON.stringify({
                current_password: currentPassword.value,
                new_password: newPassword.value,
            }),
        });
        success.value = 'Your password has been changed.';
        currentPassword.value = '';
        newPassword.value = '';
        confirmPassword.value = '';
    } catch (e) {
        error.value = (e as { message?: string }).message ?? 'Failed to change password.';
    } finally {
        saving.value = false;
    }
};
</script>

<template>
    <div class="space-y-4">
        <PageHeader title="User options" description="Account details and password change." />

        <Card>
            <CardHeader>
                <CardTitle>Account</CardTitle>
            </CardHeader>
            <CardContent>
                <dl class="grid grid-cols-1 gap-2 text-sm sm:grid-cols-2">
                    <div>
                        <dt class="text-xs font-medium text-muted-foreground">Username</dt>
                        <dd>{{ auth.user?.username ?? '—' }}</dd>
                    </div>
                    <div>
                        <dt class="text-xs font-medium text-muted-foreground">Name</dt>
                        <dd>{{ auth.user?.fullname || '—' }}</dd>
                    </div>
                </dl>
            </CardContent>
        </Card>

        <Card>
            <CardHeader>
                <CardTitle>Change password</CardTitle>
            </CardHeader>
            <CardContent>
                <form class="max-w-md space-y-4" @submit.prevent="submit">
                    <div class="space-y-2">
                        <Label for="current">Current password</Label>
                        <Input id="current" v-model="currentPassword" type="password" autocomplete="current-password" />
                    </div>
                    <div class="space-y-2">
                        <Label for="new">New password</Label>
                        <Input id="new" v-model="newPassword" type="password" autocomplete="new-password" />
                    </div>
                    <div class="space-y-2">
                        <Label for="confirm">Confirm new password</Label>
                        <Input id="confirm" v-model="confirmPassword" type="password" autocomplete="new-password" />
                    </div>

                    <p v-if="error" class="text-sm text-destructive">{{ error }}</p>
                    <p v-if="success" class="text-sm text-foreground">{{ success }}</p>

                    <Button type="submit" :disabled="saving">
                        {{ saving ? 'Saving…' : 'Change password' }}
                    </Button>
                </form>
            </CardContent>
        </Card>
    </div>
</template>