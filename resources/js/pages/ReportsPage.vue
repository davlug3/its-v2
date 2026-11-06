<script setup lang="ts">
import { ref } from 'vue';

import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { api, getToken } from '@/services/api';

const from = ref('');
const to = ref('');
const employeeId = ref('');
const format = ref<'csv' | 'json'>('csv');
const loading = ref(false);
const error = ref<string | null>(null);
const summary = ref<Record<string, unknown> | null>(null);

const today = new Date();
const firstDay = new Date(today.getFullYear(), today.getMonth(), 1).toISOString().slice(0, 10);
const lastDay = new Date(today.getFullYear(), today.getMonth() + 1, 0).toISOString().slice(0, 10);

from.value = firstDay;
to.value = lastDay;

const generate = async (): Promise<void> => {
    loading.value = true;
    error.value = null;
    summary.value = null;
    try {
        const params = new URLSearchParams();
        if (from.value) params.set('from', from.value);
        if (to.value) params.set('to', to.value);
        if (employeeId.value.trim() !== '') params.set('employees[]', employeeId.value.trim());

        if (format.value === 'csv') {
            const token = getToken();
            const response = await fetch(`/api/v1/reports/tickets?${params.toString()}`, {
                headers: token ? { Authorization: `Bearer ${token}` } : {},
            });
            if (!response.ok) {
                let message = `Request failed (${response.status})`;
                try {
                    const body = await response.json();
                    if (body.message) message = body.message;
                } catch {
                    // ignore
                }
                throw new Error(message);
            }
            const blob = await response.blob();
            const url = URL.createObjectURL(blob);
            const a = document.createElement('a');
            a.href = url;
            a.download = `tickets_report_${new Date().toISOString().slice(0, 10)}.csv`;
            document.body.appendChild(a);
            a.click();
            a.remove();
            URL.revokeObjectURL(url);
        } else {
            const month = from.value.slice(0, 7);
            const res = await api<Record<string, unknown>>(`/reports/monthly?date=${month}`);
            summary.value = res;
        }
    } catch (e) {
        error.value = (e as { message?: string }).message ?? 'Failed to generate report.';
    } finally {
        loading.value = false;
    }
};
</script>

<template>
    <div class="space-y-4">
        <h1 class="text-subheading font-semibold text-foreground">Reports</h1>

        <Card>
            <CardHeader>
                <CardTitle>Generate report</CardTitle>
            </CardHeader>
            <CardContent class="space-y-4">
                <div class="grid grid-cols-1 gap-4 sm:grid-cols-3">
                    <div class="space-y-2">
                        <Label for="from">From</Label>
                        <Input id="from" v-model="from" type="date" />
                    </div>
                    <div class="space-y-2">
                        <Label for="to">To</Label>
                        <Input id="to" v-model="to" type="date" />
                    </div>
                    <div class="space-y-2">
                        <Label for="employee">Employee ID (optional)</Label>
                        <Input id="employee" v-model="employeeId" />
                    </div>
                </div>

                <div class="flex items-center gap-4">
                    <label class="flex items-center gap-2 text-sm">
                        <input type="radio" value="csv" v-model="format" /> CSV (per ticket)
                    </label>
                    <label class="flex items-center gap-2 text-sm">
                        <input type="radio" value="json" v-model="format" /> Monthly summary (JSON)
                    </label>
                </div>

                <p v-if="error" class="text-sm text-destructive">{{ error }}</p>

                <Button :disabled="loading" @click="generate">
                    {{ loading ? 'Generating…' : 'Generate' }}
                </Button>

                <div v-if="summary" class="rounded-md border border-border p-4 text-sm">
                    <pre class="whitespace-pre-wrap">{{ JSON.stringify(summary, null, 2) }}</pre>
                </div>
            </CardContent>
        </Card>
    </div>
</template>