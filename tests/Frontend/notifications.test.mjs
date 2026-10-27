import test from "node:test";
import assert from "node:assert/strict";
import {
    mergeEvents,
    groupEvents,
} from "../../resources/js/notification-state.js";

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
