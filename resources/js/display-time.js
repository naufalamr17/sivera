export const timezoneLabel = (timezone) =>
    timezone === "Asia/Jakarta" ? "WIB (UTC+7)" : timezone;

const timezoneSuffix = (timezone) =>
    timezone === "Asia/Jakarta" ? "WIB" : timezone;

export function timeLabel(value, timezone) {
    if (!value) return "—";
    const iso = value.replace(" ", "T");
    const instant = new Date(/(?:Z|[+-]\d{2}:?\d{2})$/i.test(iso) ? iso : iso + "Z");
    return instant.toLocaleString("en-GB", {
        timeZone: timezone,
        day: "numeric",
        month: "short",
        hour: "2-digit",
        minute: "2-digit",
    }) + " " + timezoneSuffix(timezone);
}

export const clockLabel = (value, timezone) =>
    value.toLocaleTimeString("en-GB", { timeZone: timezone }) + " " + timezoneSuffix(timezone);
