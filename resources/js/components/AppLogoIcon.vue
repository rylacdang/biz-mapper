<script setup lang="ts">
import type { HTMLAttributes } from 'vue';

defineOptions({
    inheritAttrs: false,
});

type Props = {
    className?: HTMLAttributes['class'];
};

defineProps<Props>();
</script>

<template>
    <img
        src="/img/biz-mapper.png?v=3"
        alt=""
        aria-hidden="true"
        :class="className"
        v-bind="$attrs"
    />
</template>
