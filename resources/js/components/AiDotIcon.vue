<script setup lang="ts">
import { computed } from 'vue';

export type AiDotVariant = 'rose' | 'sky' | 'amber' | 'lime' | 'violet';

const props = withDefaults(
    defineProps<{
        size?: 'sm' | 'md' | 'lg';
        playing?: boolean;
        variant?: AiDotVariant;
    }>(),
    {
        size: 'lg',
        playing: false,
        variant: 'lime',
    },
);

const sizeClass: Record<string, string> = {
    sm: 'size-5',
    md: 'size-7',
    lg: 'size-11',
};

const src = computed(() => `/images/ai-dots/${props.variant}.png?v=5`);
</script>

<template>
    <span
        class="ai-dot group inline-flex shrink-0 cursor-pointer items-center justify-center"
        :class="[
            sizeClass[size],
            `ai-dot--${variant}`,
            playing ? 'ai-dot--idle' : '',
        ]"
        aria-hidden="true"
    >
        <img
            :src="src"
            alt=""
            class="ai-dot__img h-full w-full object-contain select-none drop-shadow-[0_4px_10px_rgba(0,0,0,0.08)] transition-[filter] duration-300 group-hover:drop-shadow-[0_12px_20px_rgba(0,0,0,0.18)]"
            draggable="false"
        />
    </span>
</template>

<style scoped>
.ai-dot {
    transform-origin: 50% 85%;
    will-change: transform;
}

/* —— idle = same playful loops as before —— */
.ai-dot--idle.ai-dot--rose {
    animation: idle-rose 1.9s ease-in-out infinite;
}
.ai-dot--idle.ai-dot--sky {
    animation: idle-sky 2.3s ease-in-out infinite;
}
.ai-dot--idle.ai-dot--amber {
    animation: idle-amber 1.4s cubic-bezier(0.34, 1.4, 0.64, 1) infinite;
}
.ai-dot--idle.ai-dot--lime {
    animation: idle-lime 1.7s ease-in-out infinite;
}
.ai-dot--idle.ai-dot--violet {
    animation: idle-violet 2.1s ease-in-out infinite;
}

/* —— hover = totally different moves —— */
.ai-dot--rose:hover {
    animation: hover-rose 0.55s linear infinite !important;
}
.ai-dot--sky:hover {
    animation: hover-sky 1.1s ease-in-out infinite !important;
}
.ai-dot--amber:hover {
    animation: hover-amber 0.7s ease-in-out infinite !important;
}
.ai-dot--lime:hover {
    animation: hover-lime 0.6s cubic-bezier(0.36, 0.07, 0.19, 0.97) infinite !important;
}
.ai-dot--violet:hover {
    animation: hover-violet 0.8s ease-in-out infinite !important;
}

@keyframes idle-rose {
    0%,
    100% {
        transform: translateY(0) rotate(0deg);
    }
    30% {
        transform: translateY(-4px) rotate(-6deg);
    }
    70% {
        transform: translateY(-3px) rotate(6deg);
    }
}
@keyframes idle-sky {
    0%,
    100% {
        transform: translateY(0) scale(1);
    }
    50% {
        transform: translateY(-6px) scale(1.05);
    }
}
@keyframes idle-amber {
    0%,
    100% {
        transform: translateY(0) scale(1, 1);
    }
    35% {
        transform: translateY(-7px) scale(0.94, 1.08);
    }
    50% {
        transform: translateY(1px) scale(1.06, 0.92);
    }
}
@keyframes idle-lime {
    0%,
    100% {
        transform: translateY(0) rotate(0deg);
    }
    40% {
        transform: translateY(-5px) rotate(4deg);
    }
    55% {
        transform: translateY(1px) scale(1.05, 0.94);
    }
}
@keyframes idle-violet {
    0%,
    100% {
        transform: translateY(0) rotate(0deg);
    }
    25% {
        transform: translateY(-3px) rotate(8deg);
    }
    75% {
        transform: translateY(-3px) rotate(-8deg);
    }
}

/* pink: full spin while nodding */
@keyframes hover-rose {
    0% {
        transform: translateY(0) rotate(0deg) scale(1.08);
    }
    25% {
        transform: translateY(-6px) rotate(90deg) scale(1.12);
    }
    50% {
        transform: translateY(0) rotate(180deg) scale(1.08);
    }
    75% {
        transform: translateY(-6px) rotate(270deg) scale(1.12);
    }
    100% {
        transform: translateY(0) rotate(360deg) scale(1.08);
    }
}

/* blue: smooth glide right ↔ left */
@keyframes hover-sky {
    0%,
    100% {
        transform: translateX(0) translateY(0) rotate(-6deg) scale(1.1);
    }
    25% {
        transform: translateX(12px) translateY(-8px) rotate(4deg) scale(1.14);
    }
    50% {
        transform: translateX(0) translateY(-12px) rotate(0deg) scale(1.12);
    }
    75% {
        transform: translateX(-12px) translateY(-8px) rotate(-4deg) scale(1.14);
    }
}

/* yellow: smooth right-to-left sway */
@keyframes hover-amber {
    0%,
    100% {
        transform: translateX(-8px) rotate(-10deg) scale(1.12);
    }
    50% {
        transform: translateX(8px) rotate(10deg) scale(1.12);
    }
}

/* lime: jelly jiggle */
@keyframes hover-lime {
    0%,
    100% {
        transform: translateY(0) scale(1.1, 1.1);
    }
    20% {
        transform: translateY(-2px) scale(1.22, 0.88) rotate(-4deg);
    }
    40% {
        transform: translateY(0) scale(0.9, 1.2) rotate(4deg);
    }
    60% {
        transform: translateY(-2px) scale(1.18, 0.9) rotate(-2deg);
    }
    80% {
        transform: translateY(0) scale(0.94, 1.14) rotate(2deg);
    }
}

/* violet: flip up and land */
@keyframes hover-violet {
    0% {
        transform: translateY(0) rotateX(0deg) scale(1.1);
    }
    40% {
        transform: translateY(-16px) rotateX(180deg) scale(1.18);
    }
    60% {
        transform: translateY(-16px) rotateX(180deg) scale(1.18);
    }
    100% {
        transform: translateY(0) rotateX(360deg) scale(1.1);
    }
}

@media (prefers-reduced-motion: reduce) {
    .ai-dot--idle,
    .ai-dot:hover {
        animation: none !important;
    }
}

/* mirror horizontal motion in RTL */
:global([dir='rtl']) .ai-dot--sky:hover,
:global([dir='rtl']) .ai-dot--amber:hover {
    animation-direction: reverse !important;
}
</style>
