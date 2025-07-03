<template>
    <div>
        <div v-for="yearBlock in timeline" :key="yearBlock.year">
            <h2 class="text-secondary text-center">{{ yearBlock.year }}</h2>
            <div class="row" v-for="(entry, index) in yearBlock.entries" :key="index">
                <div class="col-md-6" :class="{ 'text-end': index % 2 === 0 }">
                    <TimelineEntry
                        :title="entry.title"
                        :icon="entry.icon"
                        :category="entry.category"
                        :details="entry.details" />
                </div>
                <div class="col-md-6" v-if="index % 2 === 0"></div>
            </div>
        </div>
    </div>
</template>

<script>
import TimelineEntry from './TimelineEntry.vue'
import timeline from '../resume.json'


export default {
    components: { TimelineEntry },
    data() {
        return {
            timeline: []
        }
    },
    mounted() {
        fetch('../resume.json')
            .then(res => res.json())
            .then(data => {
                this.timeline = data
            })
            .catch(err => console.error('Fehler beim Laden der Timeline:', err))
    }
}
</script>
