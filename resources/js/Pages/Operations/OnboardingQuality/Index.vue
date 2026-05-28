<template>
    <OperationsShell
        title="Onboarding quality control"
        subtitle="Review new client and freelancer signups within 48 hours — profile authenticity, completeness, and behaviour signals."
    >
        <OnboardingQualityWorkspace
            :listing-route="route('operations.api.onboarding-quality.listing')"
            detail-route-name="operations.api.onboarding-quality.detail"
            action-route-name="operations.api.onboarding-quality.action"
            :flagged-href="route('operations.onboarding-quality.flagged')"
            :is-super-admin="false"
        />
    </OperationsShell>
</template>

<script setup>
import OnboardingQualityWorkspace from '@/Components/OnboardingQuality/OnboardingQualityWorkspace.vue';
import OperationsShell from '@/Layouts/OperationsShell.vue';
</script>
