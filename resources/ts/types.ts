/** How often a notice the visitor closed comes back. */
export type Frequency = 'once' | 'session' | 'always';

/** What a dismissal needs to know of a notice: the endpoint's fields and the views' data attributes carry them. */
export interface Dismissable {
    id: string;
    version: number;
    frequency: Frequency;
}

/** A notice element of the addon's views (or a site's own markup) as a Dismissable. */
export function fromElement(el: Element): Dismissable {
    const frequency = el.getAttribute('data-doormat-frequency');

    return {
        id: el.getAttribute('data-doormat-id') ?? '',
        version: Number(el.getAttribute('data-doormat-version') ?? 0),
        frequency: frequency === 'session' || frequency === 'always' ? frequency : 'once',
    };
}
