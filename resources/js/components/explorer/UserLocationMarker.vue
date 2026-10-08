<script setup lang="ts">
defineProps<{ accuracy: number }>();
</script>

<template>
    <!-- Raised personal marker stays distinct from category pins -->
    <div
        class="pointer-events-none relative flex h-30 w-32 flex-col items-center justify-end font-explorer-body"
        role="img"
        :aria-label="`You are here. Estimated location accuracy: ${Math.round(accuracy)} meters.`"
    >
        <span
            class="absolute top-0 rounded-full border border-blue-100 bg-white px-3 py-1.5 text-xs font-bold whitespace-nowrap text-explorer-blue shadow-lg shadow-blue-950/15"
        >
            You are here
        </span>

        <!-- Elliptical platform and shaded figure give depth without a heavy 3D model -->
        <svg
            class="h-22 w-20 overflow-visible drop-shadow-[0_5px_5px_#172b4d40]"
            viewBox="0 0 80 88"
            aria-hidden="true"
        >
            <ellipse cx="40" cy="76" rx="30" ry="10" fill="#1d4ed8" />
            <ellipse cx="40" cy="72" rx="30" ry="10" fill="#60a5fa" />
            <ellipse cx="40" cy="71" rx="23" ry="7" fill="#dbeafe" />
            <ellipse
                cx="40"
                cy="70"
                rx="13"
                ry="4"
                fill="#2563eb"
                opacity="0.2"
            />

            <path d="M31 46h9v20h-9zm10 0h9v20h-9z" fill="#172b4d" />
            <path
                d="M30 63h10v6H28a2 2 0 0 1-2-2zM41 63h9l5 4a2 2 0 0 1-2 2H41z"
                fill="#fff"
            />
            <path d="M29 28q11-7 22 0l4 20H25z" fill="#2563eb" />
            <path d="M40 25q7 0 11 3l4 20H40z" fill="#1d4ed8" />
            <path
                d="M28 29l-6 17M52 29l6 17"
                fill="none"
                stroke="#93c5fd"
                stroke-width="7"
                stroke-linecap="round"
            />
            <path
                d="M22 45v5M58 45v5"
                stroke="#ffd4aa"
                stroke-width="6"
                stroke-linecap="round"
            />
            <rect x="36" y="20" width="8" height="9" rx="3" fill="#ffd4aa" />
            <circle cx="40" cy="15" r="10" fill="#ffd4aa" />
            <path
                d="M30 15V12a10 10 0 0 1 20 0v3l-4-7q-7 5-16 3z"
                fill="#172b4d"
            />
            <path
                d="M35 31h10"
                stroke="#fff"
                stroke-width="3"
                stroke-linecap="round"
            />
        </svg>
    </div>
</template>
