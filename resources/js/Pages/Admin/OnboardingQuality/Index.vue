<template>
    <AdminShell
        title="Onboarding quality control"
        subtitle="Super Admin console — resolve escalated cases directly (no further escalation). Review new signups within 48 hours."
    >
        <OnboardingQualityWorkspace
            :listing-route="route('admin.api.onboarding-quality.listing')"
            detail-route-name="admin.api.onboarding-quality.detail"
            action-route-name="admin.api.onboarding-quality.action"
            :flagged-href="route('admin.onboarding-quality.flagged')"
            :is-super-admin="true"
        />
    </AdminShell>
</template>

<script setup>
import OnboardingQualityWorkspace from '@/Components/OnboardingQuality/OnboardingQualityWorkspace.vue';
import AdminShell from '@/Layouts/AdminShell.vue';
</script>
