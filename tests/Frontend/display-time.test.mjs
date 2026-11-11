import test from "node:test";
import assert from "node:assert/strict";
import { timeLabel, clockLabel, timezoneLabel } from "../../resources/js/display-time.js";

test("UTC database timestamps and offset timestamps display the same WIB instant", () => {
    for (const value of ["2026-11-11 18:05:06", "2026-11-11T18:05:06", "2026-11-11T18:05:06Z", "2026-11-12T01:05:06+07:00"]) {
        assert.equal(timeLabel(value, "Asia/Jakarta"), "12 Nov, 01:05 WIB");
    }
    assert.equal(timeLabel(null, "Asia/Jakarta"), "—");
});

test("the update clock and timezone label explicitly use WIB", () => {
    assert.equal(clockLabel(new Date("2026-11-11T18:05:06Z"), "Asia/Jakarta"), "01:05:06 WIB");
    assert.equal(timezoneLabel("Asia/Jakarta"), "WIB (UTC+7)");
});

test("an explicitly configured timezone is respected without a misleading WIB label", () => {
    assert.equal(timeLabel("2026-11-11 18:05:06", "UTC"), "11 Nov, 18:05 UTC");
    assert.equal(clockLabel(new Date("2026-11-11T18:05:06Z"), "UTC"), "18:05:06 UTC");
    assert.equal(timezoneLabel("UTC"), "UTC");
});
