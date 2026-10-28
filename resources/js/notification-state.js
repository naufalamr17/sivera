function newestFirst(a, b) {
    const difference = Date.parse(b.received_at) - Date.parse(a.received_at);
    return Number.isFinite(difference) && difference !== 0
        ? difference
        : b.id - a.id;
}

export function mergeEvents(previous, incoming) {
    const byId = new Map(previous.map((event) => [event.id, event]));
    for (const event of incoming) byId.set(event.id, event);
    return [...byId.values()].sort(newestFirst).slice(0, 100);
}

export function groupEvents(events) {
    const groups = new Map();
    for (const event of [...events].sort(newestFirst)) {
        const group = groups.get(event.issue_id);
        if (group) group.count++;
        else groups.set(event.issue_id, { ...event, count: 1 });
    }
    return [...groups.values()].slice(0, 20);
}

export function notificationHeading(event) {
    if (event.type === "status_changed") return "Issue status changed";
    if (event.type === "follow_up") return "Daily follow-up";
    return "New error activity";
}

export function notificationTitle(event) {
    if (event.type === "status_changed") {
        return (
            "Status changed to " +
            event.status.toLowerCase().replaceAll("_", " ") +
            " · " +
            event.exception_class
        );
    }
    if (event.type === "follow_up")
        return "Daily follow-up · " + event.exception_class;
    return event.exception_class;
}
