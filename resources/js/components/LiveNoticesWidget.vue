<script setup>
import { Button, Description, Widget } from '@statamic/cms/ui';

// The dashboard's Doormat card, on Statamic's own widget: per language the notices live right now.
// The texts arrive translated from the server (Byte5\Doormat\Widgets\LiveNotices).
defineProps({
    title: { type: String, required: true },
    url: { type: String, required: true },
    enabled: { type: Boolean, default: true },
    sites: { type: Array, default: () => [] },
    any: { type: Boolean, default: false },
    texts: { type: Object, required: true },
});
</script>

<template>
    <Widget :title :href="url" icon="megaphone">
        <template #actions>
            <Button :href="url" size="sm" :text="texts.all" />
        </template>

        <div class="px-4 py-3">
            <Description v-if="!enabled" class="mb-3 text-amber-700 dark:text-amber-400" :text="texts.switchedOff" />

            <Description v-if="!any" class="flex items-center justify-center py-6" :text="texts.none" />

            <template v-else>
                <div v-for="site in sites" :key="site.name" class="mb-3 last:mb-0">
                    <div class="mb-1 text-2xs font-medium uppercase tracking-wide text-gray-500 dark:text-gray-400">{{ site.name }}</div>
                    <Description v-if="!site.notices.length" :text="texts.noneHere" />
                    <table v-else class="w-full widget-table">
                        <tbody>
                            <tr v-for="notice in site.notices" :key="notice.url">
                                <td>
                                    <a class="title-index-field" :href="notice.url" v-text="notice.title" />
                                </td>
                                <td class="text-end text-xs whitespace-nowrap text-gray-600 dark:text-gray-400">
                                    {{ notice.type }}<template v-if="notice.pinned"> · {{ texts.pinned }}</template>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </template>
        </div>
    </Widget>
</template>
