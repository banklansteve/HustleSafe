<template>
    <div class="min-h-screen bg-white text-slate-900 antialiased">
        <a
            href="#main-content"
            class="focus:bg-primary-700 focus:text-white sr-only focus:not-sr-only focus:absolute focus:left-4 focus:top-4 focus:z-[100] focus:rounded-lg focus:px-4 focus:py-2 focus:shadow-lg"
        >
            Skip to main content
        </a>
        <slot />
    </div>
</template>
