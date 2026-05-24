/**
 * Active broadcast/Echo config from Inertia (reverb or pusher).
 *
 * @param {{ props?: { broadcast?: object|null, reverb?: object|null } }|null|undefined} page
 */
export function broadcastConfigFromPage(page) {
    return page?.props?.broadcast ?? page?.props?.reverb ?? null;
}
