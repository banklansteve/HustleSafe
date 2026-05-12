<template>
    <div class="min-h-screen bg-gradient-to-b from-slate-50 via-white to-slate-50 text-slate-900">
        <header
            class="sticky top-0 z-40 border-b border-slate-200/90 bg-white/90 backdrop-blur-lg supports-[backdrop-filter]:bg-white/80"
        >
            <div class="mx-auto flex max-w-6xl items-center justify-between gap-4 px-4 py-4 sm:px-6 lg:px-8">
                <Link href="/" class="flex items-center gap-3 rounded-xl outline-none ring-offset-2 focus-visible:ring-2 focus-visible:ring-primary-600">
                    <span
                        class="flex h-11 w-11 shrink-0 items-center justify-center rounded-2xl bg-gradient-to-br from-primary-600 to-primary-800 text-sm font-black tracking-wide text-white shadow-md shadow-primary-900/20 ring-1 ring-white/25"
                    >
                        HS
                    </span>
                    <div class="leading-tight">
                        <p class="font-display text-lg font-bold tracking-tight text-slate-900">
                            HustleSafe
                        </p>
                        <p class="text-sm font-semibold text-slate-500">
                            Escrow-first marketplace
                        </p>
                    </div>
                </Link>

                <div class="flex items-center gap-2 sm:gap-3">
                    <a
                        href="#notifications"
                        class="inline-flex h-11 w-11 items-center justify-center rounded-xl border border-slate-200 bg-white text-slate-700 shadow-sm transition hover:border-primary-200 hover:bg-primary-50 hover:text-primary-800 focus:outline-none focus-visible:ring-2 focus-visible:ring-primary-600"
                        aria-label="Notifications"
                    >
                        <BellAlertIcon class="h-6 w-6" aria-hidden="true" />
                    </a>

                    <Dropdown align="right" width="52">
                        <template #trigger>
                            <button
                                type="button"
                                class="inline-flex max-w-[12rem] items-center gap-2 rounded-xl border border-slate-200 bg-white px-3 py-2.5 text-sm font-bold text-slate-800 shadow-sm transition hover:border-primary-200 hover:bg-slate-50 focus:outline-none focus-visible:ring-2 focus-visible:ring-primary-600 sm:max-w-none sm:px-4"
                            >
                                <span class="truncate">{{ userLabel }}</span>
                                <ChevronDownIcon class="h-4 w-4 shrink-0 text-slate-400" aria-hidden="true" />
                            </button>
                        </template>
                        <template #content>
                            <DropdownLink :href="route('dashboard')">
                                Home
                            </DropdownLink>
                            <DropdownLink :href="route('profile.edit')">
                                Profile & settings
                            </DropdownLink>
                            <DropdownLink :href="route('verifications.index')">
                                Trust &amp; verifications
                            </DropdownLink>
                            <DropdownLink :href="route('logout')" method="post" as="button">
                                Log out
                            </DropdownLink>
                        </template>
                    </Dropdown>
                </div>
            </div>

            <div
                v-if="flashSuccess"
                class="border-t border-emerald-100 bg-emerald-50 px-4 py-3 text-center text-sm font-semibold text-emerald-900 sm:text-base"
                role="status"
            >
                {{ flashSuccess }}
            </div>
            <div
                v-else-if="flashStatus"
                class="border-t border-primary-100 bg-primary-50 px-4 py-3 text-center text-sm font-semibold text-primary-950 sm:text-base"
                role="status"
            >
                {{ flashStatus }}
            </div>
        </header>

        <main class="mx-auto max-w-6xl px-4 py-8 sm:px-6 sm:py-10 lg:px-8 lg:py-12">
            <slot />
        </main>
    </div>
</template>

<script setup>
import Dropdown from '@/Components/Dropdown.vue';
import DropdownLink from '@/Components/DropdownLink.vue';
import { Link, usePage } from '@inertiajs/vue3';
import { BellAlertIcon, ChevronDownIcon } from '@heroicons/vue/24/outline';
import { computed } from 'vue';

const page = usePage();

const userLabel = computed(() => page.props.auth?.user?.name ?? 'Account');

const flashSuccess = computed(() => page.props.flash?.success ?? null);
const flashStatus = computed(() => page.props.flash?.status ?? null);
</script>
