export function mergeEvents(previous, incoming) {
    const byId = new Map(previous.map((event) => [event.id, event]));
    for (const event of incoming) byId.set(event.id, event);
    return [...byId.values()].sort((a, b) => b.id - a.id).slice(0, 100);
}

export function groupEvents(events) {
    const groups = new Map();
    for (const event of [...events].sort((a, b) => b.id - a.id)) {
        const group = groups.get(event.issue_id);
        if (group) group.count++;
        else groups.set(event.issue_id, { ...event, count: 1 });
    }
    return [...groups.values()].slice(0, 20);
}
