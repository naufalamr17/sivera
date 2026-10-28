import test from "node:test";
import assert from "node:assert/strict";
import {
    mergeEvents,
    groupEvents,
    notificationHeading,
    notificationTitle,
} from "../../resources/js/notification-state.js";

test("backfilled status records do not hide more recent activity", () => {
    const events = [
        { id: 99, issue_id: "a", received_at: "2026-10-26T00:00:00Z" },
        { id: 1, issue_id: "b", received_at: "2026-10-27T00:00:00Z" },
    ];
    assert.deepEqual(
        mergeEvents([], events).map((event) => event.id),
        [1, 99],
    );
    assert.deepEqual(
        groupEvents(events).map((event) => event.id),
        [1, 99],
    );
});

test("status and daily follow-up notifications have distinct labels", () => {
    for (const status of ["RESOLVED", "IGNORED"]) {
        const event = {
            type: "status_changed",
            status,
            exception_class: "RuntimeException",
        };
        assert.equal(notificationHeading(event), "Issue status changed");
        assert.equal(
            notificationTitle(event),
            "Status changed to " + status.toLowerCase() + " · RuntimeException",
        );
    }
    assert.equal(notificationHeading({ type: "follow_up" }), "Daily follow-up");
    assert.equal(
        notificationTitle({
            type: "follow_up",
            exception_class: "RuntimeException",
        }),
        "Daily follow-up · RuntimeException",
    );
    assert.equal(
        notificationTitle({ type: "event", exception_class: "TypeError" }),
        "TypeError",
    );
});

test("overlapping batches never duplicate event counts and keep newest issue details", () => {
    const initial = [
        { id: 1, issue_id: "a", message: "old" },
        { id: 2, issue_id: "b", message: "second" },
    ];
    const incoming = [
        { id: 2, issue_id: "b", message: "second" },
        { id: 3, issue_id: "a", message: "new" },
    ];
    const merged = mergeEvents(initial, incoming);
    assert.deepEqual(
        merged.map((event) => event.id),
        [3, 2, 1],
    );
    assert.deepEqual(
        groupEvents(merged).map((event) => [
            event.issue_id,
            event.count,
            event.message,
        ]),
        [
            ["a", 2, "new"],
            ["b", 1, "second"],
        ],
    );
    assert.equal(initial.length, 2);
});

test("notification history stays bounded during event storms", () => {
    const events = Array.from({ length: 500 }, (_, id) => ({
        id: id + 1,
        issue_id: "issue-" + id,
    }));
    const merged = mergeEvents([], events);
    assert.equal(merged.length, 100);
    assert.equal(groupEvents(merged).length, 20);
    assert.equal(merged[0].id, 500);
    assert.equal(merged.at(-1).id, 401);
});
