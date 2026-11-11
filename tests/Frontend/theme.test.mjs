import test from "node:test";
import assert from "node:assert/strict";
import { readFileSync } from "node:fs";
import { runInNewContext } from "node:vm";

const script = readFileSync(new URL("../../public/theme.js", import.meta.url), "utf8");

function loadTheme(stored = null, blocked = false) {
    const root = { dataset: {} };
    let click;
    runInNewContext(script, {
        document: {
            documentElement: root,
            addEventListener: (event, handler) => { click = handler; },
        },
        localStorage: {
            getItem: () => {
                if (blocked) throw new Error("Storage disabled");
                return stored;
            },
            setItem: (key, value) => {
                if (blocked) throw new Error("Storage disabled");
                assert.equal(key, "sivera.theme");
                stored = value;
            },
        },
    });
    return {
        root,
        stored: () => stored,
        click: (toggle = true) => click({ target: { closest: () => toggle ? {} : null } }),
    };
}

test("theme defaults to Cyberpunk and restores only valid saved preferences", () => {
    for (const stored of [null, "invalid", "system", "dark"]) {
        assert.equal(loadTheme(stored).root.dataset.theme, "cyberpunk");
    }
    for (const stored of ["light", "cyberpunk"]) {
        assert.equal(loadTheme(stored).root.dataset.theme, stored);
    }
});

test("theme toggles from nested button content and persists across page loads", () => {
    const page = loadTheme();
    page.click(false);
    assert.equal(page.root.dataset.theme, "cyberpunk");
    page.click();
    assert.equal(page.root.dataset.theme, "light");
    assert.equal(loadTheme(page.stored()).root.dataset.theme, "light");
    page.click();
    assert.equal(page.root.dataset.theme, "cyberpunk");
    assert.equal(page.stored(), "cyberpunk");
});

test("blocked browser storage does not prevent theme initialization or switching", () => {
    const page = loadTheme(null, true);
    assert.equal(page.root.dataset.theme, "cyberpunk");
    page.click();
    assert.equal(page.root.dataset.theme, "light");
    page.click();
    assert.equal(page.root.dataset.theme, "cyberpunk");
});
