import LiveNoticesWidget from './components/LiveNoticesWidget.vue';

Statamic.booting(() => {
    Statamic.$components.register('doormat-live-notices-widget', LiveNoticesWidget);
});
