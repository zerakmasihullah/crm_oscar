<script setup lang="ts">
/**
 * @deprecated Import MisChart from '@/components/mis' instead.
 */
import MisChart from '@/components/mis/MisChart.vue';
import type { MisChartDataset } from '@/components/mis/MisChart.vue';

export type AnalyticsChartDataset = MisChartDataset;

defineProps<{
    type: 'bar' | 'line' | 'pie';
    labels: string[];
    datasets: MisChartDataset[];
}>();
</script>

<template>
    <MisChart :type="type" :labels="labels" :datasets="datasets" />
</template>
