import { defineStore } from 'pinia';
import { ref } from 'vue';

export const useThemeStore = defineStore('theme', () => {
    const isDark = ref<boolean>(localStorage.getItem('theme') === 'dark');

    function apply(): void {
        document.documentElement.classList.toggle('dark', isDark.value);
    }

    function toggle(): void {
        isDark.value = !isDark.value;
        localStorage.setItem('theme', isDark.value ? 'dark' : 'light');
        apply();
    }

    function init(): void {
        apply();
    }

    return { isDark, toggle, init };
});
