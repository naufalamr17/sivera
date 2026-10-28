import React, { useEffect, useRef, useState } from "react";
import { createRoot } from "react-dom/client";
import { createPortal } from "react-dom";
import {
    Activity,
    ArrowUpRight,
    Bell,
    BellRing,
    Check,
    CircleAlert,
    Code2,
    KeyRound,
    LayoutDashboard,
    Layers,
    ListFilter,
    LogOut,
    Menu,
    RefreshCw,
    ShieldCheck,
    Users,
    FileText,
    WifiOff,
} from "lucide-react";
import {
    Badge,
    Button,
    Card,
    Popover,
    PopoverContent,
    PopoverTrigger,
    Sheet,
    SheetContent,
    SheetDescription,
    SheetTitle,
    SheetTrigger,
} from "./components/ui";
import {
    mergeEvents,
    groupEvents,
    notificationHeading,
    notificationTitle,
} from "./notification-state";

const icons = {
    overview: LayoutDashboard,
    issues: CircleAlert,
    applications: Layers,
    notifications: Bell,
    users: Users,
    audit: FileText,
    api: Code2,
    password: KeyRound,
};
const formatCount = (value) => Number(value).toLocaleString("en-US");
const readStored = (key) => {
    try {
        return localStorage.getItem(key);
    } catch {
        return null;
    }
};
const storeValue = (key, value) => {
    try {
        localStorage.setItem(key, String(value));
    } catch {
        /* Storage can be disabled. */
    }
};
const parseTime = (value) =>
    new Date(value?.includes("T") ? value : value?.replace(" ", "T") + "Z");
const timeLabel = (value) =>
    parseTime(value).toLocaleString("en-GB", {
        day: "numeric",
        month: "short",
        hour: "2-digit",
        minute: "2-digit",
    });

function Navigation({ config }) {
    return (
        <>
            <a className="workspace-brand" href={config.dashboard}>
                <img
                    className="workspace-logo"
                    src={config.icon}
                    width="34"
                    height="34"
                    alt=""
                />
                <span>
                    Sivera
                    <span className="brand-subtitle">
                        Application monitoring
                    </span>
                </span>
            </a>
            <div className="navigation-label">Workspace</div>
            <nav className="workspace-nav" aria-label="Workspace">
                {config.navigation.map((item) => {
                    const Icon = icons[item.icon];
                    return (
                        <React.Fragment key={item.url}>
                            {item.admin && (
                                <div className="navigation-label admin-label">
                                    Administration
                                </div>
                            )}
                            {item.account && (
                                <div className="navigation-label admin-label">
                                    Account
                                </div>
                            )}
                            <Button
                                asChild
                                variant="ghost"
                                className={
                                    "nav-item " +
                                    (item.active ? "is-active" : "")
                                }
                            >
                                <a
                                    href={item.url}
                                    aria-current={
                                        item.active ? "page" : undefined
                                    }
                                >
                                    <Icon />
                                    <span>{item.label}</span>
                                </a>
                            </Button>
                        </React.Fragment>
                    );
                })}
            </nav>
            <div className="workspace-user">
                <span className="user-avatar">
                    {config.user.name.slice(0, 1).toUpperCase()}
                </span>
                <div>
                    <strong>{config.user.name}</strong>
                    <small>{config.user.role}</small>
                </div>
                <form action={config.logout} method="POST">
                    <input
                        type="hidden"
                        name="_token"
                        value={
                            document.querySelector('meta[name="csrf-token"]')
                                .content
                        }
                    />
                    <Button
                        variant="ghost"
                        size="icon"
                        aria-label="Log out"
                        title="Log out"
                    >
                        <LogOut />
                    </Button>
                </form>
            </div>
        </>
    );
}

function NotificationCenter({ config }) {
    const readKey = "sivera.read.v2." + config.user.id;
    const deliveredKey = "sivera.delivered.v2." + config.user.id;
    const [events, setEvents] = useState([]);
    const [readCursor, setReadCursor] = useState(
        Number(readStored(readKey) || 0),
    );
    const [connected, setConnected] = useState(false);
    const [expired, setExpired] = useState(false);
    const [toast, setToast] = useState(null);
    const [permission, setPermission] = useState(
        "Notification" in window ? Notification.permission : "unsupported",
    );
    const [permissionError, setPermissionError] = useState("");
    const [open, setOpen] = useState(false);
    const cursorRef = useRef(null);
    const grouped = groupEvents(events);
    const unread = grouped.filter((event) => event.id > readCursor).length;

    useEffect(() => {
        let stopped = false;
        let timer;
        const controller = new AbortController();
        async function poll() {
            let delay = config.pollSeconds * 1000;
            try {
                const url = new URL(config.feed);
                if (cursorRef.current !== null)
                    url.searchParams.set("after", cursorRef.current);
                const response = await fetch(url, {
                    signal: controller.signal,
                    credentials: "same-origin",
                    headers: { Accept: "application/json" },
                });
                if (response.status === 401 || response.status === 403) {
                    setExpired(true);
                    setConnected(false);
                    setEvents([]);
                    setToast(null);
                    stopped = true;
                    return;
                }
                if (!response.ok) throw new Error("Feed unavailable");
                const data = await response.json();
                if (stopped) return;
                const initial = cursorRef.current === null;
                setEvents((previous) => mergeEvents(previous, data.events));
                setConnected(true);
                if (initial && readStored(readKey) === null) {
                    storeValue(readKey, data.cursor);
                    setReadCursor(data.cursor);
                }
                if (!initial && data.events.length) {
                    const groups = groupEvents(data.events);
                    const latest = groups[0];
                    const issueCount = new Set(
                        data.events.map((event) => event.issue_id),
                    ).size;
                    setToast({
                        title:
                            issueCount === 1
                                ? notificationHeading(latest)
                                : "New issue activity",
                        text:
                            issueCount === 1
                                ? latest.application +
                                  " · " +
                                  notificationTitle(latest)
                                : issueCount + " issues have new activity",
                        url: latest.url,
                    });
                    const delivered = Number(readStored(deliveredKey) || 0);
                    if (
                        "Notification" in window &&
                        Notification.permission === "granted" &&
                        data.cursor > delivered
                    ) {
                        // Show only application and severity on the lock screen.
                        storeValue(deliveredKey, data.cursor);
                        try {
                            const notification = new Notification(
                                "Sivera · " + notificationHeading(latest),
                                {
                                    body:
                                        latest.application +
                                        " · " +
                                        (latest.type === "status_changed"
                                            ? latest.status
                                            : latest.severity),
                                    icon: config.icon,
                                    tag: "sivera-activity",
                                },
                            );
                            notification.onclick = () => {
                                window.focus();
                                window.location.assign(latest.url);
                                notification.close();
                            };
                        } catch {
                            /* Some mobile browsers require Web Push instead. */
                        }
                    }
                }
                cursorRef.current = data.cursor;
                if (data.has_more) delay = 250;
            } catch (error) {
                if (!stopped && error.name !== "AbortError")
                    setConnected(false);
            } finally {
                if (!stopped && !controller.signal.aborted)
                    timer = setTimeout(poll, delay);
            }
        }
        poll();
        const sync = (event) => {
            if (event.key === readKey)
                setReadCursor(Number(event.newValue || 0));
        };
        window.addEventListener("storage", sync);
        return () => {
            stopped = true;
            controller.abort();
            clearTimeout(timer);
            window.removeEventListener("storage", sync);
        };
    }, [config.feed, config.pollSeconds, readKey, deliveredKey]);

    useEffect(() => {
        if (!toast) return;
        const timer = setTimeout(() => setToast(null), 7000);
        return () => clearTimeout(timer);
    }, [toast]);
    const markRead = () => {
        const cursor = cursorRef.current ?? 0;
        storeValue(readKey, cursor);
        setReadCursor(cursor);
    };
    async function enableBrowser() {
        setPermissionError("");
        if (!window.isSecureContext) {
            setPermissionError(
                "Open Sivera using trusted HTTPS to enable browser alerts.",
            );
            return;
        }
        try {
            setPermission(await Notification.requestPermission());
        } catch {
            setPermissionError("Browser notifications are unavailable here.");
        }
    }
    return (
        <>
            <span
                className={
                    "connection-label " +
                    (!connected ? "connection-offline" : "")
                }
            >
                {connected ? (
                    <span className="status-dot" />
                ) : (
                    <WifiOff size={13} />
                )}
                {expired
                    ? "Session expired"
                    : connected
                      ? "Live"
                      : "Reconnecting"}
            </span>
            <Popover
                open={open}
                onOpenChange={(value) => {
                    setOpen(value);
                    if (value) markRead();
                }}
            >
                <PopoverTrigger asChild>
                    <Button
                        variant="outline"
                        size="icon"
                        className="notification-button"
                        aria-label={
                            unread
                                ? "Notifications, " + unread + " unread issues"
                                : "Notifications"
                        }
                    >
                        <Bell />
                        {unread > 0 && (
                            <span className="notification-count">
                                {unread > 9 ? "9+" : unread}
                            </span>
                        )}
                    </Button>
                </PopoverTrigger>
                <PopoverContent className="notification-popover">
                    <div className="notification-heading">
                        <div>
                            <h2>Notifications</h2>
                            <p>
                                Latest issue activity across your applications.
                            </p>
                        </div>
                        <Button
                            variant="ghost"
                            size="icon"
                            onClick={markRead}
                            aria-label="Mark notifications as read"
                        >
                            <Check />
                        </Button>
                    </div>
                    <div className="browser-permission">
                        {permission === "default" && (
                            <Button
                                variant="outline"
                                size="sm"
                                onClick={enableBrowser}
                            >
                                <BellRing />
                                Enable browser alerts
                            </Button>
                        )}
                        {permission === "granted" && (
                            <span>
                                <Check size={14} />
                                Browser alerts enabled
                            </span>
                        )}
                        {permission === "denied" && (
                            <p>
                                Browser alerts blocked. Allow notifications in
                                this site's browser settings.
                            </p>
                        )}
                        {permission === "unsupported" && (
                            <p>
                                This browser does not support desktop
                                notifications.
                            </p>
                        )}
                        {permissionError && (
                            <p role="alert">{permissionError}</p>
                        )}
                        <small>
                            Alerts work while Sivera is open. Email arrives each
                            morning.
                        </small>
                    </div>
                    {expired && (
                        <p role="alert" className="notification-empty">
                            Your session expired. Reload and sign in again.
                        </p>
                    )}
                    <div className="notification-list">
                        {grouped.length ? (
                            grouped.map((event) => (
                                <a
                                    key={event.issue_id}
                                    href={event.url}
                                    className="notification-item"
                                    onClick={markRead}
                                >
                                    <span
                                        className={
                                            "event-dot " +
                                            (event.severity === "CRITICAL"
                                                ? "is-critical"
                                                : "")
                                        }
                                    />
                                    <div>
                                        <strong>{event.application}</strong>
                                        <p>{notificationTitle(event)}</p>
                                        <small>
                                            {timeLabel(event.received_at)}
                                            {event.count > 1
                                                ? " · " +
                                                  event.count +
                                                  " recent updates"
                                                : ""}
                                        </small>
                                    </div>
                                    <ArrowUpRight size={15} />
                                </a>
                            ))
                        ) : (
                            <p className="notification-empty">
                                You're all caught up. New events will appear
                                here.
                            </p>
                        )}
                    </div>
                </PopoverContent>
            </Popover>
            {toast &&
                createPortal(
                    <div className="live-toast" role="status">
                        <CircleAlert size={18} />
                        <div>
                            <strong>{toast.title}</strong>
                            <a href={toast.url}>{toast.text}</a>
                        </div>
                        <Button
                            size="icon"
                            variant="ghost"
                            aria-label="Dismiss notification"
                            onClick={() => setToast(null)}
                        >
                            ×
                        </Button>
                    </div>,
                    document.body,
                )}
        </>
    );
}

function AppChrome({ config }) {
    return (
        <>
            {createPortal(
                <Navigation config={config} />,
                document.getElementById("workspace-sidebar"),
            )}
            <header className="topbar">
                <div className="topbar-start">
                    <Sheet>
                        <SheetTrigger asChild>
                            <Button
                                variant="ghost"
                                size="icon"
                                className="mobile-menu"
                                aria-label="Open navigation"
                            >
                                <Menu />
                            </Button>
                        </SheetTrigger>
                        <SheetContent>
                            <SheetTitle className="sr-only">
                                Workspace navigation
                            </SheetTitle>
                            <SheetDescription className="sr-only">
                                Navigate between monitoring pages.
                            </SheetDescription>
                            <Navigation config={config} />
                        </SheetContent>
                    </Sheet>
                    <span className="breadcrumb-workspace">Workspace</span>
                    <span className="breadcrumb-divider">/</span>
                    <strong>{config.heading}</strong>
                </div>
                <div className="topbar-actions">
                    <NotificationCenter config={config} />
                </div>
            </header>
        </>
    );
}

function Severity({ value }) {
    return (
        <Badge className={"severity severity-" + value.toLowerCase()}>
            {value.toLowerCase()}
        </Badge>
    );
}
function SectionTitle({ title, description, children }) {
    return (
        <div className="card-heading">
            <div>
                <h2>{title}</h2>
                {description && <p>{description}</p>}
            </div>
            {children}
        </div>
    );
}
function Empty({ children }) {
    return (
        <div className="dashboard-empty">
            <CircleAlert size={22} />
            <p>{children}</p>
        </div>
    );
}

function Dashboard({ config }) {
    const [data, setData] = useState(config.summary);
    const [connected, setConnected] = useState(true);
    const [updatedAt, setUpdatedAt] = useState(new Date());
    const [from, setFrom] = useState(config.summary.from);
    const [to, setTo] = useState(config.summary.to);
    const issuePageUrl = (page) => {
        const url = new URL(config.dashboard);
        url.searchParams.set("from", data.from);
        url.searchParams.set("to", data.to);
        url.searchParams.set("issues_page", page);
        return url.toString();
    };
    useEffect(() => {
        let stopped = false;
        let timer;
        const controller = new AbortController();
        const poll = async () => {
            try {
                const url = new URL(config.endpoint);
                url.searchParams.set("from", config.summary.from);
                url.searchParams.set("to", config.summary.to);
                url.searchParams.set(
                    "issues_page",
                    config.summary.latest_pagination.current_page,
                );
                const response = await fetch(url, {
                    credentials: "same-origin",
                    signal: controller.signal,
                    headers: { Accept: "application/json" },
                });
                if (!response.ok) throw new Error("Summary unavailable");
                const next = await response.json();
                if (!stopped) {
                    setData(next);
                    setConnected(true);
                    setUpdatedAt(new Date());
                }
            } catch (error) {
                if (!stopped && error.name !== "AbortError")
                    setConnected(false);
            } finally {
                if (!stopped)
                    timer = setTimeout(poll, config.pollSeconds * 1000);
            }
        };
        timer = setTimeout(poll, config.pollSeconds * 1000);
        return () => {
            stopped = true;
            clearTimeout(timer);
            controller.abort();
        };
    }, [
        config.endpoint,
        config.pollSeconds,
        config.summary.from,
        config.summary.to,
        config.summary.latest_pagination.current_page,
    ]);

    const maxActivity = Math.max(
        1,
        ...data.activity.map((bucket) => bucket.count),
    );
    const totalErrors = data.ranking.reduce(
        (sum, app) => sum + Number(app.events),
        0,
    );
    const metrics = [
        {
            label: "Events received",
            value: data.events,
            detail: "In selected period",
            icon: Activity,
        },
        {
            label: "Open issues",
            value: data.open,
            detail: "Current status · active in period",
            icon: CircleAlert,
        },
        {
            label: "Critical issues",
            value: data.critical,
            detail: "Unresolved · active in period",
            icon: ShieldCheck,
            critical: data.critical > 0,
        },
        {
            label: "Applications",
            value: data.applications,
            detail: "Registered in workspace",
            icon: Layers,
        },
    ];
    return (
        <>
            <div className="dashboard-head">
                <div>
                    <h1>Overview</h1>
                    <p>Understand what broke, where, and how often.</p>
                </div>
                <a className="button" href={config.issues}>
                    Explore issues
                    <ArrowUpRight size={14} />
                </a>
            </div>
            <div className="dashboard-toolbar">
                <form
                    method="GET"
                    action={config.dashboard}
                    className="date-filter"
                >
                    <ListFilter size={16} />
                    <label>
                        From
                        <input
                            type="date"
                            name="from"
                            value={from}
                            onChange={(event) => {
                                setFrom(event.target.value);
                                if (event.target.value > to)
                                    setTo(event.target.value);
                            }}
                            required
                        />
                    </label>
                    <span className="date-separator">—</span>
                    <label>
                        To
                        <input
                            type="date"
                            name="to"
                            value={to}
                            onChange={(event) => setTo(event.target.value)}
                            min={from}
                            required
                        />
                    </label>
                    <Button variant="outline" size="sm" type="submit">
                        Apply
                    </Button>
                    <a className="date-reset" href={config.dashboard}>
                        Last 7 days
                    </a>
                </form>
                <span className="period-timezone">{data.timezone}</span>
            </div>
            <div className="dashboard-metrics">
                {metrics.map(
                    ({ label, value, detail, icon: Icon, critical }) => (
                        <Card
                            className={
                                "stat-card " + (critical ? "stat-critical" : "")
                            }
                            key={label}
                        >
                            <div>
                                <span>{label}</span>
                                <Icon size={16} />
                            </div>
                            <strong>{formatCount(value)}</strong>
                            <small>{detail}</small>
                        </Card>
                    ),
                )}
            </div>
            <div className="dashboard-columns">
                <Card className="volume-card">
                    <SectionTitle
                        title="Event volume"
                        description={
                            data.from === data.to
                                ? "Hourly events received on " + data.from
                                : "Daily events · " +
                                  data.from +
                                  " to " +
                                  data.to
                        }
                    >
                        <span className="chart-legend">
                            <i />
                            Events
                        </span>
                    </SectionTitle>
                    <div className="volume-chart">
                        <div className="chart-axis">
                            <span>{formatCount(maxActivity)}</span>
                            <span>
                                {formatCount(Math.round(maxActivity / 2))}
                            </span>
                            <span>0</span>
                        </div>
                        <div className="volume-plot">
                            <div className="plot-lines">
                                <i />
                                <i />
                                <i />
                            </div>
                            <div
                                className="volume-bars"
                                role="img"
                                aria-label={
                                    "Event counts for " +
                                    data.from +
                                    " through " +
                                    data.to
                                }
                            >
                                {data.activity.map((bucket, index) => (
                                    <div
                                        key={index}
                                        className="volume-slot"
                                        title={
                                            bucket.label +
                                            ": " +
                                            formatCount(bucket.count) +
                                            " events"
                                        }
                                    >
                                        <div
                                            style={{
                                                height: bucket.count
                                                    ? Math.max(
                                                          2,
                                                          (bucket.count /
                                                              maxActivity) *
                                                              100,
                                                      ) + "%"
                                                    : "0",
                                            }}
                                        />
                                    </div>
                                ))}
                            </div>
                            <div className="volume-labels">
                                {data.activity
                                    .filter(
                                        (_, index) =>
                                            index %
                                                Math.max(
                                                    1,
                                                    Math.ceil(
                                                        data.activity.length /
                                                            5,
                                                    ),
                                                ) ===
                                                0 ||
                                            index === data.activity.length - 1,
                                    )
                                    .map((bucket, index) => (
                                        <span key={index}>{bucket.label}</span>
                                    ))}
                            </div>
                        </div>
                    </div>
                    <div className="card-foot">
                        <span>
                            {formatCount(data.events)} events in this period
                        </span>
                        <span>
                            {formatCount(data.resolved)} issues currently
                            resolved
                        </span>
                    </div>
                </Card>
                <Card className="ranking-card">
                    <SectionTitle
                        title="Application ranking"
                        description="Most ERROR and CRITICAL events"
                    />
                    {data.ranking.length ? (
                        <ol className="ranking-list">
                            {data.ranking.map((app, index) => (
                                <li key={app.id}>
                                    <span className="rank-number">
                                        {index + 1}
                                    </span>
                                    <div className="rank-detail">
                                        <div>
                                            <a
                                                href={
                                                    config.issues +
                                                    "?application=" +
                                                    app.id
                                                }
                                            >
                                                {app.name}
                                            </a>
                                            <strong>
                                                {formatCount(app.events)}
                                            </strong>
                                        </div>
                                        <div className="rank-track">
                                            <span
                                                style={{
                                                    width:
                                                        (Number(app.events) /
                                                            Math.max(
                                                                1,
                                                                Number(
                                                                    data
                                                                        .ranking[0]
                                                                        .events,
                                                                ),
                                                            )) *
                                                            100 +
                                                        "%",
                                                }}
                                            />
                                        </div>
                                        <small>
                                            {formatCount(app.issues)} distinct
                                            issues
                                        </small>
                                    </div>
                                </li>
                            ))}
                        </ol>
                    ) : (
                        <Empty>No errors in this period.</Empty>
                    )}
                    <div className="card-foot">
                        Top 5 applications · {formatCount(totalErrors)} errors
                        shown
                    </div>
                </Card>
            </div>
            <Card className="error-card">
                <SectionTitle
                    title="Most frequent errors"
                    description="Reported exceptions and messages · ranked by events in this period"
                >
                    <Badge variant="outline">Top 5</Badge>
                </SectionTitle>
                <div className="table-scroll">
                    <table className="dashboard-table">
                        <thead>
                            <tr>
                                <th>Error / reported reason</th>
                                <th>Application</th>
                                <th>Severity</th>
                                <th className="number-cell">Events</th>
                                <th></th>
                            </tr>
                        </thead>
                        <tbody>
                            {data.top_errors.map((issue) => (
                                <tr key={issue.uuid}>
                                    <td>
                                        <a
                                            className="table-title"
                                            href={
                                                config.issueBase +
                                                "/" +
                                                issue.uuid
                                            }
                                        >
                                            {issue.exception_class}
                                        </a>
                                        <p className="error-message">
                                            {issue.message}
                                        </p>
                                        <small className="error-location">
                                            {issue.file || "Unknown file"}
                                            {issue.line ? ":" + issue.line : ""}
                                            {issue.route
                                                ? " · " + issue.route
                                                : ""}
                                        </small>
                                    </td>
                                    <td className="app-cell">
                                        {issue.application.name}
                                    </td>
                                    <td>
                                        <Severity value={issue.severity} />
                                    </td>
                                    <td className="number-cell event-total">
                                        {formatCount(issue.period_count)}
                                    </td>
                                    <td>
                                        <Button
                                            asChild
                                            variant="ghost"
                                            size="icon"
                                        >
                                            <a
                                                href={
                                                    config.issueBase +
                                                    "/" +
                                                    issue.uuid
                                                }
                                                aria-label={
                                                    "Inspect " +
                                                    issue.exception_class
                                                }
                                            >
                                                <ArrowUpRight />
                                            </a>
                                        </Button>
                                    </td>
                                </tr>
                            ))}
                            {!data.top_errors.length && (
                                <tr>
                                    <td colSpan={5}>
                                        <Empty>
                                            No errors received for the selected
                                            dates.
                                        </Empty>
                                    </td>
                                </tr>
                            )}
                        </tbody>
                    </table>
                </div>
            </Card>
            <Card className="latest-card">
                <SectionTitle
                    title="Recent issues"
                    description="Latest activity within your date range"
                >
                    <Button asChild variant="outline" size="sm">
                        <a href={config.issues}>
                            View all
                            <ArrowUpRight />
                        </a>
                    </Button>
                </SectionTitle>
                <div className="table-scroll">
                    <table className="dashboard-table">
                        <thead>
                            <tr>
                                <th>Issue</th>
                                <th>Status</th>
                                <th className="number-cell">Period events</th>
                                <th>Last received</th>
                            </tr>
                        </thead>
                        <tbody>
                            {data.latest.map((issue) => (
                                <tr key={issue.uuid}>
                                    <td>
                                        <a
                                            className="table-title"
                                            href={
                                                config.issueBase +
                                                "/" +
                                                issue.uuid
                                            }
                                        >
                                            {issue.exception_class}
                                        </a>
                                        <small>{issue.application.name}</small>
                                    </td>
                                    <td>
                                        <Badge className="status-badge">
                                            {issue.status
                                                .toLowerCase()
                                                .replaceAll("_", " ")}
                                        </Badge>
                                    </td>
                                    <td className="number-cell">
                                        {formatCount(issue.period_count)}
                                    </td>
                                    <td className="last-received">
                                        {timeLabel(issue.period_last_seen)}
                                    </td>
                                </tr>
                            ))}
                            {!data.latest.length && (
                                <tr>
                                    <td colSpan={4} className="empty">
                                        No activity for these dates.
                                    </td>
                                </tr>
                            )}
                        </tbody>
                    </table>
                </div>
                <nav
                    className="issue-pagination"
                    aria-label="Recent issues pagination"
                >
                    <span>
                        {data.latest_pagination.total
                            ? data.latest_pagination.from +
                              "–" +
                              data.latest_pagination.to +
                              " of " +
                              formatCount(data.latest_pagination.total) +
                              " issues"
                            : "0 issues"}
                    </span>
                    <div className="issue-pagination-actions">
                        {data.latest_pagination.current_page > 1 ? (
                            <Button asChild variant="outline" size="sm">
                                <a
                                    href={issuePageUrl(
                                        data.latest_pagination.current_page - 1,
                                    )}
                                    rel="prev"
                                >
                                    Previous
                                </a>
                            </Button>
                        ) : (
                            <Button variant="outline" size="sm" disabled>
                                Previous
                            </Button>
                        )}
                        <span>
                            Page {data.latest_pagination.current_page} of{" "}
                            {data.latest_pagination.last_page}
                        </span>
                        {data.latest_pagination.current_page <
                        data.latest_pagination.last_page ? (
                            <Button asChild variant="outline" size="sm">
                                <a
                                    href={issuePageUrl(
                                        data.latest_pagination.current_page + 1,
                                    )}
                                    rel="next"
                                >
                                    Next
                                </a>
                            </Button>
                        ) : (
                            <Button variant="outline" size="sm" disabled>
                                Next
                            </Button>
                        )}
                    </div>
                </nav>
            </Card>
            <div className="dashboard-footer">
                <span>
                    {connected ? (
                        <RefreshCw size={12} />
                    ) : (
                        <WifiOff size={12} />
                    )}
                    {connected
                        ? "Updated " +
                          updatedAt.toLocaleTimeString("en-GB") +
                          " · Every " +
                          config.pollSeconds +
                          "s"
                        : "Connection lost. Showing last received data."}
                </span>
                <span>Status and severity reflect current values.</span>
            </div>
        </>
    );
}

const workspaceData = document.getElementById("workspace-data");
if (workspaceData)
    createRoot(document.getElementById("workspace-header")).render(
        <AppChrome config={JSON.parse(workspaceData.textContent)} />,
    );
const dashboardData = document.getElementById("dashboard-data");
if (dashboardData)
    createRoot(document.getElementById("dashboard-root")).render(
        <Dashboard config={JSON.parse(dashboardData.textContent)} />,
    );
