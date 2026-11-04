import pluginVue from 'eslint-plugin-vue';
import { withVueTs, vueTsConfigs } from '@vue/eslint-config-typescript';

export default withVueTs(
    {
        ignores: [
            'vendor/**',
            'node_modules/**',
            'public/build/**',
            'storage/**',
            'bootstrap/cache/**',
            'docs/baseline/**',
        ],
    },
    pluginVue.configs['flat/essential'],
    vueTsConfigs.recommended,
    {
        rules: {
            'vue/multi-word-component-names': 'off',
            '@typescript-eslint/no-explicit-any': 'error',
        },
    },
);
